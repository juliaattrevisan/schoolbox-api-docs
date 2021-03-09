<?php
/**
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */
declare(strict_types=1);

namespace Custom\Tigs\Summative;

use Aura\Router\Router;
use Schoolbox\GenericCollection;
use Schoolbox\YearLevel;
use Zend\Uri\UriInterface;
use function count;
use Custom\Tigs\Summative\ParticipationReport\FolderFetcher;
use External_UserInterface;
use GuzzleHttp\Psr7\Uri;
use function implode;
use function in_array;
use InvalidArgumentException;
use RuntimeException;
use Schoolbox\Collection;
use Schoolbox\Resource\Folder;
use Schoolbox\Application\CliKernelAbstract;
use Schoolbox\Application\CliTool;
use Schoolbox\MapperFactory;
use Schoolbox\Realtime\Pipe\PipeManager;
use Schoolbox\Role;
use Schoolbox\UserMapper;
use GuzzleHttp\Client;
use Schoolbox\User;

/**
 * A CLI App for batch printing PDFs to an archive
 */
class CliPdfGenerator extends CliTool\PdfGenerator\App
{
    // This tools CLI options
    private const OPT_SHORT_HELP            = 'h';                 // print help
    private const OPT_LONG_HELP             = 'help';              // print help
    private const OPT_LONG_HASH             = 'hash:';             // session hash
    private const OPT_LONG_USERNAME         = 'username:';         // username
    private const OPT_LONG_STUDENT_ID       = 'student-id:';       // student external id
    private const OPT_LONG_YEAR_LEVEL       = 'year-level:';       // year-level
    private const OPT_LONG_CURRICULUM       = 'curriculum:';       // curriculum
    private const OPT_LONG_MAX_FAILS        = 'max-fails:';        // Number of consecutive fails after which to abort the script's execution.
    private const OPT_LONG_JS_DELAY         = 'js-delay:';         // How many milliseconds to let JS run before attempting to print page
    private const OPT_LONG_REPORT_DATE      = 'report-date:';      // to generate a report for some point in time
    private const OPT_LONG_USER_STYLE_SHEET = 'user-style-sheet:'; // user style sheet
    private const OPT_LONG_ROLE_TYPE        = 'role-type:';        // role-type override
    private const OPT_LONG_INCLUDE_SUBJECTS = 'include-subjects';  // include subject reports

    private const DEFAULT_MAX_FAILS   = 3;
    private const DEFAULT_JS_DELAY    = 1000;

    private const CURRICULUM_IB  = 'ib';
    private const CURRICULUM_HSC = 'hsc';

    /** @var string|null */
    private $sessionHash;

    /** @var string|null */
    private $username;

    /** @var string|null */
    private $studentExternalId;

    /** @var string|null */
    private $yearLevel;

    /** @var string|null */
    private $curriculum;

    /** @var null|string */
    private $reportDateOverride;

    /** @var UserMapper */
    private $userMapper;

    /** @var External_UserInterface */
    private $externalUser;

    /** @var Folder\Mapper */
    private $folderMapper;

    /** @var Router */
    private $router;

    /**
     * Fully-qualified domain name
     * @var string
     */
    private $fqdn;

    /** @var int */
    private $maxFailures;

    /** @var FolderFetcher */
    private $folderFetcher;

    /**
     * How many milliseconds to let javascript to run before snapping page.
     *
     * Setting this too low will cause either total failure, or some URLs to be skipped
     *
     * Use low value for testing, high/default value for production
     *
     * @var int
     */
    private $jsDelay;

    /**
     * A user style sheet, to load with every page
     * @var string
     */
    private $userStyleSheet;

    /**
     * Enable user to print reports with grade visibility for a role other than current user
     * @var string
     */
    private $roleTypeOverride;

    /**
     * A guzzle client to request each Subject Report
     *
     * @var Client
     */
    private $guzzleClient;

    /**
     * For now, just a flat array of all URLs that contain errors. May group by student/folder in the future.
     *
     * @var string[]
     */
    private $pageErrors = [];


    /** @inheritdoc */
    public function getShortOpts(): array
    {
        return [self::OPT_SHORT_HELP];
    }


    /** @inheritdoc */
    public function getLongOpts(): array
    {
        return [
            self::OPT_LONG_HELP,
            self::OPT_LONG_HASH,
            self::OPT_LONG_USERNAME,
            self::OPT_LONG_STUDENT_ID,
            self::OPT_LONG_YEAR_LEVEL,
            self::OPT_LONG_CURRICULUM,
            self::OPT_LONG_MAX_FAILS,
            self::OPT_LONG_JS_DELAY,
            self::OPT_LONG_REPORT_DATE,
            self::OPT_LONG_USER_STYLE_SHEET,
            self::OPT_LONG_ROLE_TYPE,
            self::OPT_LONG_INCLUDE_SUBJECTS,
        ];
    }


    /**
     * @inheritdoc
     * @throws RuntimeException
     * @throws \Alaress\Database\Predicate\InvalidColumnException
     * @throws \Aura\Di\Exception\ServiceNotFound
     * @throws \Aura\Router\Exception\RouteNotFound
     * @throws \GuzzleHttp\Exception\GuzzleException
     * @throws InvalidArgumentException
     * @throws \Schoolbox\Mapper\DefinitionException
     * @throws \Schoolbox\Mapper\Exception
     * @throws \Schoolbox\Mapper\Exception\Deleted
     * @throws \UnderflowException
     */
    public function run(): void
    {
        // Did user request help?
        if ($this->getOpt(self::OPT_SHORT_HELP) || $this->getOpt(self::OPT_LONG_HELP)) {
            $this->printHelp();
            exit(CliKernelAbstract::EXIT_STATUS_OK);
        }

        // Parse command-line options. If invalid options detected, then print help and exit.
        if (!$this->parseAppOptions()) {
            $this->printHelp();
            exit(CliKernelAbstract::EXIT_STATUS_APP_ERROR);
        }

        $di = $this->getDi();
        /** @var MapperFactory $mapperFactory */
        $mapperFactory       = $this->getDi()->get('mapperFactory');
        $this->userMapper    = $mapperFactory->get(UserMapper::class);
        $this->folderMapper  = $mapperFactory->get(Folder\Mapper::class);
        $this->externalUser  = $di->get('externalUser');
        $this->fqdn          = \trim("{$this->getConfig()->get('site_hostname')}.{$this->getConfig()->get('site_domain')}");
        $this->router        = $di->get('router');
        $this->folderFetcher = new FolderFetcher($this->folderMapper, $this->externalUser);

        parent::run();
    }


    /**
     * @inheritdoc
     */
    protected function generatePdfSources(UriInterface $baseUrl): GenericCollection
    {
        $collection          = new GenericCollection(CliTool\PdfGenerator\SourceBundle::class);
        $studentIds          = $this->getStudentIds();
        $maxIdLength         = $this->calculateMaxIdLength($studentIds);
        $excludedClassCodes  = $this->getExcludedClassCodes();
        foreach ($studentIds as $studentId) {
            $student         = $this->userMapper->findById($studentId);
            $externalStudent = $this->externalUser->getStudent($student->getExternalId());
            $message         = \sprintf("Printing PDF for student. (id: %{$maxIdLength}d), (externalId: %-s)", $studentId, $student->getExternalId());
            $this->getLogger()->notice($message);

            $collection->add(
                new CliTool\PdfGenerator\SourceBundle(
                    $student->getExternalId() . '.pdf',
                    $this->generateUrlsForStudent($student, $externalStudent, $excludedClassCodes)
                )
            );
        }

        return $collection;
    }


    /**
     * Parses options and returns whether all options are valid
     *
     * @return bool
     */
    private function parseAppOptions(): bool
    {
        $valid                      = true;
        $this->sessionHash          = $this->getOpt(self::OPT_LONG_HASH);
        $this->username             = $this->getOpt(self::OPT_LONG_USERNAME);
        $this->studentExternalId    = $this->getOpt(self::OPT_LONG_STUDENT_ID);
        $this->yearLevel            = $this->getOpt(self::OPT_LONG_YEAR_LEVEL);
        $this->curriculum           = $this->getOpt(self::OPT_LONG_CURRICULUM);
        $this->reportDateOverride   = $this->getOpt(self::OPT_LONG_REPORT_DATE);
        $maxFailures                = $this->getOpt(self::OPT_LONG_MAX_FAILS);
        $jsDelay                    = $this->getOpt(self::OPT_LONG_JS_DELAY);
        $userStyleSheet             = $this->getOpt(self::OPT_LONG_USER_STYLE_SHEET);
        $roleTypeOverride           = $this->getOpt(self::OPT_LONG_ROLE_TYPE);

        if ($this->sessionHash) {
            $this->getLogger()->info('Command-line session hash supplied, validating...');
            $redis = \Alaress\Redis::getClientInstance();
            if (!$redis) {
                $this->getLogger()->alert("Unable to fetch redis client");
                $valid = false;
            } else {
                $redisData = $redis->get(\sprintf('%s%s', PipeManager::REDIS_SESSION_PREFIX, $this->sessionHash));
                if (null === $redisData) {
                    $this->getLogger()->alert("No redis data found for hash: {$this->sessionHash}");
                    $valid = false;
                } else {
                    // TODO This is too low-level and implementation specific.
                    // TODO It should be abstracted out.
                    \session_start();
                    \session_decode($redisData);
                    /** @var User $user */
                    $user     = $_SESSION['user'] ?? null;
                    $lastTime = $_SESSION['lasttime'] ?? null;
                    \session_unset();
                    if (!$user) {
                        $this->getLogger()->alert("No user object found in session data");
                        $valid = false;
                    } elseif(!$lastTime || time() - $lastTime > $this->getConfig()->get('session_length')) {
                        $this->getLogger()->alert("Session has expired");
                        $valid = false;
                    } elseif (!$user->isSuperuser()) {
                        $this->getLogger()->alert("Session user '{$user->getUsername()}' is not a superuser");
                        $valid = false;
                    } else {
                        $this->getLogger()->notice("Session user '{$user->getUsername()}' IS a superuser, user is authorised");
                    }
                }
            }
        }

        if ($this->curriculum && !in_array($this->curriculum, [self::CURRICULUM_IB, self::CURRICULUM_HSC], true)) {
            $this->getLogger()->alert("Invalid 'curriculum' option: '$this->curriculum'");
            $valid = false;
        }

        if ($this->reportDateOverride && false === \strtotime($this->reportDateOverride)) {
            $this->getLogger()->alert("Invalid 'report-date' option: '$this->reportDateOverride'");
            $valid = false;
        }

        if ($maxFailures === null) {
            $this->maxFailures = self::DEFAULT_MAX_FAILS;
        } elseif(!\is_numeric($maxFailures) || $maxFailures < 0) {
            $this->getLogger()->alert("Invalid 'max-failures' option: '$maxFailures'");
            $valid = false;
        } else {
            $this->maxFailures = (int)$maxFailures;
        }

        if ($jsDelay === null) {
            $this->jsDelay = self::DEFAULT_JS_DELAY;
        } elseif(!\is_numeric($jsDelay)) {
            $this->getLogger()->alert("Invalid 'js-delay' option: '$jsDelay'");
            $valid = false;
        } else {
            $this->jsDelay = (int)$jsDelay;
        }

        if ($userStyleSheet === null) {
            // this option is not specified
            $this->userStyleSheet = '';
        } else if (!\file_exists($userStyleSheet)) {
            $this->getLogger()->alert("Invalid 'user-style-sheet' option: '$userStyleSheet'");
            $valid = false;
        } else {
            $this->userStyleSheet = $userStyleSheet;
        }

        if ($roleTypeOverride === null || in_array($roleTypeOverride, Role::getRoleTypes(), true)) {
            $this->roleTypeOverride = $roleTypeOverride;
        } else {
            $this->getLogger()->alert("Invalid 'role-type' option: '$roleTypeOverride'");
            $valid = false;
        }


        /* Include Subjects requires Role Type override
         */
        if (!$this->roleTypeOverride && (bool)$this->getOpt(self::OPT_LONG_INCLUDE_SUBJECTS)) {
            $this->getLogger()->alert("A valid 'role-type' option must be set if using 'include-subjects'");
            $valid = false;
        }


        $this->getLogger()->debug("Command Line Options:");
        $this->getLogger()->debug("    sessionHash: {$this->sessionHash}");
        $this->getLogger()->debug("    username: {$this->username}");
        $this->getLogger()->debug("    studentExternalId: {$this->studentExternalId}");
        $this->getLogger()->debug("    yearLevel: {$this->yearLevel}");
        $this->getLogger()->debug("    curriculum: {$this->curriculum}");
        $this->getLogger()->debug("    max-fails: {$this->maxFailures}");
        $this->getLogger()->debug("    js-delay: {$this->jsDelay}");
        $this->getLogger()->debug("    report-date: {$this->reportDateOverride}");
        $this->getLogger()->debug("    user-style-sheet: {$this->userStyleSheet}");
        $this->getLogger()->debug("    role-type: {$this->roleTypeOverride}");

        return $valid;
    }


    /**
     * Returns the length of the largest user.id in the collection
     *
     * @param int[] $studentIds
     * @return int
     */
    private function calculateMaxIdLength(array $studentIds): int
    {
        $maxUserId = 0;
        foreach ($studentIds as $studentId) {
            $maxUserId = \max($maxUserId, $studentId);
        }
        return (int)\max(1, \ceil(\log10($maxUserId + 1)));
    }


    /**
     * Returns configured class codes to exclude from reporting
     *
     * @return null|string
     */
    private function getExcludedClassCodes(): ?string
    {
        // Trim and remove empty items
        $excluded = $this->getConfig()->get(Config::_TIGS_EXCLUDED_CLASS_CODES);
        $this->getLogger()->debug("Class code exclusions: " . $excluded);
        return $excluded;
    }


    /**
     * Returns the internal ids of all students for whom we are to print PDFs
     *
     * @return int[]
     * @throws \Alaress\Database\Predicate\InvalidColumnException
     * @throws \UnderflowException
     */
    private function getStudentIds(): array
    {
        $pb           = $this->userMapper->getPredicateBuilder();
        $messageParts = [];


        /* Year Levels are now joined objects, not scalars
         */
        $yearLevelMapper = YearLevel\Mapper::getInstance();
        $yearLevels = $yearLevelMapper->findByColumn('name', ['7', '8', '9', '10', '11', '12']);

        // Only students
        $pb->equals('g.group_type', Role::STUDENT);
        $messageParts[] = \sprintf("role type of '%s'", Role::STUDENT);

        // Years 7-12 (Cannot use BETWEEN as `user`.`year` is stored as a VARCHAR in database)
        $pb->in('u.year_id', $yearLevels->getPropertyValues('id'));
        $messageParts[] = "user.year between 7 and 12";

        // optional user.synergy_id filter
        if ($this->studentExternalId !== null) {
            $pb->equals('u.synergy_id', $this->studentExternalId);
            $messageParts[] = \sprintf("user.synergy_id of '%s'", $this->studentExternalId);
        } else {
            $pb->notNull('u.synergy_id');
            $messageParts[] = "a non-null external id";
        }

        // And are not deleted
        $pb->isNull('u.deleted_at');
        $messageParts[] = "not soft-deleted";

        // optional year level filter
        if ($this->yearLevel) {
            $specificYearLevel = $yearLevels->filterBy('name', $this->yearLevel)->first();

            $pb->equals('u.year_id', $specificYearLevel->getId());
            $messageParts[] = \sprintf("user.year of '%s'", $this->yearLevel);
        }

        $this->getLogger()->info("Fetching users from schoolbox with " . implode(' and ', $messageParts));
        $db = $this->userMapper->getDatabase();
        $whereClause = implode(' AND ', $pb->getWhere());

        $sql = "
            SELECT u.id
            FROM `user` u
            INNER JOIN lu_year_level lyl ON u.year_id = lyl.id
            JOIN `groups` g on u.group_id = g.id
            WHERE {$whereClause}
        ";

        $studentIds = $db->query($sql, $pb->getParams())->fetchAll(\PDO::FETCH_COLUMN);

        // Apply curriculum filter
        if ($this->curriculum) {
            $this->getLogger()->info("Filtering to students with curriculum: '{$this->curriculum}'");
            foreach ($studentIds as $idx => $studentId) {
                /** @var User $user */
                $user            = $this->userMapper->findByColumn('id', $studentId)->first();
                $externalStudent = $this->externalUser->getStudent($user->getExternalId());
                if (!$this->isRelevantCurriculum($externalStudent)) {
                    unset($studentIds[$idx]);
                }
            }
        }

        $this->getLogger()->info(\sprintf("%d students found", count($studentIds)));
        return $studentIds;
    }


    /**
     * Returns whether command line options indicate this student's curriculum should be included in report.
     *
     * @param array $externalStudent
     * @return bool
     */
    private function isRelevantCurriculum(array $externalStudent): bool
    {
        if (!$this->curriculum) {
            // No filter? All students are relevant.
            return true;
        }
        $isIb  = $externalStudent['StudentIBFlag'] ?? false;
        $isHsc = !$isIb;

        $ibOnly  = $this->curriculum === self::CURRICULUM_IB;
        $hscOnly = $this->curriculum === self::CURRICULUM_HSC;

        // A filter was specified, return whether the student matches the filter
        return (($isIb && $ibOnly) || ($isHsc && $hscOnly));
    }


//    /**
//     * Download each URL as it would be rendered for staff and checks for rendered page errors
//     *
//     * @param User   $student
//     * @param Uri[]  $urls
//     * @param string $sessionHash
//     */
//    private function detectRenderedErrors(User $student, array $urls, string $sessionHash): void
//    {
//        $this->getLogger()->info("Checking report for student {$student->getId()} for errors...");
//
//        $cookieJar = CookieJar::fromArray([PipeManager::PHP_SESSION_PREFIX => $sessionHash,], $this->fqdn);
//        $options   = ['cookies' => $cookieJar,];
//        foreach ($urls as $url) {
//            try {
//                $staffUrl = $url->withQuery('role-type=' . Role::STAFF);
//            } catch (InvalidArgumentException $e) {
//                $this->getLogger()->error("Unable to generate staff version of '$url'");
//                $this->getLogger()->error($e->getMessage());
//                $this->getLogger()->error($e->getTraceAsString());
//                continue;
//            }
//
//            $response = $this->guzzleClient->get($staffUrl, $options);
//            try {
//                $contents = $response->getBody()->getContents();
//                if (strpos($contents, Config::ERROR_DATA_ATTRIBUTE) !== false) {
//                    $this->getLogger()->error("'$staffUrl' contains an error.");
//                    $this->pageErrors[] = $staffUrl;
//                }
//            } catch (RuntimeException $e) {
//                $this->getLogger()->error("Unable to check '$staffUrl' for errors");
//                $this->getLogger()->error($e->getMessage());
//                $this->getLogger()->error($e->getTraceAsString());
//                continue;
//            }
//        }
//    }


    /**
     * Generates the set of URLs to include in a student's PDF report
     *
     * @param User  $student
     * @param array $externalStudent
     * @param null|string $excludedClassCodes
     * @return Uri[]
     * @throws \Aura\Router\Exception\RouteNotFound
     * @throws InvalidArgumentException
     * @throws \Schoolbox\Mapper\DefinitionException
     * @throws \UnexpectedValueException
     */
    private function generateUrlsForStudent(User $student, array $externalStudent, ?string $excludedClassCodes): array
    {
        $data                   = ['userId' => $student->getId(), 'reportDate' => $this->reportDateOverride,];
        $participationReportUrl = 'https://' . $this->fqdn . $this->router->generate('tigsParticipationReport.getReport', $data) . '?inline=1';
        if ($this->roleTypeOverride) {
            $participationReportUrl .= "&role-type={$this->roleTypeOverride}";
        }
        $this->getLogger()->debug("Adding url '$participationReportUrl' to list");
        $urls = [$participationReportUrl];

        /* Optionally include subject reports if specified
         */
        if (!(bool)$this->getOpt(self::OPT_LONG_INCLUDE_SUBJECTS)) {
            return $urls;
        }

        $classFolders     = $this->getClassFolders($student, $externalStudent, $excludedClassCodes, $this->roleTypeOverride);
        $queryArgs        = $this->roleTypeOverride ? "?role-type={$this->roleTypeOverride}" : '';

        foreach ($classFolders as $folder) {
            $data = [
                'userId'     => $student->getId(),
                'folderId'   => $folder->getId(),
                'reportDate' => $this->reportDateOverride,
            ];
            $subjectReportUrl = 'https://' . $this->fqdn . $this->router->generate('tigsSubjectReport.getReport', $data) . $queryArgs;
            $this->getLogger()->debug("Adding url '$subjectReportUrl' to list");
            $urls[] = $subjectReportUrl;
        }
        return $urls;
    }


    /**
     * Returns the set of folders a user is/was enrolled in, that are not excluded
     *
     * @param User        $student
     * @param array       $externalStudent
     * @param null|string $excludedClassCodes
     * @param string|null $roleTypeOverride
     * @return Collection|Folder\Model[]
     * @throws InvalidArgumentException
     * @throws \PDOException
     * @throws \UnexpectedValueException
     */
    private function getClassFolders(User $student, array $externalStudent, ?string $excludedClassCodes, ?string $roleTypeOverride): Collection
    {
        $attributes = new StudentAttributes($this->getConfig(), $student, $externalStudent['StudentIBFlag'], $this->reportDateOverride);

        /* Throw in a null $viewer, because cli has no idea about "logged in user"
         * Report generation is wholly contingent on the $roleTypeOverride in the cli tool context
         * but to fulfil method signature, it requires a param
         */
        return $this->folderFetcher->findForStudent($student, $attributes, $this->reportDateOverride, $excludedClassCodes, null, $roleTypeOverride);
    }


    /**
     * Prints a help message, always to STDOUT, regardless of Kernel's output file option
     */
    protected function printHelp(): void
    {
        $file            = $this->getToolFilename();
        $defaultMaxFails = self::DEFAULT_MAX_FAILS;
        $defaultJsDelay  = self::DEFAULT_JS_DELAY;
        $roleTypes       = implode(', ', Role::getRoleTypes());

        $green  = self::COLOUR_GREEN;
        $white  = self::COLOUR_WHITE;
        $yellow = self::COLOUR_YELLOW;

        $help = "$white

NAME:
    TIGS PDF Batch Printer - CLI wrapper to generate a PDF Archive of Participation & Subject Reports

SYNOPSIS:
    $green{$file} {$yellow}[OPTION]...$white

DESCRIPTION
    By default, writes archive to STDOUT (1>) and writes log to STDERR (2>)

    This can be changed by providing an output and/or log file. If provided, tool will not write to STDOUT or STDERR, respectively.

    To authenticate the app, you can either provide a session hash, or a username + password. If the latter, this app will prompt you to
    enter the password on STDIN and STDOUT.

    $yellow-h, --help$white
        Print this help and exit

    $yellow-o=OUT_FILE$white
        Filename to write archive to, eg '-o=output.zip'

    $yellow-l=LOG_FILE$white
        Filename to append logs to, eg '-l=error.log'

    $yellow-v[v[v]]$white
        Log verbosity. Max 3 levels, eg -vvv

    $yellow--hash=HASH$white
        A valid session hash, you can extract this from a browser session

    $yellow--username=USERNAME$white
        A username to run the tool as

    $yellow--student-id=ID$white
        Just generate the report for this student. (Value is SIS id, not Schoolbox id)

    $yellow--year-level=YEAR$white
        Just generate reports for students in this year level

    $yellow--curriculum=[ib|hsc]$white
        Just generate reports for students enrolled in this curriculum

    $yellow--max-fails=COUNT$white
         If the tool fails for this many students in a row, exit immediately.
         Defaults to $defaultMaxFails.

    $yellow--js-delay=COUNT$white
         Number of milliseconds to wait before printing a page to allow javascript to execute.
         Defaults to $defaultJsDelay.

    $yellow--report-date=REPORT_DATE$white
         Attempt to produce a report for some specific point in time.
         Supports any format accepted by PHP's strtotime()

    $yellow--user-style-sheet=STYLE_FILE$white
         Filename of a style sheet to load with every page.

    $yellow--role-type=ROLE_TYPE$white
         Print Subject Reports as they would appear to users with this role type.
         Must be one of [{$roleTypes}]

     $yellow--include-subjects$white
         If provided, include the Subject Reports. Requires setting the --role-type option.

EXAMPLES:
 * Write log to terminal, output to archive.zip
      $file 1> archive.zip
      $file -o archive.zip
 * Append log to export.log, output to archive.zip
      $file 2>> export.log 1> archive.zip
      $file -l export.log -o archive.zip
";
        \fwrite(STDOUT, $help);
    }


//    /**
//     * Prints a summary of errors that were detected while generating reports
//     */
//    private function printPageErrorSummary(): void
//    {
//        $countErrors = count($this->pageErrors);
//        if (!$countErrors) {
//            $this->getLogger()->info("No errors were detected while generating reports");
//        } else {
//            $errorFragment = $countErrors === 1 ? 'error was' : 'errors were';
//            $this->getLogger()->error(sprintf("%d $errorFragment detected while generating reports", count($this->pageErrors)));
//        }
//        foreach ($this->pageErrors as $staffUrlWithError) {
//            $this->getLogger()->error($staffUrlWithError);
//        }
//    }
}

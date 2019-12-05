<?php
/**
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */
declare(strict_types=1);

namespace Custom\Tigs\Summative;

use Aura\Router\Router;
use function count;
use Custom\Tigs\Summative\ParticipationReport\FolderFetcher;
use External_UserInterface;
use GuzzleHttp\Cookie\CookieJar;
use GuzzleHttp\Psr7\Uri;
use function implode;
use function in_array;
use InvalidArgumentException;
use Knp\Snappy\Pdf;
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
use ZipArchive;

/**
 * A CLI App for batch printing PDFs to an archive
 */
class CliPdfGenerator extends CliTool\App
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

    private const EXIT_STATUS_AUTHENTICATION_FAILURE = 100;
    private const EXIT_STATUS_AUTHORISATION_FAILURE  = 101;

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

        $this->execute();
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
     * Start doing the work.
     *
     * @throws RuntimeException
     * @throws \Alaress\Database\Predicate\InvalidColumnException
     * @throws \Aura\Router\Exception\RouteNotFound
     * @throws \GuzzleHttp\Exception\GuzzleException
     * @throws InvalidArgumentException
     * @throws \Schoolbox\Mapper\DefinitionException
     * @throws \Schoolbox\Mapper\Exception
     * @throws \Schoolbox\Mapper\Exception\Deleted
     * @throws \UnderflowException
     */
    private function execute(): void
    {
        $this->guzzleClient  = new Client();
        $excludedClassCodes  = $this->getExcludedClassCodes();
        $sessionHash         = $this->getSessionHash();
        $studentsIds         = $this->getStudentIds();
        $maxIdLength         = $this->calculateMaxIdLength($studentsIds);
        $tempDir             = $this->createTempDir();
        $pdfFilenames        = [];
        $consecutiveFailures = 0;
        $index               = 1;
        $count               = count($studentsIds);

        foreach ($studentsIds as $studentId) {
            $this->getLogger()->notice(\sprintf("Processing student %s of %s", $index++, $count));
            if($consecutiveFailures >= $this->maxFailures){
                $this->getLogger()->error("$consecutiveFailures consecutive failures, giving up...");
                break;
            }
            $student         = $this->userMapper->findById($studentId);
            $externalStudent = $this->externalUser->getStudent($student->getExternalId());
            $message         = \sprintf("Printing PDF for student. (id: %{$maxIdLength}d), (externalId: %-s)", $studentId, $student->getExternalId());
            $this->getLogger()->notice($message);
            try {
                $pdfFilenames[]      = $this->generatePdf($student, $externalStudent, $sessionHash, $excludedClassCodes, $tempDir);
                $consecutiveFailures = 0;
            } catch (RuntimeException $e) {
                $this->getLogger()->warning($e->getMessage());
                $consecutiveFailures++;
                continue;
            }
        }

        $this->createZipArchive($pdfFilenames, $tempDir);
        $this->cleanUpTempFiles($pdfFilenames, $tempDir);
        $this->printPageErrorSummary();
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
     * Create a temp directory to store files and return its name
     *
     * @return string
     * @throws RuntimeException If unable to create the temp directory
     */
    private function createTempDir(): string
    {
        $tempDir = $this->getConfig()->get('zip_dir') . '/' . \time();
        if (!\mkdir($tempDir) && !\is_dir($tempDir)) {
            throw new \RuntimeException(sprintf('Directory "%s" was not created', $tempDir));
        }
        return $tempDir;
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
     * Gets a session hash to be used to enable web requests
     *
     * @return string
     * @throws \GuzzleHttp\Exception\GuzzleException
     * @throws InvalidArgumentException
     */
    private function getSessionHash(): string
    {
        // Did user provide a session hash on the command line?
        if ($this->sessionHash) {
            $this->getLogger()->debug('Using hash provided on command line');
            return $this->sessionHash;
        }

        $username = $this->getUsername();

        // Use web login form to support multiple identity providers
        $sessionHash = $this->generateSessionHashViaLoginForm($username);

        // But ensure user is a superuser
        $this->authoriseUser($username);

        return $sessionHash;
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

        // Only students
        $pb->equals('g.group_type', Role::STUDENT);
        $messageParts[] = \sprintf("role type of '%s'", Role::STUDENT);

        // Years 7-12 (Cannot use BETWEEN as `user`.`year` is stored as a VARCHAR in database)
        $pb->in('u.year', ['7', '8', '9', '10', '11', '12']);
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
            $pb->equals('u.year', $this->yearLevel);
            $messageParts[] = \sprintf("user.year of '%s'", $this->yearLevel);
        }

        $this->getLogger()->info("Fetching users from schoolbox with " . implode(' and ', $messageParts));
        $db = $this->userMapper->getDatabase();
        $whereClause = implode(' AND ', $pb->getWhere());

        $sql = "
            SELECT u.id
            FROM `user` u
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


    /**
     * Generate a PDF report for a single user and returns the filename
     *
     * @param User     $student
     * @param array    $externalStudent
     * @param string   $sessionHash
     * @param null|string $excludedClassCodes
     * @param string   $tempDir
     * @return string The filename of the PDF
     * @throws RuntimeException If unable to generate a PDF Report for this student
     * @throws InvalidArgumentException
     * @throws \Aura\Router\Exception\RouteNotFound
     * @throws \Schoolbox\Mapper\DefinitionException
     * @throws \UnexpectedValueException
     */
    private function generatePdf(User $student, array $externalStudent, string $sessionHash, ?string $excludedClassCodes, string $tempDir): string
    {
        $filename = "{$tempDir}/{$student->getExternalId()}.pdf";
        $this->getLogger()->debug("Attempting to create temp PDF file: $filename");
        $fp = \fopen($filename, 'wb');
        if (!$fp) {
            throw new RuntimeException("Unable to create file: '{$filename}'");
        }

        $urls = $this->generateUrlsForStudent($student, $externalStudent, $excludedClassCodes);
        $this->detectRenderedErrors($student, $urls, $sessionHash);
        $this->getLogger()->info("Generating PDF...");
        $javascriptDelayMs = $this->jsDelay;
        $userStyleSheet    = $this->userStyleSheet;
        $maxAttempts       = 10;
        $attempts          = 0;
        $previousException = null;
        while (true) {
            if ($attempts++ >= $maxAttempts) {
                \unlink($filename);
                throw new RuntimeException("Unable to generate report after $maxAttempts attempts, skipping", 0, $previousException);
            }
            try {
                $this->snapshotUrlsToPdf($urls, $sessionHash, $fp, $javascriptDelayMs, $userStyleSheet);
                break;
            } catch (RuntimeException $e) {
                $this->getLogger()->warning($e->getMessage());
                $this->getLogger()->warning($e->getTraceAsString());
                $javascriptDelayMs *= 2;
                $previousException = $e;
            }
        }

        $this->getLogger()->info("Wrote PDF to '$filename'");
        return $filename;
    }


    /**
     * Download each URL as it would be rendered for staff and checks for rendered page errors
     *
     * @param User   $student
     * @param Uri[]  $urls
     * @param string $sessionHash
     */
    private function detectRenderedErrors(User $student, array $urls, string $sessionHash): void
    {
        $this->getLogger()->info("Checking report for student {$student->getId()} for errors...");

        $cookieJar = CookieJar::fromArray([PipeManager::PHP_SESSION_PREFIX => $sessionHash,], $this->fqdn);
        $options   = ['cookies' => $cookieJar,];
        foreach ($urls as $url) {
            try {
                $staffUrl = $url->withQuery('role-type=' . Role::STAFF);
            } catch (InvalidArgumentException $e) {
                $this->getLogger()->error("Unable to generate staff version of '$url'");
                $this->getLogger()->error($e->getMessage());
                $this->getLogger()->error($e->getTraceAsString());
                continue;
            }

            $response = $this->guzzleClient->get($staffUrl, $options);
            try {
                $contents = $response->getBody()->getContents();
                if (strpos($contents, Config::ERROR_DATA_ATTRIBUTE) !== false) {
                    $this->getLogger()->error("'$staffUrl' contains an error.");
                    $this->pageErrors[] = $staffUrl;
                }
            } catch (RuntimeException $e) {
                $this->getLogger()->error("Unable to check '$staffUrl' for errors");
                $this->getLogger()->error($e->getMessage());
                $this->getLogger()->error($e->getTraceAsString());
                continue;
            }
        }
    }


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
        $urls = [new Uri($participationReportUrl)];

        /* Optionally include subject reports if specified
         */
        if (!(bool)$this->getOpt(self::OPT_LONG_INCLUDE_SUBJECTS)) {
            return $urls;
        }

        $classFolders     = $this->getClassFolders($student, $externalStudent, $excludedClassCodes);
        $queryArgs        = $this->roleTypeOverride ? "?role-type={$this->roleTypeOverride}" : '';

        foreach ($classFolders as $folder) {
            $data = [
                'userId'     => $student->getId(),
                'folderId'   => $folder->getId(),
                'reportDate' => $this->reportDateOverride,
            ];
            $subjectReportUrl = 'https://' . $this->fqdn . $this->router->generate('tigsSubjectReport.getReport', $data) . $queryArgs;
            $this->getLogger()->debug("Adding url '$subjectReportUrl' to list");
            $urls[] = new Uri($subjectReportUrl);
        }
        return $urls;
    }


    /**
     * Returns the set of folders a user is/was enrolled in, that are not excluded
     *
     * @param User  $student
     * @param array $externalStudent
     * @param null|string $excludedClassCodes
     * @return Collection|Folder\Model[]
     * @throws InvalidArgumentException
     * @throws \Schoolbox\Mapper\DefinitionException
     * @throws \UnexpectedValueException
     */
    private function getClassFolders(User $student, array $externalStudent, ?string $excludedClassCodes): Collection
    {
        $attributes = new StudentAttributes($this->getConfig(), $student, $externalStudent['StudentIBFlag'], $this->reportDateOverride);
        return $this->folderFetcher->findForStudent($student, $attributes, $this->reportDateOverride, $excludedClassCodes);
    }


    /** @noinspection PhpDocRedundantThrowsInspection (the call to $snappy->getOutput() can throw a RuntimeException)) */
    /**
     * Attempts to snapshot a set of URLs as a PDF, and return the PDF contents
     *
     * @param Uri[]    $urls
     * @param string   $sessionHash
     * @param resource $fp The temp file to write the PDF to
     * @param int      $javascriptDelayMs
     * @param string   $userStyleSheet
     * @throws RuntimeException If unable to generate the PDF contents
     * @throws InvalidArgumentException
     */
    private function snapshotUrlsToPdf(array $urls, string $sessionHash, $fp, int $javascriptDelayMs, string $userStyleSheet): void
    {
        $this->getLogger()->debug("Setting 'javascript-delay' to $javascriptDelayMs ms");

        // @see https://wkhtmltopdf.org/usage/wkhtmltopdf.txt for list of options
        $snappy = new Pdf('/usr/local/bin/wkhtmltopdf');
        $snappy->setTimeout(120); // TODO expose as CLI option
        $snappy->setOption('cookie', [PipeManager::PHP_SESSION_PREFIX => $sessionHash]);
        $snappy->setOption('orientation', 'Landscape');
        $snappy->setOption('disable-external-links', true);
        $snappy->setOption('viewport-size', 1024);
        $snappy->setOption('javascript-delay', $javascriptDelayMs);
        if ($userStyleSheet !== '') {
            $this->getLogger()->debug("Setting 'user-style-sheet' to $userStyleSheet");
            $snappy->setOption('user-style-sheet', $userStyleSheet);
        }

        \fwrite($fp, $snappy->getOutput($urls));
    }


    /**
     * Create a single ZIP archive of all the PDF documents
     *
     * @param string[] $pdfFilenames
     * @param string   $tempDir
     */
    private function createZipArchive(array $pdfFilenames, string $tempDir): void
    {
        $zipFilename = \tempnam($tempDir, 'zip');
        $zipArchive  = new ZipArchive();
        $this->getLogger()->debug("Creating temp zip archive: $zipFilename");
        $zipArchive->open($zipFilename, ZipArchive::CREATE);

        // To avoid exceeding open file limit, may need to batch zip
        $openFileLimit = (int)\exec('ulimit -n');
        $batchSize     = \round($openFileLimit - 20, -2); // buffer of 20, round to nearest 100
        $this->getLogger()->debug(sprintf('Open file limit: %d, setting batch size to %d', $openFileLimit, $batchSize));
        $batchIndex = 1;
        $batches    = \ceil(count($pdfFilenames) / $batchSize);
        foreach ($pdfFilenames as $pdfFilename) {
            if ($batchIndex++ % $batchSize === 0) {
                $batchCount = (int)($batchIndex / $batchSize);
                $this->getLogger()->info("Writing zip batch $batchCount/$batches...");
                $zipArchive->close();
                $zipArchive->open($zipFilename);
            }
            $this->getLogger()->debug("Adding PDF file to zip archive: $pdfFilename");
            $zipArchive->addFile($pdfFilename, \basename($pdfFilename));
        }
        $this->getLogger()->info("Writing zip batch $batches/$batches...");
        $zipArchive->close();

        $this->getLogger()->debug("Writing zip archive to output stream");
        if (false === \stream_copy_to_stream(\fopen($zipFilename, 'rb'), $this->getOutStream())) {
            $this->getLogger()->error('Unable to write zip archive to output stream');
        }

        $this->getLogger()->debug("Deleting zip archive: $zipFilename");
        \unlink($zipFilename);
    }


    /**
     * Explicitly deletes temporary files
     *
     * @param array  $pdfFilenames
     * @param string $tempDir
     */
    private function cleanUpTempFiles(array $pdfFilenames, string $tempDir): void
    {
        $this->getLogger()->debug("Cleaning up temporary files");
        foreach ($pdfFilenames as $pdfFilename) {
            $this->getLogger()->debug("Deleting $pdfFilename");
            \unlink($pdfFilename);
        }

        $this->getLogger()->debug("Deleting temp directory $tempDir");
        \rmdir($tempDir);
    }


    /**
     * Return a username, either from command line options, or by prompting the user
     *
     * @return string
     */
    private function getUsername(): string
    {
        // Revert to username and password
        if ($this->username) {
            $this->getLogger()->debug('Using username provided on command line');
            return $this->username;
        }
        $username = null;
        while (true) {
            $this->getLogger()->debug("Requesting username");
            $username = \trim($this->readLine('Please provide a schoolbox username: '));
            // TODO use a regex
            if ($username) {
                $this->getLogger()->debug("Username set to '$username'");
                break;
            }
            $this->getLogger()->warning("Username '$username' appears invalid");
        }
        return $username;
    }


    /**
     * Attempt to login via the web form, and return the generated session hash
     *
     * @param string $username
     * @return string
     * @throws \GuzzleHttp\Exception\GuzzleException
     * @throws InvalidArgumentException
     */
    private function generateSessionHashViaLoginForm(string $username): string
    {
        $attempts    = 0;
        $maxAttempts = 3;
        $sessionHash = '';
        while (true) {
            $attempts++;
            if ($attempts > $maxAttempts) {
                $this->getLogger()->warning("Login failed");
                exit(self::EXIT_STATUS_AUTHENTICATION_FAILURE);
            }
            $remaining = $maxAttempts - $attempts + 1;
            $this->getLogger()->debug("Requesting password, ($remaining attempts remaining)");
            $password = $this->readPassword("Please provide your password ($remaining attempts remaining): ");
            // TODO use regex
            if ($password) {
                $this->getLogger()->info("Password provided, attempting login");
                $sessionHash = $this->attemptLogin($username, $password);
                if ($sessionHash) {
                    break;
                }
            }
            $this->getLogger()->warning("Password appears invalid");
        }
        return $sessionHash;
    }


    /**
     * Attempt to login. Returns session hash on success, empty string on failure
     *
     * @param string $username
     * @param string $password
     * @return string
     * @throws \GuzzleHttp\Exception\GuzzleException
     * @throws InvalidArgumentException
     */
    private function attemptLogin(string $username, string $password): string
    {
        $url = "https://{$this->fqdn}/login";

        $headers  = ['Origin' => $this->fqdn];
        $postData = [
            'page'     => '/login/',
            'username' => $username,
            'password' => $password,
            'Submit'   => 'Sign in',
        ];

        $options = [
            'headers'         => $headers,
            'form_params'     => $postData,
            'allow_redirects' => false,
        ];

        $response = (new Client())->request('POST', $url, $options);

        if (303 !== $response->getStatusCode()) {
            $this->getLogger()->warning("Login failure. Status code: {$response->getStatusCode()}");
            return '';
        }

        $cookieName = PipeManager::PHP_SESSION_PREFIX;
        foreach ($response->getHeader('Set-Cookie') as $cookieString) {
            if (preg_match("/^{$cookieName}=([a-z0-9]+);/", $cookieString, $matches)) {
                $this->getLogger()->info("Session cookie found");
                return $matches[1];
            }
        }
        return '';
    }


    /**
     * Checks that the specified user is a superuser
     *
     * @param string $username
     * @return bool
     */
    private function authoriseUser(string $username): bool
    {
        $user = $this->userMapper->findByUsername($username);
        if ($user && $user->isSuperuser()) {
            $this->getLogger()->debug("User '$username' authorised to print reports");
            return true;
        }
        $this->getLogger()->error("User '$username' is not authorised to print reports, exiting");
        exit(self::EXIT_STATUS_AUTHORISATION_FAILURE);
    }


    /**
     * Prints a help message, always to STDOUT, regardless of Kernel's output file option
     */
    private function printHelp(): void
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


    /**
     * Prints a summary of errors that were detected while generating reports
     */
    private function printPageErrorSummary(): void
    {
        $countErrors = count($this->pageErrors);
        if (!$countErrors) {
            $this->getLogger()->info("No errors were detected while generating reports");
        } else {
            $errorFragment = $countErrors === 1 ? 'error was' : 'errors were';
            $this->getLogger()->error(sprintf("%d $errorFragment detected while generating reports", count($this->pageErrors)));
        }
        foreach ($this->pageErrors as $staffUrlWithError) {
            $this->getLogger()->error($staffUrlWithError);
        }
    }
}

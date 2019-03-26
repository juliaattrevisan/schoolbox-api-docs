<?php
/**
 * @copyright   Copyright 2019, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */
declare(strict_types=1);

namespace Custom\Tigs\Summative;

use Aura\Router\Router;
use External_UserInterface;
use Knp\Snappy\Pdf;
use RuntimeException;
use Schoolbox\Collection;
use Schoolbox\Resource\Folder;
use Schoolbox\Application\CliKernelAbstract;
use Schoolbox\Application\CliTool;
use Schoolbox\GenericCollection;
use Schoolbox\MapperFactory;
use Schoolbox\Realtime\Pipe\PipeManager;
use Schoolbox\Role;
use Schoolbox\UserMapper;
use GuzzleHttp\Client;
use Schoolbox\User;
use ZipArchive;
use Schoolbox\Assessment\Term;

/**
 * A CLI App for batch printing PDFs to an archive
 */
class CliPdfGenerator extends CliTool\App
{
    // This tools CLI options
    private const OPT_SHORT_HELP      = 'h';            // print help
    private const OPT_LONG_HELP       = 'help';         // print help
    private const OPT_LONG_HASH       = 'hash:';        // session hash
    private const OPT_LONG_USERNAME   = 'username:';    // username
    private const OPT_LONG_STUDENT_ID = 'student-id:';  // student-id
    private const OPT_LONG_YEAR_LEVEL = 'year-level:';  // year-level
    private const OPT_LONG_CURRICULUM = 'curriculum:';  // curriculum
    private const OPT_LONG_MAX_FAILS  = 'max-fails:';   // Number of consecutive fails after which to abort the script's execution.
    private const OPT_LONG_JS_DELAY   = 'js-delay:';    // How many milliseconds to let JS run before attempting to print page
    private const OPT_LONG_NOW        = 'now:';         // to simulate time

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
    private $studentId;

    /** @var string|null */
    private $yearLevel;

    /** @var string|null */
    private $curriculum;

    /** @var null|string */
    private $now;

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
            self::OPT_LONG_NOW,
        ];
    }


    /**
     * @inheritdoc
     * @throws RuntimeException
     * @throws \Alaress\Database\Predicate\InvalidColumnException
     * @throws \Aura\Di\Exception\ServiceNotFound
     * @throws \GuzzleHttp\Exception\GuzzleException
     * @throws \InvalidArgumentException
     * @throws \UnderflowException
     * @throws \Aura\Router\Exception\RouteNotFound
     * @throws \Schoolbox\Mapper\DefinitionException
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
        $mapperFactory      = $this->getDi()->get('mapperFactory');
        $this->userMapper   = $mapperFactory->get(UserMapper::class);
        $this->folderMapper = $mapperFactory->get(Folder\Mapper::class);
        $this->externalUser = $di->get('externalUser');
        $this->fqdn         = \trim("{$this->getConfig()->get('site_hostname')}.{$this->getConfig()->get('site_domain')}");
        $this->router       = $di->get('router');

        $this->execute();
    }


    /**
     * Parses options and returns whether all options are valid
     *
     * @return bool
     */
    private function parseAppOptions(): bool
    {
        $valid             = true;
        $this->sessionHash = $this->getOpt(self::OPT_LONG_HASH);
        $this->username    = $this->getOpt(self::OPT_LONG_USERNAME);
        $this->studentId   = $this->getOpt(self::OPT_LONG_STUDENT_ID);
        $this->yearLevel   = $this->getOpt(self::OPT_LONG_YEAR_LEVEL);
        $this->curriculum  = $this->getOpt(self::OPT_LONG_CURRICULUM);
        $this->now         = $this->getOpt(self::OPT_LONG_NOW);
        $maxFailures       = $this->getOpt(self::OPT_LONG_MAX_FAILS);
        $jsDelay           = $this->getOpt(self::OPT_LONG_JS_DELAY);

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
                    \session_start();
                    \session_decode($redisData);
                    /** @var User $user */
                    $user = $_SESSION['user'] ?? null;
                    \session_unset();
                    if (!$user) {
                        $this->getLogger()->alert("No user object found in session data");
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

        if ($this->curriculum && !\in_array($this->curriculum, [self::CURRICULUM_IB, self::CURRICULUM_HSC], true)) {
            $this->getLogger()->alert("Invalid 'curriculum' option: '$this->curriculum'");
            $valid = false;
        }

        if ($this->now && false === strtotime($this->now)) {
            $this->getLogger()->alert("Invalid 'now' option: '$this->now'");
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

        $this->getLogger()->debug("Command Line Options:");
        $this->getLogger()->debug("    sessionHash: {$this->sessionHash}");
        $this->getLogger()->debug("    username: {$this->username}");
        $this->getLogger()->debug("    studentId: {$this->studentId}");
        $this->getLogger()->debug("    yearLevel: {$this->yearLevel}");
        $this->getLogger()->debug("    curriculum: {$this->curriculum}");
        $this->getLogger()->debug("    max-fails: {$this->maxFailures}");
        $this->getLogger()->debug("    js-delay: {$this->jsDelay}");
        $this->getLogger()->debug("    now: {$this->now}");

        return $valid;
    }


    /**
     * Start doing the work.
     *
     * @throws RuntimeException
     * @throws \Alaress\Database\Predicate\InvalidColumnException
     * @throws \GuzzleHttp\Exception\GuzzleException
     * @throws \InvalidArgumentException
     * @throws \UnderflowException
     * @throws \Aura\Router\Exception\RouteNotFound
     * @throws \Schoolbox\Mapper\DefinitionException
     */
    private function execute(): void
    {
        $excludedClassCodes  = $this->getExcludedClassCodes();
        $sessionHash         = $this->getSessionHash();
        $students            = $this->getStudents();
        $maxIdLength         = $this->calculateMaxIdLength($students);
        $tempDir             = $this->createTempDir();
        $pdfFilenames        = [];
        $consecutiveFailures = 0;
        $index               = 1;
        $count               = $students->count();

        foreach ($students as $student) {
            $this->getLogger()->notice(\sprintf("Processing student %s of %s", $index++, $count));
            if($consecutiveFailures >= $this->maxFailures){
                $this->getLogger()->error("$consecutiveFailures consecutive failures, giving up...");
                break;
            }
            if (!$student->getExternalId()) {
                $this->getLogger()->info("Skipping user (id: {$student->getId()}). External ID is empty");
                continue;
            }
            $externalStudent = $this->externalUser->getStudent($student->getExternalId());
            if (!$this->isRelevantCurriculum($externalStudent)) {
                $this->getLogger()->info("Skipping user (id: {$student->getId()}). Irrelevant curriculum type");
                continue;
            }
            $message = \sprintf(
                "Printing PDF for student. (id: %{$maxIdLength}d), (externalId: %-s)",
                $student->getId(),
                $student->getExternalId()
            );
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
    }


    /**
     * Returns the length of the largest user.id in the collection
     *
     * @param GenericCollection|User[] $students
     * @return int
     */
    private function calculateMaxIdLength(GenericCollection $students): int
    {
        $maxUserId = 0;
        foreach ($students as $student) {
            $maxUserId = \max($maxUserId, $student->getId());
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
     * Returns a set of class codes to exclude from reporting
     *
     * @return string[]
     */
    private function getExcludedClassCodes(): array
    {
        // Trim and remove empty items
        $excluded        = \array_filter(\array_map('\trim', \explode(',', $this->getConfig()->get(Config::_TIGS_EXCLUDED_CLASS_CODES) ?? '')));
        $singleQuoteWrap = function (string $code): string {
            return \sprintf("'%s'", $code);
        };
        $this->getLogger()->debug("Class code exclusions: " . \implode(', ', \array_map($singleQuoteWrap, $excluded)));
        return $excluded;
    }


    /**
     * Gets a session hash to be used to enable web requests
     *
     * @return string
     * @throws \GuzzleHttp\Exception\GuzzleException
     * @throws \InvalidArgumentException
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
     * Returns the external ids of all students for whom we are to print PDFs
     *
     * @return User[]|GenericCollection
     * @throws \Alaress\Database\Predicate\InvalidColumnException
     * @throws \UnderflowException
     */
    private function getStudents(): GenericCollection
    {
        $message = \sprintf("Fetching users from schoolbox with role type of '%s' and user.year between 7 and 12", Role::STUDENT);
        $pb      = $this->userMapper->getPredicateBuilder()
                                    ->equals('g.group_type', Role::STUDENT)
                                    ->in('year', ['7', '8', '9', '10', '11', '12']); // Cannot use BETWEEN as this is stored as a VARCHAR in database

        if ($this->studentId) {
            $pb->equals('id', $this->studentId);
            $message .= \sprintf(" and user.id of '%d'", $this->studentId);
        }

        if ($this->yearLevel) {
            $pb->equals('year', $this->yearLevel);
            $message .= \sprintf(" and user.year of '%s'", $this->yearLevel);
        }
        $this->getLogger()->info($message);
        $students = $this->userMapper->findByPredicates($pb);
        $this->getLogger()->info("{$students->count()} students found");
        return $students;
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
     * @param string[] $excludedClassCodes
     * @param string   $tempDir
     * @return string The filename of the PDF
     * @throws RuntimeException If unable to generate a PDF Report for this student
     * @throws \InvalidArgumentException
     * @throws \Aura\Router\Exception\RouteNotFound
     * @throws \Schoolbox\Mapper\DefinitionException
     * @throws \UnexpectedValueException
     */
    private function generatePdf(User $student, array $externalStudent, string $sessionHash, array $excludedClassCodes, string $tempDir): string
    {
        $urls = $this->generateUrlsForStudent($student, $externalStudent, $excludedClassCodes);

        $this->getLogger()->info("Generating PDF...");
        $javascriptDelayMs = $this->jsDelay;
        $maxAttempts       = 10;
        $attempts          = 0;
        $output            = null;
        $previous          = null;
        while (!$output) {
            if ($attempts++ >= $maxAttempts) {
                throw new RuntimeException("Unable to generate report after $maxAttempts attempts, skipping", 0, $previous);
            }
            try {
                $output = $this->snapshotUrlsToPdf($urls, $sessionHash, $javascriptDelayMs);
            } catch (RuntimeException $e) {
                $this->getLogger()->warning($e->getMessage());
                $this->getLogger()->warning($e->getTraceAsString());
                $javascriptDelayMs *= 2;
                $previous          = $e;
            }
        }
        $filename = "{$tempDir}/{$student->getExternalId()}.pdf";
        $fp       = \fopen($filename, 'wb');
        if (!$fp) {
            throw new RuntimeException("Unable to create file: '{$filename}'");
        }
        \fwrite($fp, $output);
        $this->getLogger()->info("Wrote PDF to '$filename'");
        return $filename;
    }


    /**
     * Generates the set of URLs to include in a student's PDF report
     *
     * @param User  $student
     * @param array $externalStudent
     * @param array $excludedClassCodes
     * @return string[]
     * @throws \Aura\Router\Exception\RouteNotFound
     * @throws \InvalidArgumentException
     * @throws \Schoolbox\Mapper\DefinitionException
     * @throws \UnexpectedValueException
     */
    private function generateUrlsForStudent(User $student, array $externalStudent, array $excludedClassCodes): array
    {
        $data                   = ['userId' => $student->getId(), 'now' => $this->now,];
        $participationReportUrl = 'https://' . $this->fqdn . $this->router->generate('tigsParticipationReport.getReport', $data);
        $this->getLogger()->debug("Adding url '$participationReportUrl' to list");
        $urls = [$participationReportUrl];

        $classFolders     = $this->getClassFolders($student, $externalStudent);
        $filteredClasses  = $this->filterClasses($classFolders, $excludedClassCodes);

        foreach ($filteredClasses as $folder) {
            $data = [
                'userId'   => $student->getId(),
                'folderId' => $folder->getId(),
                'now'      => $this->now,
            ];
            $subjectReportUrl = 'https://' . $this->fqdn . $this->router->generate('tigsSubjectReport.getReport', $data);
            $this->getLogger()->debug("Adding url '$subjectReportUrl' to list");
            $urls[] = $subjectReportUrl;
        }
        return $urls;
    }


    /**
     * @param User  $student
     * @param array $externalStudent
     * @return Collection
     * @throws \InvalidArgumentException
     * @throws \Schoolbox\Mapper\DefinitionException
     * @throws \UnexpectedValueException
     */
    private function getClassFolders(User $student, array $externalStudent): Collection
    {
        if (null === $this->now) {
            return $this->folderMapper->findAllBySubjectList($this->externalUser->getSubjectList($student));
        }
        $attributes    = new StudentAttributes($student, $externalStudent['StudentIBFlag'], $this->now);
        $term          = (new Term\Model())
            ->setStart($attributes->getCurriculumSemesterStart())
            ->setDisplayFinish($attributes->getCurriculumSemesterFinish())
            ->setActualFinish($attributes->getCurriculumSemesterFinish());
        $withResponses = $this->folderMapper->findFoldersWithResponses($student, $term);
        $withFeedback  = $this->folderMapper->findFoldersWithFeedbackToStudent($student, $term);

        return $this->folderMapper->createCollection()->mergeUnique($withResponses, 'id')->mergeUnique($withFeedback, 'id');
    }


    /** @noinspection PhpDocRedundantThrowsInspection (the call to $snappy->getOutput() can throw) */
    /**
     * Attempts to snapshot a set of URLs as a PDF, and return the PDF contents
     *
     * @param array  $urls
     * @param string $sessionHash
     * @param int    $javascriptDelayMs
     * @return string
     * @throws RuntimeException If unable to generate the PDF contents
     * @throws \InvalidArgumentException
     */
    private function snapshotUrlsToPdf(array $urls, string $sessionHash, int $javascriptDelayMs): string
    {
        $this->getLogger()->debug("Setting 'javascript-delay' to $javascriptDelayMs ms");

        // @see https://wkhtmltopdf.org/usage/wkhtmltopdf.txt
        $snappy = new Pdf('/usr/local/bin/wkhtmltopdf');
        $snappy->setOption('cookie', [PipeManager::PHP_SESSION_PREFIX => $sessionHash]);
        $snappy->setOption('orientation', 'Landscape');
        $snappy->setOption('javascript-delay', $javascriptDelayMs);

        return $snappy->getOutput($urls);
    }


    /**
     * Returns a new collection with any excluded classes removed
     *
     * @param Collection|Folder\Model[] $classFolders
     * @param string[]                  $excludedClassCodes
     * @return Collection|Folder\Model[]
     */
    private function filterClasses(Collection $classFolders, array $excludedClassCodes): Collection
    {
        $filtered = $classFolders->emptyClone();
        foreach ($classFolders as $idx => $folder) {
            $exclude = false;
            foreach ($folder->getCodes() as $code) {
                if (\in_array($code->getCode(), $excludedClassCodes, true)) {
                    $this->getLogger()->debug("'{$code->getCode()}' is in class exclusion list, skipping folder {$folder->getId()}...");
                    $exclude = true;
                    break;
                }
            }
            if (!$exclude) {
                $this->getLogger()->debug("Folder {$folder->getId()} not excluded, added to filtered set.");
                $filtered[$idx] = $folder;
            }
        }
        return $filtered;
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

        foreach ($pdfFilenames as $pdfFilename) {
            $this->getLogger()->debug("Adding PDF file to zip archive: $pdfFilename");
            $zipArchive->addFile($pdfFilename, \basename($pdfFilename));
        }
        $zipArchive->close();

        $this->getLogger()->debug("Writing zip archive to output stream");
        \fwrite($this->getOutStream(), \file_get_contents($zipFilename));

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
     * @throws \InvalidArgumentException
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
     * @throws \InvalidArgumentException
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
        // Did user specify absolute filename?
        if (\strpos($_SERVER['SCRIPT_NAME'], '/') === 0) {
            $file = $_SERVER['SCRIPT_NAME'];
        } else {
            $file = $_SERVER['PWD'] . '/' . \ltrim($_SERVER['SCRIPT_NAME'], '.');
        }
        $defaultMaxFails = self::DEFAULT_MAX_FAILS;
        $defaultJsDelay  = self::DEFAULT_JS_DELAY;

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
        Just generate the report for this student
        
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
         
    $yellow--now=NOW$white
         Attempt to produce a report for some specific point in time.
         Supports any format accepted by PHP's strtotime()

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
}

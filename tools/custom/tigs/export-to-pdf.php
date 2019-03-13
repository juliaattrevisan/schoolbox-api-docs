#!/usr/bin/php
<?php
/**
 * @copyright   Copyright 2019, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */
declare(strict_types=1);

use Knp\Snappy\Pdf;
use Schoolbox\Resource\Folder;
use Schoolbox\Application\CliKernelAbstract;
use Schoolbox\Application\CliTool;
use Schoolbox\GenericCollection;
use Schoolbox\IConfigLoader;
use Schoolbox\MapperFactory;
use Schoolbox\Realtime\Pipe\PipeManager;
use Schoolbox\Role;
use Schoolbox\UserMapper;
use GuzzleHttp\Client;
use Schoolbox\User;

require_once __DIR__ . '/../../../conf/core/require-cli.php';

// TODO - START - Delete this once SA-8248 is merged. It will be handled by the require above
require_once __DIR__ . '/../../../www/conf/conf.php';
require_once SITE_root . '/www/include/stdfuncs.php';
require_once SITE_root . '/library/constants.php';
set_include_path('.' . PATH_SEPARATOR . SITE_root . PATH_SEPARATOR . get_include_path());
require_once SITE_root . '/library/Autoloader.php';
Autoloader::initialise();
// TODO - END

// This tools CLI options
const OPT_SHORT_HELP      = 'h';            // print help
const OPT_LONG_HELP       = 'help';         // print help
const OPT_LONG_HASH       = 'hash:';        // session hash
const OPT_LONG_USERNAME   = 'username:';    // username
const OPT_LONG_STUDENT_ID = 'student-id:';  // student-id
const OPT_LONG_YEAR_LEVEL = 'year-level:';  // year-level
const OPT_LONG_CURRICULUM = 'curriculum:';  // curriculum
const OPT_LONG_NOW        = 'now:';         // to simulate time

$shortOpts = [OPT_SHORT_HELP];
$longOpts  = [OPT_LONG_HELP, OPT_LONG_HASH, OPT_LONG_USERNAME, OPT_LONG_STUDENT_ID, OPT_LONG_YEAR_LEVEL, OPT_LONG_CURRICULUM, OPT_LONG_NOW,];
$kernel    = new Schoolbox\Application\CliTool\Kernel($shortOpts, $longOpts);

$app = new class() extends CliTool\App
{

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

    private $fqdn;


    /**
     * @inheritdoc
     */
    public function run(): void
    {
        // Did user request help?
        if ($this->getOpt(OPT_SHORT_HELP) || $this->getOpt(OPT_LONG_HELP)) {
            $this->printHelp();
            exit(CliKernelAbstract::EXIT_STATUS_OK);
        }

        // Parse command-line options. If invalid options detected, then print help and exit.
        if (!$this->parseAppOptions()) {
            $this->printHelp();
            exit(CliKernelAbstract::EXIT_STATUS_APP_ERROR);
        }

        $di = $this->getDi();
        /** @var IConfigLoader $config */
        $config = $this->getDi()->get('config');
        /** @var MapperFactory $mapperFactory */
        $mapperFactory      = $this->getDi()->get('mapperFactory');
        $this->userMapper   = $mapperFactory->get(UserMapper::class);
        $this->folderMapper = $mapperFactory->get(Folder\Mapper::class);
        $this->externalUser = $di->get('externalUser');
        $this->fqdn         = \trim("{$config->get('site_hostname')}.{$config->get('site_domain')}");

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
        $this->sessionHash = $this->getOpt(OPT_LONG_HASH);
        $this->username    = $this->getOpt(OPT_LONG_USERNAME);
        $this->studentId   = $this->getOpt(OPT_LONG_STUDENT_ID);
        $this->yearLevel   = $this->getOpt(OPT_LONG_YEAR_LEVEL);
        $this->curriculum  = $this->getOpt(OPT_LONG_CURRICULUM);
        $this->now         = $this->getOpt(OPT_LONG_NOW);

        $this->getLogger()->debug("Command Line Options:");
        $this->getLogger()->debug("    sessionHash: {$this->sessionHash}");
        $this->getLogger()->debug("    username: {$this->username}");
        $this->getLogger()->debug("    studentId: {$this->studentId}");
        $this->getLogger()->debug("    yearLevel: {$this->yearLevel}");
        $this->getLogger()->debug("    curriculum: {$this->curriculum}");
        $this->getLogger()->debug("    now: {$this->now}");

        if ($this->curriculum && !\in_array($this->curriculum, [self::CURRICULUM_IB, self::CURRICULUM_HSC], true)) {
            $this->getLogger()->alert("Invalid 'curriculum' option: '$this->curriculum'");
            $valid = false;
        }

        if ($this->now && false === strtotime($this->now)) {
            $this->getLogger()->alert("Invalid 'now' option: '$this->now'");
            $valid = false;
        }
        return $valid;
    }


    /**
     * Start doing the work.
     */
    private function execute(): void
    {
        $excludedClassCodes = $this->getExcludedClassCodes();
        $sessionHash        = $this->getSessionHash();
        $students           = $this->getStudents();
        $maxIdLength        = $this->calculateMaxIdLength($students);
        $tempDir            = $this->createTempDir();
        $pdfFilenames       = [];

        foreach ($students as $student) {
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
                $pdfFilenames[] = $this->generatePdf($student, $sessionHash, $excludedClassCodes, $tempDir);
            } catch (RuntimeException $e) {
                $this->getLogger()->warning($e->getMessage());
                continue;
            }
        }

        $this->createZipArchive($pdfFilenames);
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
        return (int)\max(1, ceil(log10($maxUserId + 1)));
    }


    /**
     * Create a temp directory to store files and return its name
     *
     * @return string
     * @throws RuntimeException If unable to create the temp directory
     */
    private function createTempDir(): string
    {
        $tempDir = \sys_get_temp_dir() . '/' . \time();
        if (!mkdir($tempDir) && !is_dir($tempDir)) {
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
        // TODO Read and explode config value. See SA-8310
        return ['12KayakA', '02EN2', 'U15CRIC_A', 'SSP', '02EN1'];
    }


    /**
     * Gets a session hash to be used to enable web requests
     *
     * @return string
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
     */
    private function getStudents(): GenericCollection
    {
        $message = sprintf("Fetching users from schoolbox with role type of '%s' and user.year between 7 and 12", Role::STUDENT);
        $pb      = $this->userMapper->getPredicateBuilder()
                                    ->equals('g.group_type', Role::STUDENT)
                                    ->in('year', ['7', '8', '9', '10', '11', '12']); // Cannot use BETWEEN as this is stored as a VARCHAR in database

        if ($this->studentId) {
            $pb->equals('id', $this->studentId);
            $message .= sprintf(" and user.id of '%d'", $this->studentId);
        }

        if ($this->yearLevel) {
            $pb->equals('year', $this->yearLevel);
            $message .= sprintf(" and user.year of '%s'", $this->yearLevel);
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
     * @param string   $sessionHash
     * @param string[] $excludedClassCodes
     * @param string   $tempDir
     * @return string The filename of the PDF
     * @throws RuntimeException If unable to generate a PDF Report for this student
     */
    private function generatePdf(User $student, string $sessionHash, array $excludedClassCodes, string $tempDir): string
    {
        $urls = $this->generateUrlsForStudent($student, $excludedClassCodes);

        $this->getLogger()->info("Generating PDF...");
        $javascriptDelayMs = 200; // Setting this too low will cause either total failure, or some URLs to be skipped
        $maxAttempts       = 10;
        $attempts          = 0;
        $output            = null;
        while (!$output) {
            if ($attempts++ >= $maxAttempts) {
                throw new RuntimeException("Unable to generate report after $maxAttempts attempts, skipping");
            }
            try {
                $output = $this->snapshotUrlsToPdf($urls, $sessionHash, $javascriptDelayMs);
            } catch (RuntimeException $e) {
                $this->getLogger()->warning($e->getMessage());
                $this->getLogger()->warning($e->getTraceAsString());
                $javascriptDelayMs *= 2;
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
     * @param array $excludedClassCodes
     * @return string[]
     */
    private function generateUrlsForStudent(User $student, array $excludedClassCodes): array
    {
        $nowSuffix = $this->now ? "?now={$this->now}" : '';
        // TODO Currently only FullWebKernel has routes populated as it's tightly coupled to session for auth reasons
        $participationReportUrl = 'https://' . $this->fqdn . \str_replace('{userId}', $student->getId(), "/modules/custom/tigs/summative/participation/user/{userId}$nowSuffix");
        $this->getLogger()->debug("Adding url '$participationReportUrl' to list");
        $urls = [$participationReportUrl];

        $subjects         = $this->externalUser->getSubjectList($student);
        $filteredSubjects = $this->filterSubjects($subjects, $excludedClassCodes);

        foreach ($filteredSubjects as $filteredSubject) {
            try {
                $folder = $this->folderMapper->findByCode($filteredSubject['code']);
            } catch (\Schoolbox\Mapper\Exception $e) {
                $this->getLogger()->debug("No folder found for code '{$filteredSubject['code']}', skipping...");
                continue;
            }
            $urlWithUserReplaced   = \str_replace('{userId}', $student->getId(), "/modules/custom/tigs/summative/subject/{userId}/folder/{folderId}$nowSuffix");
            $urlWithFolderReplaced = 'https://' . $this->fqdn . \str_replace('{folderId}', $folder->getId(), $urlWithUserReplaced);
            $this->getLogger()->debug("Adding url '$urlWithFolderReplaced' to list");
            $urls[] = $urlWithFolderReplaced;
        }
        return $urls;
    }


    /**
     * Attempts to snapshot a set of URLs as a PDF, and return the PDF contents
     *
     * @param array  $urls
     * @param string $sessionHash
     * @param int    $javascriptDelayMs
     * @return string
     * @throws RuntimeException If unable to generate the PDF contents
     */
    private function snapshotUrlsToPdf(array $urls, string $sessionHash, int $javascriptDelayMs): string
    {
        $this->getLogger()->debug("Setting 'javascript-delay' to $javascriptDelayMs ms");

        // @see https://wkhtmltopdf.org/usage/wkhtmltopdf.txt
        $snappy = new Pdf('/usr/local/bin/wkhtmltopdf');
        $snappy->setOption('cookie', [PipeManager::PHP_SESSION_PREFIX => $sessionHash]);
        $snappy->setOption('orientation', 'Landscape');
        $snappy->setOption('no-background', true);
        $snappy->setOption('javascript-delay', $javascriptDelayMs);

        return $snappy->getOutput($urls);
    }


    /**
     * Removes any subjects that are in the exclusion list
     *
     * @param string[][] $subjects
     * @param string[]   $excludedClassCodes
     * @return string[][]
     */
    private function filterSubjects(array $subjects, array $excludedClassCodes): array
    {
        foreach ($subjects as $idx => $subject) {
            if (\in_array($subject['code'], $excludedClassCodes, true)) {
                $this->getLogger()->debug("'{$subject['code']}' is in class exclusion list, skipping...");
                unset($subjects[$idx]);
            }
        }
        return $subjects;
    }


    /**
     * Create a single ZIP archive of all the PDF documents
     *
     * @param string[] $pdfFilenames
     */
    private function createZipArchive(array $pdfFilenames): void
    {
        $zipFilename = \tempnam(\sys_get_temp_dir(), 'zip');
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
            $username = \trim(\readline('Please provide a schoolbox username: '));
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
            $password = \readline("Please provide your password ($remaining attempts remaining): ");
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
        // TODO $this->setup() should take kernel options and print them here if relevant
        $help = "
CLI wrapper to generate a PDF Archive of Participation & Subject Reports

By default, writes archive to STDOUT (1>) and writes log to STDERR (2>)

This can be changed by providing an output and/or log file. If provided, tool will not write to STDOUT or STDERR, respectively.

 Examples:
 * Write log to terminal, output to archive.zip
      ./export.php 1> archive.zip
      ./export.php -o archive.zip
 * Append log to export.log, output to archive.zip
      ./export.php 2>> export.log 1> archive.zip
      ./export.php -l export.log -o archive.zip\n";

        \fwrite(STDOUT, $help);
    }
};


$kernel->runApp($app);

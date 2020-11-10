<?php
/**
 * @copyright Copyright 2020, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Application\CliTool\PdfGenerator;

use GuzzleHttp\Client;
use Knp\Snappy\Pdf;
use RuntimeException;
use Schoolbox\Application\CliTool\App as BaseApp;
use Schoolbox\Auth\Remote\RemoteLogin;
use Schoolbox\Realtime\Pipe\PipeManager;
use Zend\Uri\Uri;
use Zend\Uri\UriInterface;
use ZipArchive;


/**
 * Generic functionality for a CLI tool which can generate PDFs.
 */
abstract class App extends BaseApp
{
//    private const OPT_SHORT_HELP      = 'h';                 // print help
//    private const OPT_LONG_HELP       = 'help';              // print help
    private const OPT_LONG_HASH     = 'hash:';             // session hash
    private const OPT_LONG_USERNAME = 'username:';         // username
//    private const OPT_LONG_STUDENT_ID = 'student-id:';       // student external id
//    private const OPT_LONG_YEAR_LEVEL = 'year-level:';       // year-level
//    private const OPT_LONG_CURRICULUM       = 'curriculum:';       // curriculum
    private const OPT_LONG_MAX_FAILS = 'max-fails:';        // Number of consecutive fails after which to abort the script's execution.
    private const OPT_LONG_JS_DELAY  = 'js-delay:';         // How many milliseconds to let JS run before attempting to print page
//    private const OPT_LONG_REPORT_YEAR      = 'report-year:';      // to generate a report for some point in time
    private const OPT_LONG_USER_STYLE_SHEET = 'user-style-sheet:'; // user style sheet
//    private const OPT_LONG_ROLE_TYPE        = 'role-type:';        // role-type override
//    private const OPT_LONG_INCLUDE_SUBJECTS = 'include-subjects';  // include subject reports

    private const DEFAULT_MAX_FAILS = 3;
    private const DEFAULT_JS_DELAY  = 1000;
//    private const DEFAULT_YEAR_LEVELS = [7, 8, 9,];

    private const EXIT_STATUS_AUTHENTICATION_FAILURE = 100;
    private const EXIT_STATUS_AUTHORISATION_FAILURE  = 101;

    /**
     * @inheritdoc
     */
    public function getShortOpts(): array
    {
        return [
//            self::OPT_SHORT_HELP
        ];
    }


    /**
     * @inheritdoc
     */
    public function getLongOpts(): array
    {
        return [
//            self::OPT_LONG_HELP,
            self::OPT_LONG_HASH,
            self::OPT_LONG_USERNAME,
//            self::OPT_LONG_STUDENT_ID,
//            self::OPT_LONG_YEAR_LEVEL,
            self::OPT_LONG_MAX_FAILS,
            self::OPT_LONG_JS_DELAY,
//            self::OPT_LONG_REPORT_YEAR,
            self::OPT_LONG_USER_STYLE_SHEET,
//            self::OPT_LONG_ROLE_TYPE,
        ];
    }


    /**
     * @inheritdoc
     */
    public function run(): void
    {
//        // Did user request help?
//        if ($this->getOpt(self::OPT_SHORT_HELP) || $this->getOpt(self::OPT_LONG_HELP)) {
//            $this->printHelp();
//            exit(CliKernelAbstract::EXIT_STATUS_OK);
//        }


//        // generate a list of URLs
//        /** @var Router $router */
//        $router = $this->getDi()->get('router');
//        /** @var IConnector $externalDb */
//        $externalDb   = $this->getDi()->get('databaseExternal');
//        $userMapper   = UserMapper::getInstance();
//        $folderMapper = FolderMapper::getInstance();
//        /** @var MapperProxy $assessmentDataSource */
//        $assessmentDataSource = $this->getDi()->get('assessmentDataSource');
//        $rubricDataSource     = RubricDataSource::getInstance();
//        $userAccessMapper     = UserAccessMapper::getInstance();
//        $urlGenerator         = new UrlGenerator(
//            $router,
//            new Factory(
//                new Gateway($externalDb),
//                $userMapper,
//                $folderMapper,
//                $assessmentDataSource,
//                $rubricDataSource,
//                $userAccessMapper
//            )
//        );


//        // Determine the report year and year levels to run the report for.
//        $reportYear = $this->getOptAsInt(self::OPT_LONG_REPORT_YEAR) ?? $this->getDefaultReportYear();
//        $yearLevel  = $this->getOptAsInt(self::OPT_LONG_YEAR_LEVEL);
//        if (isset($yearLevel)) {
//            $yearLevels = [$yearLevel];
//        } else {
//            $yearLevels = self::DEFAULT_YEAR_LEVELS;
//        }
//        $this->getLogger()->notice(
//            sprintf(
//                'Generating PDF reports for students in year(s) %s in calendar year %d',
//                implode(', ', $yearLevels),
//                $reportYear
//            )
//        );
//
//
//        // Once we have a report year and year level:
//        // figure out which students were in that year level that year.
//        // (Since this relies upon student year level: it won't work well for
//        //  fetching reports for students who are no longer at school.
//        //  Schoolbox doesn't store the dates in which a student was enrolled
//        //  within a particular year level, so there's nothing I can do there.)
//        $yearDelta = $this->getCurrentYear() - $reportYear;
//
//
//        // By default: generate for years 7, 8 and 9
//        $searchYearLevels = [];
//        foreach ($yearLevels as $yearLevel) {
//            $searchYearLevels[] = $yearLevel + $yearDelta;
//        }


        // Attempt to sign in:
        // Create a remote authentication class, whose only output is a
        // session ID cookie
        $sessionHash = $this->getOpt(self::OPT_LONG_HASH);
        if (!$sessionHash) {
            $sessionHash = $this->generateSessionHashViaLoginForm($this->getUsername());
        }


        // Build a PDF generator object, put the session ID cookie in it
        $pdfGenerator = $this->buildPdfGenerator();
        $pdfGenerator->setOption('cookie', [PipeManager::PHP_SESSION_PREFIX => $sessionHash]);


        // Create a temp dir
        $tempdir = $this->createTempDir();


        // Generate a list of filenames and files that will be generated
        $domain  = trim("{$this->getConfig()->get('site_hostname')}.{$this->getConfig()->get('site_domain')}");
        $port    = null;
        $baseUrl = (new Uri())
            ->setScheme('https')
            ->setHost($domain);
        if (isset($port)) {
            $baseUrl = $baseUrl->setPort($port);
        }
        $pdfFilenames = [];
        foreach ($this->generatePdfSources($baseUrl) as $pdfSource) {
            // TODO wrap this in a retry
            $this->getLogger()->notice($pdfSource['message']);

            // Create the PDF
            $filename = $tempdir . '/' . $pdfSource['filename'];
            $fp       = fopen($filename, 'wb');
            if (!$fp) {
                throw new RuntimeException("Unable to create file: '{$filename}'");
            }

            $maxAttempts       = 10;
            $attempts          = 0;
            $previousException = null;
            $jsDelay           = $this->getOptAsInt(self::OPT_LONG_JS_DELAY) ?? self::DEFAULT_JS_DELAY;
            while (true) {
                if ($attempts++ >= $maxAttempts) {
                    unlink($filename);
                    throw new RuntimeException("Unable to generate PDF after $maxAttempts attempts, skipping", 0, $previousException);
                }
                try {
                    // Creating the filename
                    $pdfGenerator->setOption('javascript-delay', $jsDelay);

                    // URLs need to have a port added
                    fwrite($fp, $pdfGenerator->getOutput($pdfSource['urls']));
                    $pdfFilenames[] = $filename;
                    break;
                } catch (RuntimeException $e) {
                    $this->getLogger()->warning($e->getMessage());
                    $this->getLogger()->warning($e->getTraceAsString());

                    // Extend the JS delay, as that might prevent rendering
                    $jsDelay           *= 2;
                    $previousException = $e;
                }
            }
            fclose($fp);
        }


        // Done generating PDFs: zip them up
        $this->createZipArchive($pdfFilenames, $tempdir);
        $this->cleanUpTempFiles($pdfFilenames, $tempdir);


//        // start making a user query.
//        $userQuery = $userMapper->getPredicateBuilder()
//                                ->in('year', $searchYearLevels);
//        $studentId = $this->getOptAsInt(self::OPT_LONG_STUDENT_ID);
//        if (isset($studentId)) {
//            $userQuery = $userQuery->equals('id', $studentId);
//        }
//
//        /** @var User[] $students */
//        $students = $userMapper->findByPredicates(
//            $userQuery,
//            [
//                'order' => [
//                    'lastname'  => 'asc',
//                    'firstname' => 'asc',
//                ],
//            ]
//        );
//        $this->getLogger()->notice(
//            sprintf('Found %d student(s) to generate reports for', count($students))
//        );
//        foreach ($students as $student) {
//            $this->getLogger()->info(
//                sprintf(
//                    'Generating report for %s, %s (ID: %d, TASS ID: %s)',
//                    $student->getLastname(),
//                    $student->getFirstname(),
//                    $student->getId(),
//                    $student->getExternalId()
//
//                )
//            );
//            $filename = sprintf('%s.pdf', $student->getExternalId());
//            $this->getLogger()->debug(sprintf('Generating report file: %s', $filename));
//
//            $urls = $urlGenerator->generate($reportYear, $student);
//
//            // The first URL will be the pastoral report
//            // All subsequent will be subject reports
//            // Collate these all into a single page
//
//            // create a file pointer
////            fwrite($fp, $pdfGenerator->getOutput($urls));
//        }
    }


//    /**
//     * Prints a help message, always to STDOUT, regardless of Kernel's output file option
//     */
//    private function printHelp(): void
//    {
//        $file              = $this->getToolFilename();
//        $defaultMaxFails   = self::DEFAULT_MAX_FAILS;
//        $defaultJsDelay    = self::DEFAULT_JS_DELAY;
//        $defaultReportYear = $this->getDefaultReportYear();
//        $roleTypes         = implode(', ', Role::getRoleTypes());
//
//        $green  = self::COLOUR_GREEN;
//        $white  = self::COLOUR_WHITE;
//        $yellow = self::COLOUR_YELLOW;
//
//        $help = "$white
//
//NAME:
//    Meriden eReport PDF Batch Printer
//
//SYNOPSIS:
//    $green{$file} {$yellow}[OPTION]...$white
//
//DESCRIPTION
//    This tool generates an archive of eReports in PDF format.
//
//    By default, writes archive to STDOUT (1>) and writes log to STDERR (2>)
//
//    This can be changed by providing an output and/or log file. If provided,
//    tool will not write to STDOUT or STDERR, respectively.
//
//    To authenticate the app, you can either provide a session hash, or a
//    username + password. If the latter, this app will prompt you to enter the
//    password on STDIN and STDOUT.
//
//    $yellow-h, --help$white
//        Print this help and exit
//
//    $yellow-o=OUT_FILE$white
//        Filename to write archive to, e.g. '-o=output.zip'
//
//    $yellow-l=LOG_FILE$white
//        Filename to append logs to, e.g. '-l=error.log'
//
//    $yellow-v[v[v]]$white
//        Log verbosity. Max 3 levels, e.g. -vvv
//
//    $yellow--hash=HASH$white
//        A valid session hash, you can extract this from a browser session
//
//    $yellow--username=USERNAME$white
//        A username to run the tool as
//
//    $yellow--student-id=ID$white
//        Just generate the report for this student.
//
//    $yellow--year-level=YEAR_LEVEL$white
//        Just generate reports for students in this year level for the report's
//        year, e.g. '--year-level=7'.
//        Defaults to generating reports for all year levels which may have an
//        eReport (i.e. 7, 8 and 9).
//
//    $yellow--max-fails=COUNT$white
//        If the tool fails for this many students in a row, exit immediately.
//        Defaults to $defaultMaxFails.
//
//    $yellow--js-delay=COUNT$white
//        Number of milliseconds to wait before printing a page to allow
//        javascript to execute.
//        Defaults to $defaultJsDelay.
//
//    $yellow--report-year=REPORT_YEAR$white
//        Generate the reports for the given year.
//        Defaults to the current year ($defaultReportYear).
//
//    $yellow--user-style-sheet=STYLE_FILE$white
//        Filename of a style sheet to load with every page.
//
//    $yellow--role-type=ROLE_TYPE$white
//        Print eReports as they would appear to users with this role type.
//        Must be one of [{$roleTypes}]
//
//EXAMPLES:
// * Write log to terminal, output to archive.zip
//      $file 1> archive.zip
//      $file -o archive.zip
// * Append log to export.log, output to archive.zip
//      $file 2>> export.log 1> archive.zip
//      $file -l export.log -o archive.zip
//";
//        fwrite(STDOUT, $help);
//    }
//
//
//    /**
//     * What is the default year that this report runs for?
//     *
//     * @return int
//     */
//    private function getDefaultReportYear(): int
//    {
//        return $this->getCurrentYear();
//    }
//
//
//    /**
//     * What is the current year?
//     *
//     * @return int
//     */
//    private function getCurrentYear(): int
//    {
//        return (int)date('Y');
//    }


    /**
     * Build a PDF generator with sensible settings.
     *
     * @return Pdf
     */
    private function buildPdfGenerator(): Pdf
    {
        $pdfGenerator = new Pdf('/usr/local/bin/wkhtmltopdf');

        // TODO expose more CLI options
        $pdfGenerator->setTimeout(120);
        $pdfGenerator->setOption('orientation', 'Landscape');
        $pdfGenerator->setOption('disable-external-links', true);
        $pdfGenerator->setOption('viewport-size', 1024);

        $userStyleSheet = $this->getOpt(self::OPT_LONG_USER_STYLE_SHEET);
        if (isset($userStyleSheet)) {
            $pdfGenerator->setOption('user-style-sheet', $userStyleSheet);
        }
        return $pdfGenerator;
    }


    /**
     * Return a username, either from command line options, or by prompting the
     * user.
     *
     * @return string
     */
    private function getUsername(): string
    {
        // Revert to username and password
        $username = $this->getOpt(self::OPT_LONG_USERNAME);
        if (isset($username)) {
            $this->getLogger()->debug('Using username provided on command line');
            return $username;
        }

        while (true) {
            $this->getLogger()->debug("Requesting username");
            $username = trim($this->readLine('Please provide a schoolbox username: '));
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
     * Attempt to login via the web form, and return the generated session hash.
     *
     * @param string $username
     * @return string
     */
    private function generateSessionHashViaLoginForm(string $username): string
    {
        $attempts    = 0;
        $maxAttempts = 3;
        $remoteLogin = new RemoteLogin(
            new Client(),
            trim("{$this->getConfig()->get('site_hostname')}.{$this->getConfig()->get('site_domain')}")
        );
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
                $cookie = $remoteLogin->authenticate($username, $password);
                if (isset($cookie)) {
                    return $cookie;
                }
            }
            $this->getLogger()->warning("Password appears invalid");
        }
    }


    /**
     * Create a temp directory to store files and return its name
     *
     * @return string
     * @throws RuntimeException If unable to create the temp directory
     */
    private function createTempDir(): string
    {
        $tempDir = $this->getConfig()->get('zip_dir') . '/' . time();
        if (!mkdir($tempDir) && !is_dir($tempDir)) {
            throw new RuntimeException(sprintf('Directory "%s" was not created', $tempDir));
        }
        return $tempDir;
    }


    /**
     * Create a single ZIP archive of all the PDF documents
     *
     * @param string[] $pdfFilenames
     * @param string   $tempDir
     */
    private function createZipArchive(array $pdfFilenames, string $tempDir): void
    {
        $zipFilename = tempnam($tempDir, 'zip');

        /* https://stackoverflow.com/questions/32694148/ziparchivegetstatusstring-invalid-or-uninitialized-zip-object
         * Also, SA-10419
         *
         * ZipArchive doesn't expect a file to exist when it's creating its zip archive
         */
        @unlink($zipFilename);

        $zipArchive = new ZipArchive();
        $this->getLogger()->debug("Creating temp zip archive: $zipFilename");
        $zipArchive->open($zipFilename, ZipArchive::CREATE);

        // To avoid exceeding open file limit, may need to batch zip
        $openFileLimit = (int)exec('ulimit -n');
        $batchSize     = round($openFileLimit - 20, -2); // buffer of 20, round to nearest 100
        $this->getLogger()->debug(sprintf('Open file limit: %d, setting batch size to %d', $openFileLimit, $batchSize));
        $batchIndex = 1;
        $batches    = ceil(count($pdfFilenames) / $batchSize);
        foreach ($pdfFilenames as $pdfFilename) {
            if ($batchIndex++ % $batchSize === 0) {
                $batchCount = (int)($batchIndex / $batchSize);
                $this->getLogger()->info("Writing zip batch $batchCount/$batches...");
                $zipArchive->close();
                $zipArchive->open($zipFilename);
            }
            $this->getLogger()->debug("Adding PDF file to zip archive: $pdfFilename");
            $zipArchive->addFile($pdfFilename, basename($pdfFilename));
        }
        $this->getLogger()->info("Writing zip batch $batches/$batches...");
        $zipArchive->close();

        $this->getLogger()->debug("Writing zip archive to output stream");
        if (false === stream_copy_to_stream(fopen($zipFilename, 'rb'), $this->getOutStream())) {
            $this->getLogger()->error('Unable to write zip archive to output stream');
        }

        $this->getLogger()->debug("Deleting zip archive: $zipFilename");
        unlink($zipFilename);
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
            unlink($pdfFilename);
        }

        $this->getLogger()->debug("Deleting temp directory $tempDir");
        rmdir($tempDir);
    }


    /**
     * Generate the list of PDFs to generate.
     *
     * @param UriInterface $baseUrl
     * @return array
     */
    abstract protected function generatePdfSources(UriInterface $baseUrl): array;
}

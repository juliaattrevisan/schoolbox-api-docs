<?php
/**
 * @copyright Copyright 2020, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Custom\Meriden\Pastoral\CommandLineTool;

use Alaress\Database\Connector\IConnector;
use Aura\Router\Router;
use Custom\Meriden\Pastoral\StudentInvolvement\Factory;
use Custom\Meriden\Pastoral\StudentInvolvement\Gateway;
use Schoolbox\Application\CliKernelAbstract;
use Schoolbox\Application\CliTool\App;
use Schoolbox\Assessment\Rubric\DataModel\DataSource as RubricDataSource;
use Schoolbox\Learning\Assessment\DataModel\MapperProxy;
use Schoolbox\Permission\Access\UserAccessMapper;
use Schoolbox\Resource\Folder\Mapper as FolderMapper;
use Schoolbox\Role;
use Schoolbox\Sync\Provisioner\UserMapper;
use function fwrite;

/**
 * Command-line PDF generator
 */
class PdfGenerator extends App
{
    private const OPT_SHORT_HELP      = 'h';                 // print help
    private const OPT_LONG_HELP       = 'help';              // print help
    private const OPT_LONG_HASH       = 'hash:';             // session hash
    private const OPT_LONG_USERNAME   = 'username:';         // username
    private const OPT_LONG_STUDENT_ID = 'student-id:';       // student external id
    private const OPT_LONG_YEAR_LEVEL = 'year-level:';       // year-level
//    private const OPT_LONG_CURRICULUM       = 'curriculum:';       // curriculum
    private const OPT_LONG_MAX_FAILS        = 'max-fails:';        // Number of consecutive fails after which to abort the script's execution.
    private const OPT_LONG_JS_DELAY         = 'js-delay:';         // How many milliseconds to let JS run before attempting to print page
    private const OPT_LONG_REPORT_YEAR      = 'report-year:';      // to generate a report for some point in time
    private const OPT_LONG_USER_STYLE_SHEET = 'user-style-sheet:'; // user style sheet
    private const OPT_LONG_ROLE_TYPE        = 'role-type:';        // role-type override
//    private const OPT_LONG_INCLUDE_SUBJECTS = 'include-subjects';  // include subject reports

    private const DEFAULT_MAX_FAILS   = 3;
    private const DEFAULT_JS_DELAY    = 1000;
    private const DEFAULT_YEAR_LEVELS = [7, 8, 9,];

    /**
     * @inheritdoc
     */
    public function getShortOpts(): array
    {
        return [self::OPT_SHORT_HELP];
    }


    /**
     * @inheritdoc
     */
    public function getLongOpts(): array
    {
        return [
            self::OPT_LONG_HELP,
            self::OPT_LONG_HASH,
            self::OPT_LONG_USERNAME,
            self::OPT_LONG_STUDENT_ID,
            self::OPT_LONG_YEAR_LEVEL,
            self::OPT_LONG_MAX_FAILS,
            self::OPT_LONG_JS_DELAY,
            self::OPT_LONG_REPORT_YEAR,
            self::OPT_LONG_USER_STYLE_SHEET,
            self::OPT_LONG_ROLE_TYPE,
        ];
    }


    /**
     * @inheritdoc
     */
    public function run(): void
    {
        // Did user request help?
        if ($this->getOpt(self::OPT_SHORT_HELP) || $this->getOpt(self::OPT_LONG_HELP)) {
            $this->printHelp();
            exit(CliKernelAbstract::EXIT_STATUS_OK);
        }

        // generate a list of URLs

        /** @var Router $router */
        $router = $this->getDi()->get('router');
        /** @var IConnector $externalDb */
        $externalDb   = $this->getDi()->get('databaseExternal');
        $userMapper   = UserMapper::getInstance();
        $folderMapper = FolderMapper::getInstance();
        /** @var MapperProxy $assessmentDataSource */
        $assessmentDataSource = $this->getDi()->get('assessmentDataSource');
        $rubricDataSource     = RubricDataSource::getInstance();
        $userAccessMapper     = UserAccessMapper::getInstance();
        $urlGenerator         = new UrlGenerator(
            $router,
            new Factory(
                new Gateway($externalDb),
                $userMapper,
                $folderMapper,
                $assessmentDataSource,
                $rubricDataSource,
                $userAccessMapper
            )
        );


        // Determine the report year and year levels to run the report for.
        $reportYear = $this->getOptAsInt(self::OPT_LONG_REPORT_YEAR) ?? $this->getDefaultReportYear();
        $yearLevel  = $this->getOptAsInt(self::OPT_LONG_YEAR_LEVEL);
        if (isset($yearLevel)) {
            $yearLevels = [$yearLevel];
        } else {
            $yearLevels = self::DEFAULT_YEAR_LEVELS;
        }


        // Once we have a report year and year level:
        // figure out which students were in that year level that year.
        // (Since this relies upon student year level: it won't work well for
        //  fetching reports for students who are no longer at school.
        //  Schoolbox doesn't store the dates in which a student was enrolled
        //  within a particular year level, so there's nothing I can do there.)
        $yearDelta = $this->getCurrentYear() - $reportYear;


        // By default: generate for years 7, 8 and 9
        $searchYearLevels = [];
        foreach ($yearLevels as $yearLevel) {
            $searchYearLevels[] = $yearLevel + $yearDelta;
        }


        // start making a user query.
        // TODO for 20.2 year will be in a join table, so update the query
        $userQuery = $userMapper->getPredicateBuilder()
            ->in('year', $searchYearLevels);
        $studentId = $this->getOptAsInt(self::OPT_LONG_STUDENT_ID);
        if (isset($studentId)) {
            $userQuery = $userQuery->equals('id', $studentId);
        }

        foreach ($userMapper->findByPredicates($userQuery) as $student) {
            $urls = $urlGenerator->generate($reportYear, $student);
            foreach ($urls as $url) {
                var_dump($url['url']);
            }
        }
    }


    /**
     * Prints a help message, always to STDOUT, regardless of Kernel's output file option
     */
    private function printHelp(): void
    {
        $file              = $this->getToolFilename();
        $defaultMaxFails   = self::DEFAULT_MAX_FAILS;
        $defaultJsDelay    = self::DEFAULT_JS_DELAY;
        $defaultReportYear = $this->getDefaultReportYear();
        $roleTypes         = implode(', ', Role::getRoleTypes());

        $green  = self::COLOUR_GREEN;
        $white  = self::COLOUR_WHITE;
        $yellow = self::COLOUR_YELLOW;

        $help = "$white

NAME:
    Meriden eReport PDF Batch Printer

SYNOPSIS:
    $green{$file} {$yellow}[OPTION]...$white

DESCRIPTION
    This tool generates an archive of eReports in PDF format.

    By default, writes archive to STDOUT (1>) and writes log to STDERR (2>)

    This can be changed by providing an output and/or log file. If provided,
    tool will not write to STDOUT or STDERR, respectively.

    To authenticate the app, you can either provide a session hash, or a
    username + password. If the latter, this app will prompt you to enter the
    password on STDIN and STDOUT.

    $yellow-h, --help$white
        Print this help and exit

    $yellow-o=OUT_FILE$white
        Filename to write archive to, e.g. '-o=output.zip'

    $yellow-l=LOG_FILE$white
        Filename to append logs to, e.g. '-l=error.log'

    $yellow-v[v[v]]$white
        Log verbosity. Max 3 levels, e.g. -vvv

    $yellow--hash=HASH$white
        A valid session hash, you can extract this from a browser session

    $yellow--username=USERNAME$white
        A username to run the tool as

    $yellow--student-id=ID$white
        Just generate the report for this student.

    $yellow--year-level=YEAR_LEVEL$white
        Just generate reports for students in this year level for the report's
        year, e.g. '--year-level=7'.
        Defaults to generating reports for all year levels which may have an
        eReport (i.e. 7, 8 and 9).

    $yellow--max-fails=COUNT$white
        If the tool fails for this many students in a row, exit immediately.
        Defaults to $defaultMaxFails.

    $yellow--js-delay=COUNT$white
        Number of milliseconds to wait before printing a page to allow
        javascript to execute.
        Defaults to $defaultJsDelay.

    $yellow--report-year=REPORT_YEAR$white
        Generate the reports for the given year.
        Defaults to the current year ($defaultReportYear).

    $yellow--user-style-sheet=STYLE_FILE$white
        Filename of a style sheet to load with every page.

    $yellow--role-type=ROLE_TYPE$white
        Print eReports as they would appear to users with this role type.
        Must be one of [{$roleTypes}]

EXAMPLES:
 * Write log to terminal, output to archive.zip
      $file 1> archive.zip
      $file -o archive.zip
 * Append log to export.log, output to archive.zip
      $file 2>> export.log 1> archive.zip
      $file -l export.log -o archive.zip
";
        fwrite(STDOUT, $help);
    }


    /**
     * What is the default year that this report runs for?
     *
     * @return int
     */
    private function getDefaultReportYear(): int
    {
        return $this->getCurrentYear();
    }


    /**
     * What is the current year?
     *
     * @return int
     */
    private function getCurrentYear(): int
    {
        return (int)date('Y');
    }
}

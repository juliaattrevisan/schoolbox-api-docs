<?php
/**
 * @copyright Copyright 2020, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Custom\Meriden\Pastoral\CommandLineTool;

use Schoolbox\Application\CliKernelAbstract;
use Schoolbox\Application\CliTool\App;
use Schoolbox\Role;
use function fwrite;

/**
 * Command-line PDF generator
 */
class PdfGenerator extends App
{
    private const OPT_SHORT_HELP            = 'h';                 // print help
    private const OPT_LONG_HELP             = 'help';              // print help
    private const OPT_LONG_HASH             = 'hash:';             // session hash
    private const OPT_LONG_USERNAME         = 'username:';         // username
    private const OPT_LONG_STUDENT_ID       = 'student-id:';       // student external id
    private const OPT_LONG_YEAR_LEVEL       = 'year-level:';       // year-level
//    private const OPT_LONG_CURRICULUM       = 'curriculum:';       // curriculum
    private const OPT_LONG_MAX_FAILS        = 'max-fails:';        // Number of consecutive fails after which to abort the script's execution.
    private const OPT_LONG_JS_DELAY         = 'js-delay:';         // How many milliseconds to let JS run before attempting to print page
    private const OPT_LONG_REPORT_YEAR      = 'report-year:';      // to generate a report for some point in time
    private const OPT_LONG_USER_STYLE_SHEET = 'user-style-sheet:'; // user style sheet
    private const OPT_LONG_ROLE_TYPE        = 'role-type:';        // role-type override
//    private const OPT_LONG_INCLUDE_SUBJECTS = 'include-subjects';  // include subject reports

    private const DEFAULT_MAX_FAILS   = 3;
    private const DEFAULT_JS_DELAY    = 1000;

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
        year, e.g. '--year-level=7'

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
        return (int)date('Y');
    }
}

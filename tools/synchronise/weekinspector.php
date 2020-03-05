#!/usr/bin/php
<?php
/**
 * @author      Steve Chan <steve.chan@schoolbox.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        http://www.schoolbox.com.au/
 */
declare(strict_types=1);

use Schoolbox\Application\CliTool;
use Schoolbox\External\Connector\Kamar\Base\Endpoint\Receiver;
use Schoolbox\External\Connector\Kamar\Base\Endpoint\Timetable\WeekOffset;
use Schoolbox\Assessment\Term;

require_once __DIR__ . '/../../conf/core/require.php';

/**
 * CLI Tool
 *
 * Copy this to /tools folder
 *
 * To redirect log output to a file(s):
 *     STDOUT is file descriptor 1
 *     STDERR is file descriptor 2; our logger output ends up going here
 *
 *     Example: php example.php 2> log.txt
 */
$app = new class extends CliTool\App {

    /**
     * --help
     *
     * Print help text for those who don't know how to use this tool
     */
    private const OPT_LONG_HELP = 'help';


    /**
     * A year for which to search for a Term
     * The dates are used as week offsets
     */
    private const OPT_LONG_YEAR = 'year:';


    /**
     * A class code for which to search
     */
    private const OPT_LONG_CODE = 'code:';


    /**
     * A particular slot index for which to search
     */
    private const OPT_LONG_SLOT = 'slot:';


    /**
     * A particular teacher string for which to search
     * Teacher ids are comma-separated in some classes
     */
    private const OPT_LONG_TEACHER = 'teachers:';


    /**
     * @inheritdoc
     */
    public function getLongOpts(): array
    {
        return [
            self::OPT_LONG_HELP,
            self::OPT_LONG_YEAR,
            self::OPT_LONG_CODE,
            self::OPT_LONG_SLOT,
            self::OPT_LONG_TEACHER,
        ];
    }


    /**
     * Run the cli tool
     */
    public function run(): void
    {
        if ($this->getOpt(self::OPT_LONG_HELP)) {
            echo <<<HELP

Find a file containing the specified class code, slot and teacher code.
Used to find data spread across multiple xml files broadcast by Kamar.

Slot and Teacher are optional args
The intent behind that interface was so that you could look for instances of
a particular class taught by a particular teacher, and where that manifests
among the forest of xml files.

Otherwise it just reports when classes occur across the entire forest.

Usage: php weekinspector.php --year {year} --code {code} [--slot {slot}] [--teachers {teachers}]
        --year       a four digit int
        --code       a class code string
        --slot       a slot index between 1 and 10
        --teachers   a teacher string

Examples:
    // All classes with code 13MATC
    php weekinspector.php --year 2020 --code 13MATC

    // All slot 2 classes with code 13MATC
    php weekinspector.php --year 2020 --code 13MATC --slot 2

    // All classes with code 13MATC taught by JKE
    php weekinspector.php --year 2020 --code 13MATC --teachers JKE

    // All slot 2 classes with code 13MATC taught by JKE
    php weekinspector.php --year 2020 --code 13MATC --slot 2 --teachers JKE


HELP;
            exit;
        }

        /* Understand args
         */
        $year = (int)$this->getOpt(self::OPT_LONG_YEAR);
        $classCode = $this->getOpt(self::OPT_LONG_CODE);
        $weekOffset = (new WeekOffset(Term\Mapper::getInstance()))->findWeekOffset($year);

        $this->getLogger()->info("We are in week {$weekOffset}.");

        /* Use the path specified in config
         */
        $path = $this->getConfig()->get('schooldata_import_endpoint_host');

        $dirhandle = opendir($path);

        /* Investigate all files
         */
        for ($n = 0; $xmlFile = readdir($dirhandle);) {
            if ($xmlFile !== '.' && $xmlFile !== '..' && strpos($xmlFile, Receiver::XML_STUDENTTIMETABLES) !== false) {
                $nodes = new SimpleXMLElement(file_get_contents("{$path}/{$xmlFile}"));

                $timetables = $nodes->xpath('/SMSDirectoryData/timetables/timetable');

                /* Investigate every timetable node in every file
                 */
                foreach ($timetables as $timetable) {
                    $studentId = $timetable->xpath('@student')[0];

                    $weeks = $timetable->xpath("week[@index={$weekOffset}]");

                    /* Find out if this week contains a particular class as per the cli args
                     */
                    foreach ($weeks as $week) {
                        $subjects = $week->xpath("days/day/slots/subjects/subject[contains(code, '{$classCode}')]");

                        if (\count($subjects)) {
                            foreach ($subjects as $subject) {
                                $subjectsNode = $subject->xpath('ancestor::subjects')[0];
                                $slot = (string)$subjectsNode->xpath('@slot')[0];
                                $day = (string)$subjectsNode->xpath('ancestor::day/@index')[0];
                                $teachers = (string)$subject->teachers;

                                $slotSpecified = (bool)$this->getOpt(self::OPT_LONG_SLOT);
                                $slotMatches = $slot === $this->getOpt(self::OPT_LONG_SLOT);
                                $teacherSpecified = (bool)$this->getOpt(self::OPT_LONG_TEACHER);
                                $teacherMatches = $teachers === $this->getOpt(self::OPT_LONG_TEACHER);

                                if (($slotSpecified && $slotMatches && $teacherSpecified && $teacherMatches) ||
                                    (!$slotSpecified && $teacherSpecified && $teacherMatches) ||
                                    (!$teacherSpecified && $slotSpecified && $slotMatches) ||
                                    (!$slotSpecified && !$teacherSpecified)) {
                                    $message = sprintf(
                                        'Student %s, day %s, slot %s: Teachers: %s, Rooms: %s: %s',
                                        $studentId,
                                        $day,
                                        $slot,
                                        $teachers,
                                        (string)$subject->rooms,
                                        $xmlFile
                                    );

                                    $this->getLogger()->info($message);
                                }
                            }
                        }
                    }
                }

                /* Number of files processed
                 */
                $n++;
            }
        }

        if (!$n) {
            $this->getLogger()->info(sprintf(
                'No files processed: do you have any xmls in %s?',
                $path
            ));
        }
    }


    /**
     * FIXME Decide whether this script must be run as root
     *  By default, this returns false (see CliTool\App) but if you're using methods that require
     *  access to special folders (such as when you're creating files) you need to run this as root.
     *  Otherwise it'll fail with a generic error that might mislead you
     *
     * @return bool|null
     */
    public function runAsRoot(): ?bool
    {
        return parent::runAsRoot();
    }
};

/* OPTION_ERRORS => E_ALL causes all notices and errors to fatally crash your script
 *
 * You should generally try to fix those, even though in some special cases php will throw notices
 * for its own expected behaviours which will inadvertently cause this to fail
 */
$options = [CliTool\Kernel::OPTION_LEVEL => Monolog\Logger::DEBUG, CliTool\Kernel::OPTION_ERRORS => E_ALL,];

/** @noinspection PhpUnhandledExceptionInspection */
(new Schoolbox\Application\CliTool\Kernel($app, $options))->runApp();

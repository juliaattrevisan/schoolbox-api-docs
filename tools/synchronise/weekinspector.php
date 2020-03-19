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
use Schoolbox\External\TimetableCampusMapper;

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
     * The term you're looking in
     */
    private const OPT_LONG_TERM = 'term:';


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
            self::OPT_LONG_TERM,
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

Note: You should upload the appropriate Timetable Structure csv first.
This defines the Terms' start/finish dates via Timetable Cycle dates.
Otherwise the week calculations are going to look completely wrong.

Usage: php weekinspector.php --term {term} --code {code} [--slot {slot}] [--teachers {teachers}]
        --term       an int
        --code       a class code string
        --slot       a slot index between 1 and 10
        --teachers   a teacher string

Examples:
    // All classes with code 13MATC in term 2
    php weekinspector.php --term 2 --code 13MATC

    // All slot 2 classes in term 2 with code 13MATC
    php weekinspector.php --term 2 --code 13MATC --slot 2

    // All classes in term 2 with code 13MATC taught by JKE
    php weekinspector.php --term 2 --code 13MATC --teachers JKE

    // All slot 2 classes in term 2 with code 13MATC taught by JKE
    php weekinspector.php --term 2 --code 13MATC --slot 2 --teachers JKE


HELP;
            exit;
        }

        /* Understand args
         */
        $currentTerm = (int)$this->getOpt(self::OPT_LONG_TERM);
        $classCode = $this->getOpt(self::OPT_LONG_CODE);

        $weekOffsetCalculator = new WeekOffset(
            TimetableCampusMapper::getInstance(),
            \Alaress_Date_Format::now(),
            $this->getLogger()
        );

        $currentWeekOffset = $weekOffsetCalculator->findWeekOffset();
        $termSettings = $weekOffsetCalculator->findCurrentTerm();

        /* Figure out which term number we're in, to navigate xml files' nodes
         * Figure out which cycle we're in, when persisting data
         */
        $currentTerm = $termSettings['termIndex'];
        $currentCycleId = $termSettings['cycleId'];

        $this->getLogger()->info(sprintf(
            'We are in Week Offset %s for Term %s. This equates to being %s week(s) into Term %s',
            $currentWeekOffset,
            $currentTerm,
            $currentWeekOffset+1,
            $currentTerm
        ));

        $this->getLogger()->info(sprintf(
            'Imported classes should be associated with cycle_id %s which is described in sis_timetable_campus and sis_timetable_cycle',
            $currentCycleId
        ));

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

                    $weeks = $timetable->xpath("week[contains(term, '{$currentTerm}')]");

                    /* Find out if this week contains a particular class as per the cli args
                     */
                    foreach ($weeks as $week) {
                        $subjects = $week->xpath("days/day/slots/subjects/subject[contains(code, '{$classCode}')]");

                        if (\count($subjects)) {
                            foreach ($subjects as $subject) {
                                $subjectsNode = $subject->xpath('ancestor::subjects')[0];
                                $slot = (string)$subjectsNode->xpath('@slot')[0];
                                $dayIndex = (string)$subjectsNode->xpath('ancestor::day/@index')[0];
                                $weekIndex = (string)$week->xpath('@index')[0];
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
                                        'Student %s, week @index %s, day @index %s, slot %s: Teachers: %s, Rooms: %s: %s',
                                        $studentId,
                                        (string)$week->xpath('@index')[0],
                                        $dayIndex,
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

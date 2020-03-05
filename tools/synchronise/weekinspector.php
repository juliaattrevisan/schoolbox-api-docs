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
     * --flag
     *
     * An example arg that's a boolean on/off
     */
    private const OPT_LONG_FLAG = 'flag';


    /**
     * --input
     *
     * An example arg that has an actual value, denoted by the colon
     */
    private const OPT_LONG_YEAR = 'year:';

    private const OPT_LONG_CODE = 'code:';

    private const OPT_LONG_SLOT = 'slot:';

    private const OPT_LONG_TEACHER = 'teachers:';

    /**
     * @inheritdoc
     */
    public function getLongOpts(): array
    {
        return [
            self::OPT_LONG_HELP,
            self::OPT_LONG_FLAG,
            self::OPT_LONG_YEAR,
            self::OPT_LONG_CODE,
            self::OPT_LONG_SLOT,
            self::OPT_LONG_TEACHER,
        ];
    }


    private function findWeekOffset(int $year): ?int
    {
        $terms = \Schoolbox\Assessment\Term\Mapper::getInstance()->findForYear($year);

        if ($terms->count()) {
            $term = $terms->first();
            $now = \Alaress_Date_Format::now();

            $startWeek = (int)date('W', $term->getStart()->getUnixTimestamp());
            $currentWeek = (int)date('W', $now->getUnixTimestamp());

            /* Include the current week in calculations using a +1 offset
             * e.g. if term begins on 8/1/20,
             *     8/1/20, 9/1/20 and 10/1/20 are in week 1
             *     11/1/20 is in week 2
             *     17/1/20 is in week 2
             *     21/2/20 is in week 3
             */
            $offset = $currentWeek - $startWeek + 1;

            if ($offset > 0) {
                return $offset;
            }
        }

        return null;
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

Slot and Teacher are optional args that must be provided together.
If only one, or none, are provided, then the tool just looks for class codes.

The intent behind that interface was so that you could look for instances of
a particular class taught by a particular teacher, and where that manifests
among the forest of xml files.

Otherwise it just reports when classes occur across the entire forest.

Usage: php weekinspector.php --year {year} --code {code} [--slot {slot} --teachers {teachers}]
        --year       a four digit int
        --code       a class code string
        --slot       a slot index between 1 and 10
        --teachers   a teacher string

Example: php weekinspector.php --year 2020 --code 13MATC [--slot 2 --teachers JKE]


HELP;
            exit;
        }

        $year = (int)$this->getOpt(self::OPT_LONG_YEAR);
        $classCode = $this->getOpt(self::OPT_LONG_CODE);

        $offset = $this->findWeekOffset($year);

        $this->getLogger()->info("We are in week {$offset}.");

        $path = '/vagrant/data/cache/stream/kamar/archive';

        $dirhandle = opendir($path);

        while ($xmlFile = readdir($dirhandle)) {
            if ($xmlFile !== '.' && $xmlFile !== '..' && strpos($xmlFile, Receiver::XML_STUDENTTIMETABLES) !== false) {
                $nodes = new SimpleXMLElement(file_get_contents("{$path}/{$xmlFile}"));

                $timetables = $nodes->xpath('/SMSDirectoryData/timetables/timetable');

                foreach ($timetables as $timetable) {
                    $studentId = $timetable->xpath('@student')[0];

                    $weeks = $timetable->xpath("week[@index={$offset}]");

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
            }
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

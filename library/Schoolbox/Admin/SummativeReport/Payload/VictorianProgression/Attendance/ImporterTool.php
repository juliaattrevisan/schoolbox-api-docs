<?php

/**
 * CSV Importer for Penola's Semester 1, 2024 Attendance data
 *
 * Penola have an Accellerus export that contains feedbacks for particular assessments that
 * circumstantially represent a student's attendance in a class. This imports the feedback
 * into the db.
 *
 * @copyright   Copyright 2024, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Admin\SummativeReport\Payload\VictorianProgression\Attendance;

use Schoolbox\Application\CliTool;

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
class ImporterTool extends CliTool\App
{
    /**
     * Make sure to add your locking script to the Lock Inspector tool
     * const LOCK_NAMES in Inspector.php
     */
    public const LOCK_NAME = 'Attendance_PCC_Sem1_2024';


    /**
     * Arbitrarily choose actual dates
     */
    private const ASSESSMENT_SEM1_DATE_PUBLISH = '04/05/2023 12:01am';


    /**
     * Decide how long this should be based on your usage
     * You might have to adjust it, but it should cover the cost of an iteration
     */
    private const LOCK_TTL = 60;


    /**
     * --input
     *
     * Path to csv file
     */
    private const OPT_LONG_INPUT = 'input:';


    /**
     * --title
     *
     *Title of the assessment whose feedbacks you want to populate
     */
    private const OPT_LONG_TITLE = 'title:';


    /**
     * --worktype
     *
     * Work Type of the assessment whose feedbacks you want to populate
     */
    private const OPT_LONG_WORKTYPE = 'worktype:';


    /**
     * --folder
     *
     * folder code whose rows you want to process specifically.
     */
    private const OPT_LONG_FOLDER = 'folder:';


    /**
     * --creator
     *
     * id of user who will have marked and published each feedback.
     */
    private const OPT_LONG_CREATOR = 'creator:';


    /**
     * --publish
     *
     * Choose a publish date for the feedbacks: e.g. 4/05/2023 12:01am
     */
    private const OPT_LONG_PUBLISH_AT = 'publish:';


    /**
     * --help
     *
     * Print help text for those who don't know how to use this tool
     */
    private const OPT_LONG_HELP = 'help';


    /**
     * @inheritdoc
     */
    public function getLongOpts(): array
    {
        return [
            self::OPT_LONG_INPUT,
            self::OPT_LONG_TITLE,
            self::OPT_LONG_WORKTYPE,
            self::OPT_LONG_FOLDER,
            self::OPT_LONG_CREATOR,
            self::OPT_LONG_PUBLISH_AT,
            self::OPT_LONG_HELP
        ];
    }


    /**
     * Run the cli tool
     */
    public function run(): void
    {
        if ($this->getOpt(self::OPT_LONG_HELP)) {
            echo <<<HELP

CSV Importer for Penola's Semester 1, 2024 Attendance data

Penola have an Accellerus export that contains feedbacks for particular assessments that
circumstantially represent a student's attendance in a class. This imports the feedback
into the db.

Requirements:
 * Attendance assessments must be a due work
 * Attendance assessments must use Percentage mark type
 * CSV Columns: FileYear, ActualSemester, Student Name, ClassCode, StudentId, Attendance Rate, Comment

Usage: attendanceImport.php --input=path/to/my/file.csv [--title=...] [--worktype=...] [--folder] [--creator] [--perform]


Options
-------

    --title  
        The assessment name against which feedbacks are stored. It's an exact name match.
        If you don't want to use assessment names, you can choose a work type instead.
            
    --worktype  
        The worktype id which is used to identify assessments against which feedbacks are stored.
        If you don't want to use a work type, you can choose an assessment name instead.
        
    --folder
        Choose a particular folder code whose feedback you want to import. It'll still parse the whole
        csv but it'll skip rows that don't match this folder code. This can be used to partially
        import a csv progressively.
        
    --creator
        A user id for the schoolbox user account who will proxy an actual human for representing
        "the person who wrote and published this feedback." If omitted, uses Alaress Admin.
                

HELP;
            return;
        }

        /* Decide how loudly to announce lock acquisition failure
         *
         * Simply using $lock = $this->acquireLock() will keep you safe, even if you don't
         * specifically check for acquisition
         */
        try {
            $lock = $this->acquireLock(self::LOCK_NAME, self::LOCK_TTL);
        } catch (\RuntimeException $e) {
            return;
        }

        /* Check input file was specified
         */
        $filename = $this->getOpt(self::OPT_LONG_INPUT);

        if (null === $filename) {
            $this->getLogger()->error('no --input file specified.');
            return;
        }

        /* Identify either by assessment title or work type id
         */
        if ('' === trim((string)$this->getOpt(self::OPT_LONG_WORKTYPE)) && '' === trim((string)$this->getOpt(self::OPT_LONG_TITLE))) {
            $this->getLogger()->info('Please specify assessment title or work type id to identify assessments.');

            return;
        }

        $this->getLogger()->info('Script commencing.');

        /* Dependencies
         */
        $userMapper = \Schoolbox\UserMapper::getInstance();
        $folderMapper = \Schoolbox\Resource\Folder\Mapper::getInstance();


        /* Find by title
         */
        $title = null;

        if ($this->getOpt(self::OPT_LONG_TITLE)) {
            $title = trim((string)$this->getOpt(self::OPT_LONG_TITLE));
        }

        /* Find by work type id
         */
        $workTypeId = null;

        if ($this->getOpt(self::OPT_LONG_WORKTYPE)) {
            $workTypeId = (int)$this->getOpt(self::OPT_LONG_WORKTYPE);
        }

        /* Folder code restriction
         */
        $folderCode = null;

        if ($this->getOpt(self::OPT_LONG_FOLDER)) {
            $folderCode = trim((string)$this->getOpt(self::OPT_LONG_FOLDER));
        }

        /* This person "created" all the feedbacks and assessments
         */
        if ($this->getOpt(self::OPT_LONG_CREATOR)) {
            $userId = (int)$this->getOpt(self::OPT_LONG_CREATOR);

            try {
                $creator = $userMapper->findById($userId);
            } catch (\Schoolbox\Mapper\Exception $e) {
                $this->getLogger()->error(sprintf(
                    'Creator user id %s not found. Exiting.',
                    $userId
                ));

                return;
            }
        } else {
            /* We can assume this guy always exists
             */
            $creator = $userMapper->findOneByColumn('username', \Schoolbox\UserMapper::USERNAME_ALARESS_ADMIN);
        }

        /* Specify a publish date
         */
        $publishAt = self::ASSESSMENT_SEM1_DATE_PUBLISH;

        if ($this->getOpt(self::OPT_LONG_PUBLISH_AT)) {
            $publishAt = trim((string)$this->getOpt(self::OPT_LONG_PUBLISH_AT));
        }

        /* This can be ported to the ui version with null and true in place of logger, perform and lock.
         */
        (new Processor(
            $userMapper,
            $folderMapper,
            $title,
            $workTypeId,
            $publishAt,
            $folderCode,
            $this->getLogger(),
            $this->perform(),
        ))->parseFeedbacks($filename, $creator, $lock);

        /* Announce conclusion
         */
        if (!$this->perform()) {
            $this->getLogger()->info('Planning finished.');
        }

        /* Announce lock release
         */
        $this->getLogger()->debug('Releasing lock');

        $lock->release();
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
}

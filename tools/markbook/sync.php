<?php
/**
 * This file iterates over each class and then retrieves all the assessments.  It will create those assessments
 * in the remote markbook system and then proceed to loop over all the grades and load those into the system.
 *
 * This has been designed so it can be run multiple times, if the corresponding assessment already exists it will be
 * updated.
 *
 * @author      James Leckie <jleckie@alaress.com.au>
 * @copyright   Copyright 2015, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */

use Aura\Di\Container as Injector;
use Psr\Log\LoggerInterface;
use React\EventLoop\LoopInterface;
use Schoolbox\Assessment\Activity;
use Schoolbox\Assessment\Component\SubmissionBox\ReturnedWork;
use Schoolbox\Config\ConfigMapper;
use Schoolbox\Learning\Quiz\Attempt\Model as QuizAttempt;
use Schoolbox\Resource\Folder\Mapper as FolderMapper;
use Schoolbox\Resource\Folder\Model as Folder;
use Schoolbox\Assessment\Component\SubmissionBoxMapper;
use Schoolbox\Learning\Quiz\Mapper as QuizMapper;
use Schoolbox\Learning\Quiz\Attempt\Mapper as QuizAttemptMapper;
use Schoolbox\User;

require_once __DIR__ . '/../../conf/core/require.php';
spl_autoload_call('Schoolbox\\Application\\Daemon');

// Get command line options

$options = getopt('p::h::help', array('start::', 'end::', 'year::', 'semester::', 'update-only', 'dry-run'));

// Setup kernel

$kernel = new Schoolbox\Application\Daemon\Kernel('Markbook Syncrhonisation Tool', [
    'verbose'  => [true, true, true, true],
    'help' => isset($options['h']) || isset($options['help']) ? true : false,
    'parentId' => isset($options['p']) ? $options['p'] : 0,
    'year' => isset($options['year']) ? $options['year'] : false,
    'semester' => isset($options['semester']) ? $options['semester'] : false,
    'start' => isset($options['start']) ? $options['start'] : false,
    'end' => isset($options['end']) ? $options['end'] : false,
    'updateOnly' => isset($options['update-only']),
    'dryRun' => isset($options['dry-run'])

]);

/**
 * Recursive function that retrieves the children of a folder that have class codes attached.
 *
 * @param $folderId
 * @param Schoolbox\Resource\Folder\Mapper $folderMapper
 * @param array $catsWithCodes
 * @return Folder[]
 */
function traverse($folderId, $folderMapper, $catsWithCodes = array())
{
    $allFolders = $folderMapper->findByParent($folderId);

    foreach ($allFolders as $currentFolder) {
        if (strlen($currentFolder->getCodeString())) {
            $catsWithCodes[$currentFolder->getId()] = $currentFolder;
        }

        $catsWithCodes = traverse($currentFolder->getId(), $folderMapper, $catsWithCodes);
    }

    return $catsWithCodes;
}

// Get this kernel started

$kernel->run(function ($di, $loop, $log, $opts) {

    /**
     * @var Injector $di
     * @var LoopInterface $loop
     * @var LoggerInterface $log
     * @var array $opts
     */

    // Make sure markbook sync is actually enabled

    $configMapper = ConfigMapper::getInstance();

    if ($configMapper->get('external_markbook') == 0) {
        $log->error("ERROR: External markbook synchronisation is not enabled");
        exit(0);
    }

// Display help if requested

    if ($opts['help']) {
        echo <<<HELP
    
Syncs existing markbook data from Schoolbox into remote system

Usage: sync.php -p=[folderID] [--year=yyyy] [--semester=s] [--start=yyyy-mm-dd] [--end=yyyy-mm-dd] [--dry-run] [--update-only]
    -p=xxx               the ID of parent folder to start the traversal, defaults to 0, format --p=237 
    --year=yyyy          Synergetic FileYear into which to export the markbook, format --year=2016 (must be used with semester parameter)
    --semester=s         Synergetic FileSemester into which to export the markbook, --semester=1 (must be used with year parameter)
    --start=yyyy-mm-dd   work due after this date, format --start=2016-04-20, defaults to current semester
    --end=yyyy-mm-dd     work due before this date, format --end=2016-08-15, defualts to current semester
    --dry-run:           if set, don't actually update anything, but show what would be updated.
    --update-only:       if set, will not create any new items in the remote system, only updates existing settings
                         

HELP;
        exit(1);
    }

    // Get instance of mappers

    $folderMapper = FolderMapper::getInstance();
    $submissionMapper = SubmissionBoxMapper::getInstance();
    $onlineAssessmentMapper = QuizMapper::getInstance();
    $attemptMapper = QuizAttemptMapper::getInstance();

    /** @noinspection PhpDeprecationInspection */
    $markbookHandler = new SubmissionReturn();
    $markbookExternal = new External_Markbook();

    // Determine the time period we want to sync

    $startTime = new Alaress_Date_Format;
    $endTime = new Alaress_Date_Format;

    // Check if we've got a year and semester override set

    if ($opts['year'] && $opts['semester']) {

        // User override year/semester for export

        $year = $opts['year'];
        $semester = $opts['semester'];

    } else if ($opts['year'] || $opts['semester']) {

        $log->error("ERROR: You must specify both a year and semester");
        exit(0);

    } else {

        // Will be overridden with default from Synergetic

        $year = null;
        $semester = null;
    }

    if ($opts['start'] && $opts['end']) {

        // Start and end range passed on commandline, use supplied dates

        try {

            $startTime->setFromFormat($opts['start'], Alaress_Date_Format::DATABASE_DATE_FORMAT);
            $endTime->setFromFormat($opts['end'], Alaress_Date_Format::DATABASE_DATE_FORMAT);

            // Hardcode the time to start/end of the day

            $startTime->setTime(0, 0, 0);
            $endTime->setTime(23, 59, 59);

            if ($startTime->gt($endTime)) {
                $log->error("ERROR: Invalid date range supplied, end date must be later than the start date");
            }

        } catch (InvalidArgumentException $e) {

            // Creation of date/time objects failed, format must be wrong

            $log->error("ERROR: Invalid date supplied, please check date format matches (" . Alaress_Date_Format::DATABASE_DATE_FORMAT . ")");
            exit(0);
        }

    } elseif (date('n') > 6) {

        // If we have gone past June- assume a fake "Semester 2" and use the last six months of the year

        $startTime->setDate(date('Y'), 7, 1)->setTime(0, 0, 0);
        $endTime->setDate(date('Y'), 12, 30)->setTime(23, 59, 59);

    } else {

        // Otherwise use a fake "Semester 1" and use the first six months of the year

        $startTime->setDate(date('Y'), 1, 1)->setTime(0, 0, 0);
        $endTime->setDate(date('Y'), 6, 30)->setTime(23, 59, 59);
    }

    // Retrieve all of the folders that we are going to traverse

    $folderList = [];

    // If we aren't starting at the root folder, include the actual root folder

    if ($opts['parentId'] != 0) {
        $folderList[$opts['parentId']] = $folderMapper->findById($opts['parentId']);
    }

    $folderList = $folderList + traverse($opts['parentId'], $folderMapper);

    // Make sure we got a result

    if (count($folderList) == 0) {
        $log->error('ERROR: No matching folders were found');
        exit(0);
    }

    // Iterate all assessments in these folders

    $log->info('Markbook synchronisation starting');
    $log->info("Syncing work between {$startTime->getLocalShort()} and {$endTime->getLocalShort()}");

    foreach ($folderList as $folder) {

        $action = str_pad("Processing folder: ", 34, " ");

        if (!empty($folder->getCodeString())) {

            $code = "{$folder->getId()} - {$folder->getCodeString()}";
        } else {
            $code = "{$folder->getId()}";
        }

        $log->info("{$action}{$folder->getName()} ({$code})");

        // Process submission boxes

        $submissionBoxList = $submissionMapper->getDueActivities(
            [$folder->getId()],
            $startTime->getUnixTimestamp(),
            $endTime->getUnixTimestamp()
        );

        // Check if we found anything

        if ($submissionBoxList->count() == 0) {

            // Might want to log something

        } else {

            // Iterate all submissions

            /** @var Activity $submissionBox */
            foreach ($submissionBoxList as $submissionBox) {

                $action = str_pad("Submission Box:", 30, " ");

                $log->info("    {$action}{$submissionBox->getName()} ({$submissionBox->getId()})");

                // Check if we are allowed to create new markbook entries

                if (!$opts['updateOnly']) {

                    // Creation allowed

                    $action = str_pad("Action:", 30, " ");
                    $log->info("    {$action}Create/Update");

                    if (!$opts['dryRun']) {
                        $markbookExternal->createOrUpdateAssessment($submissionBox, $year, $semester);
                    }

                } else {

                    // No creation of new assessments in markbook is allowed

                    $action = str_pad("Action:", 30, " ");
                    $log->info("    {$action}Update Only");

                    if (!$opts['dryRun']) {
                        $markbookExternal->updateAssessment($submissionBox, $year, $semester);
                    }
                }

                // Retrieve the class list for the submission box

                $action = str_pad("Submission Returns Found:", 30, " ");

                $classList = $markbookHandler->getStudentListByBoxId($submissionBox->getId());
                $log->info("    {$action}" . count($classList));

                /** @var User $student */
                foreach ($classList as $student) {

                    $result = $markbookHandler->getByUser($student['uid'], $submissionBox->getId());

                    if (empty($result)) {
                        continue;
                    }

                    // Create some fake user objects

                    $owner = new User();
                    $owner->setId($result[0]['owner']);

                    $marker = new User();
                    $marker->setId($result[0]['marker']);

                    // Pretending to be a mapper even though we don't have one yet

                    $return = new ReturnedWork();
                    $return->setSubmissionBox($submissionBox->getSubmissionBox());
                    $return->setNormalizedMark($result[0]['norm_mark']);
                    $return->setMarker($marker);
                    $return->setOwner($owner);
                    $return->setComment($result[0]['comment']);

                    $mark = $result[0]['norm_mark'];

                    if (empty($mark)) {
                        $mark = 'Unmarked';
                    }

                    $name = str_pad("{$student['firstname']} {$student['lastname']}:", 26, " ");
                    $log->info("        {$name}{$mark}");

                    if (!$opts['dryRun']) {
                        $markbookExternal->setMark($return, $year, $semester);
                    }
                }
            }
        }

        // Process quizzes

        $quizList = $onlineAssessmentMapper->getDueActivities(
            [$folder->getId()],
            $startTime->getUnixTimestamp(),
            $endTime->getUnixTimestamp()
        );

        if ($quizList->count() == 0) {

            // Might want to log something

        } else {

            /** @var Activity $activity */
            foreach ($quizList as $activity) {

                $quiz = $activity->getAssessment();

                $action = str_pad("Quiz: ", 30, " ");

                $log->info("    {$action}{$quiz->getTitle()} ({$quiz->getId()})");

                $markbook = $markbookExternal->getAssessment($activity, $year, $semester);

                if (is_array($markbook) && count($markbook) >= 1) {

                    $action = str_pad("Updating assessment:", 30, " ");

                    $log->info("    {$action}{$activity->getName()}");

                    if (!$opts['dryRun']) {
                        $markbookExternal->updateAssessment($activity, $year, $semester);
                    }

                } elseif (!$opts['updateOnly']) {

                    $action = str_pad("Creating assessment:", 30, " ");

                    $log->info("    {$action}{$activity->getName()}");

                    if (!$opts['dryRun']) {
                        $markbookExternal->createAssessment($activity, $year, $semester);
                    }

                } else {

                    // Markbook didn't exist in external and we are in update only mode, nothing else we can do

                    $action = str_pad("Skipping:", 30, " ");

                    $log->warning("{$action}{$activity->getName()}");
                    continue;
                }

                $action = str_pad("Students Found:", 30, " ");

                $classList = $attemptMapper->getAllAttempts($quiz->getId(), array('most_recent' => true));
                $log->info("    {$action}" . count($classList));

                foreach ($classList as $studentID => $result) {

                    // Pretending to be a mapper, even though I dont have one yet.
                    $return = new QuizAttempt();
                    $return->setQuiz($quiz);
                    $return->setId($result[0]['attempt_id']);
                    $return->setUser(new User);
                    $return->getUser()->setId($studentID);

                    if (!$opts['dryRun']) {
                        $markbookExternal->setMark($return, $year, $semester);
                    }
                }
            }
        }
    }

});
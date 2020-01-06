#!/usr/bin/php
<?php
/**
 * This file iterates over each class and then retrieves all the assessments.  It will create those assessments
 * in the remote markbook system and then proceed to loop over all the grades and load those into the system.
 *
 * This has been designed so it can be run multiple times, if the corresponding assessment already exists it will be
 * updated.
 *
 * @stolenfrom  James Leckie <jleckie@alaress.com.au>
 * @author      Damian Sloane <dsloane@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

use Aura\Di\Container as Injector;
use Psr\Log\LoggerInterface;
use React\EventLoop\LoopInterface;
use Schoolbox\Application\Daemon;
use Schoolbox\IConfigLoader;
use Schoolbox\Learning\Assessment\DataModel\MapperProxy as AssessmentDataSource;
use Schoolbox\Resource\Folder\Mapper as FolderMapper;
use Schoolbox\Resource\Folder\Model as Folder;

require_once __DIR__ . '/../../conf/core/require.php';

$kernel = new Daemon\Kernel(
    'Schoolbox Markbook Sync Script',
    [
        Daemon\Kernel::OPTION_APP_SHORT_OPTS => 'vp::h::',
        Daemon\Kernel::OPTION_APP_LONG_OPTS  => [
            'help',
            'start::',
            'end::',
            'year::',
            'semester::',
            'update-only',
            'dry-run',
        ],
    ]
);

/**
 * Recursive function that retrieves the children of a folder that have class codes attached.
 *
 * @param                                  $folderId
 * @param Schoolbox\Resource\Folder\Mapper $folderMapper
 * @param array                            $catsWithCodes
 * @return Folder[]
 */
function traverse($folderId, $folderMapper, $catsWithCodes = [])
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

/**
 * Display help text
 *
 * @param IConfigLoader $config
 */
function help($config)
{
    $additionalParameters = "";

    // Synergetic receives some additional options around FileYear/FileSemester

    if ($config->get('external_type') === 'synergetic') {
        $additionalParameters .= "    --year=yyyy          Synergetic FileYear into which to export the markbook and student marks, format --year=2016 (must be used with semester parameter)\n";
        $additionalParameters .= "    --semester=s         Synergetic FileSemester into which to export the markbook and student marks, --semester=1 (must be used with year parameter)\n";
        $addParamsShort       = "[--year=yyyy] [--semester=s] ";
    }

    // Get pretty uppercased SIS name

    $remoteSystemName = ucwords($config->get('external_type'));

    echo <<<HELP

Schoolbox Markbook Sync Script

Description:   Syncs existing markbook data from Schoolbox into {$remoteSystemName}
Usage:         php sync.php -p=[folderID] {$addParamsShort}[--start=yyyy-mm-dd] [--end=yyyy-mm-dd] [--dry-run] [--update-only]

    -p=xxx               the Schoolbox ID of parent folder to start the traversal, defaults to 0, format -p=237
    --start=yyyy-mm-dd   Only sync work due after this date, format --start=2016-04-20, defaults to current semester
    --end=yyyy-mm-dd     Only sync work due before this date, format --end=2016-08-15, default to current semester
    --dry-run:           if set, does not actually update anything, but shows what would be updated
    --update-only:       if set, will not create any new items in the remote system, only updates existing markbooks
{$additionalParameters}

HELP;

}

$kernel->run(static function (Injector $di, LoopInterface $loop, LoggerInterface $log, array $opts) {

    $help       = isset($opts['h']) || isset($opts['help']);
    $parentId   = $opts['p'] ?? 0;
    $year       = $opts['year'] ?? null;
    $semester   = $opts['semester'] ?? null;
    $start      = $opts['start'] ?? false;
    $end        = $opts['end'] ?? false;
    $updateOnly = isset($opts['update-only']);
    $dryRun     = isset($opts['dry-run']);

    // Make sure markbook sync is actually enabled
    /** @var IConfigLoader $config */
    $config = $di->get('config');
    if (!$config->get('external_markbook')) {
        $log->error("ERROR: External markbook synchronisation is not enabled in Schoolbox. Please enable in your Schoolbox administrator settings and try again.");
        exit(1);
    }

    if ($help) {
        help($config);
        exit(0);
    }

    /** @var AssessmentDataSource $assessmentDataSource */
    $assessmentDataSource = $di->get('assessmentDataSource');

    // Get instance of mappers

    $folderMapper = FolderMapper::getInstance();

    // Synergetic Markbook uses FileYear/FileSemester and exposes command line parameters not available to other connectors.
    if ($config->get('external_type') === 'synergetic') {
        try {
            $markbookExternal = new External_Synergetic_Markbook($year, $semester);
        } catch (InvalidArgumentException $e) {
            $log->error("ERROR: {$e->getMessage()}");
            help($config);
            exit(1);
        }
    } else {
        $markbookExternal = new External_Markbook();
    }

    // Determine the time period we want to sync

    $startTime = new Alaress_Date_Format;
    $endTime   = new Alaress_Date_Format;

    if ($start && $end) {

        // Start and end range passed on commandline, use supplied dates

        try {

            $startTime->setFromFormat($start, Alaress_Date_Format::DATABASE_DATE_FORMAT);
            $endTime->setFromFormat($end, Alaress_Date_Format::DATABASE_DATE_FORMAT);

            // Hardcode the time to start/end of the day

            $startTime->setTime(0, 0, 0);
            $endTime->setTime(23, 59, 59);

            if ($startTime->gt($endTime)) {
                $log->error("ERROR: Invalid date range supplied, end date must be later than the start date.");
            }

        } catch (InvalidArgumentException $e) {

            // Creation of date/time objects failed, format must be wrong

            $log->error("ERROR: Invalid date supplied, please check date format matches (" . Alaress_Date_Format::DATABASE_DATE_FORMAT . ").");
            exit(1);
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
    /** @var Folder[] $folderList */
    $folderList = [];

    // If we aren't starting at the root folder, include the actual root folder

    if ($parentId) {
        $folderList[$parentId] = $folderMapper->findById($parentId);
    }

    /** @noinspection AdditionOperationOnArraysInspection */
    $folderList += traverse($parentId, $folderMapper);

    // Make sure we got a result

    if (count($folderList) === 0) {
        $log->info('No matching folders were found, nothing to sync.');
        exit(0);
    }

    // Iterate all assessments in these folders

    $log->info('Markbook synchronisation starting...');
    $log->info("Syncing work due between {$startTime->getLocalShort()} and {$endTime->getLocalShort()}...");


    foreach ($folderList as $folder) {

        $action = str_pad("Processing folder: ", 34, " ");

        if (!empty($folder->getCodeString())) {

            $code = "{$folder->getId()} - {$folder->getCodeString()}";
        } else {
            $code = "{$folder->getId()}";
        }

        $log->info("{$action}{$folder->getName()} ({$code})");

        // Process assessments

        $assessmentList = $assessmentDataSource->findAllByFolderIdForReview(
            $folder->getId(),
            null,
            [
                'due_after'  => $startTime,
                'due_before' => $endTime,
            ]
        );

        foreach ($assessmentList as $assessment) {
            if (!$markbookExternal->isSyncable($assessment)) {
                continue;
            }

            $action = str_pad("Assessment:", 30, " ");

            $log->info("    {$action}{$assessment->getTitle()} ({$assessment->getId()})");

            // Check if we are allowed to create new markbook entries

            if (!$updateOnly) {

                // Creation allowed

                $action = str_pad("Action:", 30, " ");
                $log->info("    {$action}Create/Update");

                if (!$dryRun) {
                    $markbookExternal->syncAssessment($assessment);
                }

            } else {

                // No creation of new assessments in markbook is allowed

                $action = str_pad("Action:", 30, " ");
                $log->info("    {$action}Update Only");

                if (!$dryRun && $markbookExternal->hasAssessment($assessment)) {
                    $markbookExternal->syncAssessment($assessment);
                }
            }

            // Retrieve the class list for the submission box

            $action        = str_pad("Assessment Feedback Found:", 30, " ");
            $feedbackCount = 0;
            foreach ($assessment->getAllParticipants() as $participant) {
                if ($participant->hasPublishedFeedback()) {
                    $feedbackCount++;
                }
            }

            $log->info("    {$action}" . $feedbackCount);

            foreach ($assessment->getAllParticipants() as $participant) {
                $feedback = $participant->findMostRecentInstructorGivenFeedback();
                if (!$feedback) {
                    continue;
                }

                $student = $participant->getUser();
                $name    = str_pad("{$student->getFirstname()} {$student->getLastname()} ({$student->getExternalId()}):", 26, " ");
                $log->info("        {$name}{$feedback->getHumanReadableMark()}");

                if (!$dryRun) {
                    $markbookExternal->syncFeedback($participant->findMostRecentInstructorGivenFeedback());
                }
            }
        }
    }

});

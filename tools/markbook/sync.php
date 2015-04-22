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
 */

use Schoolbox\Resource\Folder\Mapper as FolderMapper;
use Schoolbox\Assessment\Component\SubmissionBoxMapper;
use Schoolbox\Assessment\Component\SubmissionBox\ReturnedWorkMapper;
use Alaress\Lumberjack\Logger;

require_once __DIR__ . '/../../include/Bootstrap.php';

$options          = getopt('p::h', array('update-only', 'dry-run'));
$parentId         = isset($options['p']) ? $options['p'] : 0;
$updateOnly   = isset($options['update-only']);
$dryRun           = isset($options['dry-run']);

if (isset($options['h'])) {
    echo <<<HELP
Syncs existing markbook data from Schoolbox into remote system

Usage: sync.php -p[folderID] [--dry-run] [--update-only]
    -p:                  the ID of parent folder to start the traversal, defaults to 0
    --dry-run:           if set, don't actually update anything, but show what
                         would be updated.
    --update-only:       if set, will not create any new items in the remote system, only
                         updates existing settings

HELP;
    exit(1);
}
$folderMapper = new FolderMapper();
$submissionMapper = new SubmissionBoxMapper();
$resultMapper = new ReturnedWorkMapper();
$markbookHandler = new SubmissionReturn();

$markbookExternal = new External_Markbook();

$log = new Logger;

/* Time period will be current semester only */
$now = Alaress_Date_Format::now();
$startTime = new Alaress_Date_Format;
$endTime = new Alaress_Date_Format;
if($now->getUsingFormat('m') > 6) {
    $startTime->setDate(date('Y'),7,1)->setTime(0,0,0);
    $endTime->setDate(date('Y'),12,30)->setTime(23,59,59);
}
else {
    $startTime->setDate(date('Y'),1,1)->setTime(0,0,0);
    $endTime->setDate(date('Y'),6,30)->setTime(23,59,59);
}

$log->notice("Only syncing work between {$startTime->getLocalShort()} and {$endTime->getLocalShort()}", array());

$categoryList = traverse($parentId, $folderMapper);

foreach($categoryList as $category) {
    $log->notice("Processing assessments from: {$category->getName()} ({$category->getCodeString()})", array());
    $submissionBoxList = $submissionMapper->getDueActivities(array($category->getId()),$startTime->getUnixTimestamp(), $endTime->getUnixTimestamp());
    foreach($submissionBoxList as $submissionBox) {
        $markbook = $markbookExternal->getAssessment($submissionBox->getSubmissionBox());

        if(is_array($markbook)) {
            $log->info("Updating existing assessment: {$submissionBox->getName()}",array());
            if(!$dryRun) {
                $markbookExternal->updateAssessment($submissionBox->getSubmissionBox());
            }
        }
        elseif( !$updateOnly) {
            $log->info("Creating new assessment: {$submissionBox->getName()}", array());
            if(!$dryRun) {
                $markbookExternal->createAssessment($submissionBox->getSubmissionBox());
            }
        }
        else {
            $log->warning("Skipping creation in update only mode: {$submissionBox->getName()}",array());
            continue;
        }

        $classList = $markbookHandler->getStudentListByBoxId($submissionBox->getId());
        $log->notice("Setting marks for {count($classList)} students", array());
        foreach($classList as $student) {
            $result = $markbookHandler->getByUser($student['uid'], $submissionBox->getId());

            // Pretending to be a mapper, even though I dont have one yet.
            $return = new Schoolbox\Assessment\Component\SubmissionBox\ReturnedWork();
            $return->setSubmissionBox($submissionBox->getSubmissionBox());
            $return->setNormalizedMark($result[0]['norm_mark']);
            $return->setMarker($result[0]['marker']);
            $return->setOwner(new \Schoolbox\User);
            $return->getOwner()->setId($result[0]['owner']);
            $return->setComment($result[0]['comment']);

            if(!$dryRun) {
                $markbookExternal->setMark($return);
            }
        }
    }
}




/**
 * Recursive function that retrieves the children of a category that have class codes attached.
 *
 * @param $categoryId
 * @param Schoolbox\Resource\Folder\Mapper $folderMapper
 * @param array $catsWithCodes
 * @return array
 */
function traverse($categoryId, $folderMapper, $catsWithCodes = array())
{
    $allFolders = $folderMapper->findByParent($categoryId);

    foreach ($allFolders as $currentFolder) {
        if (strlen($currentFolder->getCodeString())) {
            $catsWithCodes[$currentFolder->getId()] = $currentFolder;
        }

        $catsWithCodes = traverse($currentFolder->getId(), $folderMapper, $catsWithCodes);
    }

    return $catsWithCodes;
}
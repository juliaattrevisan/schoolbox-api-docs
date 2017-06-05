<?php

/**
 * This script will take plagscan documents in an error state and attempt to resubmit them to
 * plagscan so they can be reprocessed.  Use this if you have lots of files in the -1 state.
 *
 * @author           James Leckie <jleckie@alaress.com.au>
 * @copyright        Copyright 2015, Alaress Pty Ltd
 *
 */


use \Schoolbox\Learning\Assessment\DueWork\Submission\File\Plagscan\Model as Plagscan;
use \Schoolbox\Learning\Assessment\DueWork\Submission\File\Plagscan\Mapper as PlagscanMapper;

use \Schoolbox\Assessment\Component\SubmissionBox\Submission\SingleFile\Model as StudentSubmissionFile;
use \Schoolbox\Assessment\Component\SubmissionBox\Submission\SingleFile\Mapper as StudentSubmissionFileMapper;

use \Schoolbox\Learning\Assessment\DueWork\Submission\File\Model as SubmissionFile;

require_once __DIR__ . '/../../conf/core/require.php';
spl_autoload_call('Schoolbox\\Application\\Daemon');
$kernel = new \Schoolbox\Application\Daemon\Kernel('Plagscan Scanner', array('level'=>200));
$log = $kernel->logger();

$mapper = PlagscanMapper::getInstance();

if (!$mapper->getConnector()) {

    /* Plagscan not configured - nothing to do
     */
    $log->warning("Plagscan scan did not run: username, api key and/or access token is not configured correctly");
    exit();
}

/** @var Plagscan $currentDocument */
$allDocuments = $mapper->findByColumn('status', Plagscan::STATUS_UNSCANNABLE, [
    'order' => [
        'student_submission_file_id' => 'ASC'
    ]
]);

$log->info("Plagscan now re-processing {$allDocuments->count()} documents");

$numUpdated = 0;

foreach ($allDocuments as $currentDocument) {
    $prefix = "Plagscan Record {$currentDocument->getId()}";

    try {
        $plagscanId = null;

        /** @var StudentSubmissionFile $studentSubmissionFile */
        $studentSubmissionFile = StudentSubmissionFileMapper::getInstance()->findById($currentDocument->getStudentSubmissionFileId());

        /* Convert to new format
         */
        $submissionFile = new SubmissionFile();
        $submissionFile->setId($studentSubmissionFile->getId());
        $submissionFile->setStorage($studentSubmissionFile->getStorage());

        /* This returns a plagscan id upon transmission
         */
        $log->info("{$prefix}: transmitting student_submission_file_id {$currentDocument->getStudentSubmissionFileId()}");

        /* Figure out what the filename will be
         */
        $filename = $mapper->plagscanFilename($submissionFile);

        if ($filename !== null) {

            /* Privatise all previous incarnations of this (assessment, student) tuple to
             * prevent documents from being scanned against older versions of themselves
             */
            $previousPlagscans = $mapper->findPreviousIncarnationsOf($submissionFile->getId());

            /** @var Plagscan $currentPlagscan */
            foreach($previousPlagscans as $currentPlagscan) {
                $log->info("{$prefix}: privatising {$currentPlagscan->getPlagscanId()} - superseded by student_submission_file_id {$currentDocument->getStudentSubmissionFileId()}");
                $mapper->privatise($currentPlagscan);
            }

            /* Transmit the new file
             */
            $plagscanId = $mapper->getConnector()->transmit($submissionFile, $filename);

            /* Update the db with this new plagscan id
             */
            if ($plagscanId) {
                $log->info("{$prefix}: queueing student_submission_file_id {$currentDocument->getStudentSubmissionFileId()}");

                $currentDocument->setPlagscanId($plagscanId);
                $currentDocument->setStatus(Plagscan::STATUS_QUEUED);
                $currentDocument->setUpdatedAt(\Alaress_Date_Format::now());
                $mapper->save($currentDocument);

                $log->info("{$prefix}: set to pending status");
                $numUpdated++;
            } else {
                $log->warning("{$prefix}: transmission did not return a plagscanId for submission file {$currentDocument->getStudentSubmissionFileId()}");
            }
        }
        else {
            $log->warning("{$prefix}: unknown join criteria for file/assessment/user");
        }
    }
    catch (Schoolbox\Mapper\Exception $e) {
        $log->warning("{$prefix}: unable to find student_submission_file_id {$currentDocument->getStudentSubmissionFileId()}");

        /* Couldn't find the file or any of its pieces
         * So we continue instead, ignoring this plagscan doc
         */
        continue;
    }
    catch (\DomainException $e) {

        /* Could not transmit this file to plagscan for some reason
         */
        $log->error("{$prefix}: could not transmit student_submission_file_id {$currentDocument->getStudentSubmissionFileId()}; {$e->getMessage()}");
    }
    catch (\RuntimeException $e) {

        /* Could not transmit this file to plagscan for some reason
         */
        $log->error("{$prefix}: could not transmit student_submission_file_id {$currentDocument->getStudentSubmissionFileId()}; {$e->getMessage()}");
    }
}

$log->info("Plagscan transmission completed. {$numUpdated} document(s) were resent and queued");

/* Also tell me how many died
 */
$numFailed = $allDocuments->count() - $numUpdated;

if ($numFailed) {
    $log->info("{$numFailed} document(s) failed transmitting");
}


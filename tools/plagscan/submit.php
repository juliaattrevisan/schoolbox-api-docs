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

if (!$mapper->getGateway()) {

    /* Plagscan not configured - nothing to do
     */
    $log->warning("Plagscan scan did not run: username, api key and/or access token is not configured correctly");
    exit();
}

/** @var Plagscan $currentDocument */
$allDocuments = $mapper->findByColumn('status', Plagscan::STATUS_UNSCANNABLE);

$log->info("Plagscan now re-processing {$allDocuments->count()} documents");

$numUpdated = 0;

foreach ($allDocuments as $currentDocument) {
    $prefix = "Plagscan Record {$currentDocument->getId()}";

    try {
        /** @var StudentSubmissionFile $studentSubmissionFile */
        $studentSubmissionFile = StudentSubmissionFileMapper::getInstance()->findById($currentDocument->getStudentSubmissionFileId());

        /* Convert to new format
         */
        $submissionFile = new SubmissionFile();
        $submissionFile->setStorage($studentSubmissionFile->getStorage());

        /* This returns a plagscan id upon transmission
         */
        $log->info("{$prefix}: transmitting student_submission_file_id {$currentDocument->getStudentSubmissionFileId()}");

        $plagscanId = $mapper->getGateway()->transmit($submissionFile);

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
        $log->warning("{$prefix}: could not transmit student_submission_file_id {$currentDocument->getStudentSubmissionFileId()}; {$e->getMessage()}");
    }
}

$log->info("Plagscan transmission completed. {$numUpdated} document(s) were resent and queued");

/* Also tell me how many died
 */
$numFailed = $allDocuments->count() - $numUpdated;

if ($numFailed) {
    $log->info("{$numFailed} document(s) failed transmitting");
}


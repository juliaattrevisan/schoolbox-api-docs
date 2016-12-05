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

try {
    $mapper = PlagscanMapper::getInstance();
}
catch (\DomainException $e) {

    /* Plagscan not configured - nothing to do
     */
    $log->warning("Plagscan scan did not run: username/api key is not configured correctly");
    exit();
}

$allDocuments = $mapper->findByColumn('status', Plagscan::STATUS_UNSCANNABLE);

$log->info("Plagscan now re-processing {$allDocuments->count()} documents");

$numUpdated = 0;

/** @var Plagscan $currentDocument */
foreach ($allDocuments as $currentDocument) {
    $prefix = "Plagscan_id {$currentDocument->getPlagscanId()}";

    try {
        /** @var StudentSubmissionFile $studentSubmissionFile */
        $studentSubmissionFile = StudentSubmissionFileMapper::getInstance()->findById($currentDocument->getStudentSubmissionFileId());

        /* Convert to new format
         */
        $submissionFile = new SubmissionFile();
        $submissionFile->setStorage($studentSubmissionFile->getStorage());

        /* Send it back to plag which returns some other int
         * that we don't seem to keep anywhere - it's some kind
         * of internal id for Plagscan itself
         */
        $log->info("{$prefix}: resubmitting student_submission_file_id {$currentDocument->getStudentSubmissionFileId()}");

        $documentId = $mapper->getGateway()->submit($submissionFile);

        if (!empty($documentId)) {

            $log->info("{$prefix}: checking that plagscan has {$currentDocument->getStudentSubmissionFileId()}");
            $response = $mapper->getGateway()->check($documentId);

            if ($response) {
                $currentDocument->setPlagscanId($documentId);
                $currentDocument->setStatus(Plagscan::STATUS_QUEUED);
                $mapper->save($currentDocument);

                $log->info("{$prefix}: set document {$currentDocument->getId()} to pending status");
            }
            else {
                $log->warning("{$prefix}: check of document {$currentDocument->getId()} failed for submission file {$currentDocument->getStudentSubmissionFileId()}");
            }
        }
        else {
            $log->warning("{$prefix}: submission of document {$currentDocument->getId()} failed for submission file {$currentDocument->getStudentSubmissionFileId()}");
        }
    }
    catch (Schoolbox\Mapper\Exception $e) {
        $log->warning("{$prefix}: unable to find student_submission_file_id {$currentDocument->getStudentSubmissionFileId()}");

        /* Couldn't find the file or any of its pieces
         * So we continue instead, ignoring this plagscan doc
         */
        continue;
    }
}


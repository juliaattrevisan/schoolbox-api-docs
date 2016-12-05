<?php

/**
 * This script will take plagscan documents in an error state and attempt to resubmit them to
 * plagscan so they can be reprocessed.  Use this if you have lots of files in the -1 state.
 *
 * @author           James Leckie <jleckie@alaress.com.au>
 * @copyright        Copyright 2015, Alaress Pty Ltd
 *
 */

use Schoolbox\Assessment\Component\SubmissionBox\Submission\SubmissionMapper;
use \Schoolbox\Learning\Assessment\DueWork\Submission\File\Plagscan\Model as Plagscan;
use \Schoolbox\Learning\Assessment\DueWork\Submission\File\Plagscan\Mapper as PlagscanMapper;

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
    $log->warning("Plagscan scan did not run: username/api key is not configured correctly.");
    exit();
}

$allDocuments = $mapper->findByColumn('status', Plagscan::STATUS_UNSCANNABLE);

$log->info("Plagscan now re-processing {$allDocuments->count()} documents.");

$numUpdated = 0;

foreach ($allDocuments as $currentDocument) {
    try {
        $submission = SubmissionMapper::getInstance()->findById($currentDocument->getFile()->getId());

        // TODO fix this to work with multiple files
        $documentId = $mapper->getGateway()->submit($submission);

        if (!empty($documentId)) {
            $response = $mapper->getGateway()->check($documentId);

            if($response) {
                $currentDocument->setPlagscanId($documentId);
                $currentDocument->setStatus(Plagscan::STATUS_QUEUED);
                $mapper->save($currentDocument);

                $log->info("Resubmitted document ".$currentDocument->getId()." for processing: ".$documentId);
            }
            else {
                $log->warning("Check of document failed ".$currentDocument->getId().": ".$response);

            }

        }
        else {
            $log->warning("Submission of document ".$currentDocument->getId()." failed: ".$documentId);
        }
    } catch (Schoolbox\Mapper\Exception $e) {
        $log->warning("Unable to find submission ".$currentDocument->getFile()->getId());
    }
}


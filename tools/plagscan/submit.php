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








$docs = $mapper->findByColumn('status', Plagscan::STATUS_UNSCANNABLE);

$log->info("Plagscan resubmission started processing {$docs->count()} docs");

$gateway = $mapper->getGateway();

/* TODO Repair ALL of this
 */
foreach ($docs as $doc) {

    try{
        $submission = SubmissionMapper::getInstance()->findById($doc->getFile()->getId());

        // TODO fix this to work with multiple files
        $documentId = $gateway->submit($submission);

        if (!empty($documentId)) {
            $response = $gateway->check($documentId);

            if($response) {
                $doc->setPlagscanId($documentId);
                $doc->setStatus(Plagscan::STATUS_QUEUED);
                $mapper->save($doc);

                $log->info("Resubmitted document ".$doc->getId()." for processing: ".$documentId);
            }
            else {
                $log->warning("Check of document failed ".$doc->getId().": ".$response);

            }

        }
        else {
            $log->warning("Submission of document ".$doc->getId()." failed: ".$documentId);
        }
    } catch (Schoolbox\Mapper\Exception $e) {
        $log->warning("Unable to find submission ".$doc->getFile()->getId());
    }

}


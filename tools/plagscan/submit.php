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

require_once __DIR__ . '/../../conf/core/require.php';
spl_autoload_call('Schoolbox\\Application\\Daemon');
$kernel = new \Schoolbox\Application\Daemon\Kernel('Plagscan Scanner', array('level'=>200));
$log = $kernel->logger();

$mapper = Plagscan_DocumentMapper::getInstance();
$docs   = $mapper->findByColumn('status', \Schoolbox\Learning\Assessment\DueWork\Submission\File\Plagscan::STATUS_UNSCANNABLE);

//
// Hit the Plagscan API for each document.
$key      = Config::get('plagscan_api_key');
$username = Config::get('plagscan_username');
if (empty($username) || empty($key)) {
    $log->warning("Plagscan scan not run, as Plagscan is not configured");
    exit;
}


$log->info("Plagscan resubmission started processing ".$docs->count()." docs");
$gateway      = new Plagscan_Gateway($username, $key);

foreach ($docs as $doc) {

    try{
        $submission = SubmissionMapper::getInstance()->findById($doc->getFile()->getId());

        // TODO fix this to work with multiple files
        $documentId = $gateway->submit($submission);

        if (!empty($documentId)) {
            $response = $gateway->check($documentId);

            if($response) {
                $doc->setPlagscanId($documentId);
                $doc->setStatus(\Schoolbox\Learning\Assessment\DueWork\Submission\File\Plagscan::STATUS_QUEUED);
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


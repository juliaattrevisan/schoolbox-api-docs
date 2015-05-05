<?php

/**
 * This script will take plagscan documents in an error state and attempt to resubmit them to
 * plagscan so they can be reprocessed.  Use this if you have lots of files in the -1 state.
 *
 * @author           James Leckie <jleckie@alaress.com.au>
 * @copyright        Copyright 2015, Alaress Pty Ltd
 *
 */

require_once(dirname(__FILE__) . '/../../include/Bootstrap.php');

use Alaress\Lumberjack\Logger;
use Schoolbox\Assessment\Component\SubmissionBox\Submission\FileMapper;

$log = new Logger;
$mapper = Plagscan_DocumentMapper::getInstance();
$docs   = $mapper->findByColumn('status', Plagscan_Document::STATUS_UNSCANNABLE);

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
        $submission = FileMapper::getInstance()->findById($doc->getFile()->getId());

        $documentId = $gateway->submit($submission);

        if (!empty($documentId)) {
            $response = $gateway->check($documentId);

            if($response) {
                $doc->setPlagscanId($documentId);
                $doc->setStatus(Plagscan_Document::STATUS_QUEUED);
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


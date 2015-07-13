<?php
/**
 * This file will scan for student submissions that have no uuid and are accepted by crocodoc and attempt to load
 * them into crocodoc so they are ready for teachers to mark inline
 *
 * @author      James Leckie <jleckie@alaress.com.au>
 * @copyright   Copyright 2015, Alaress Pty Ltd
 */
require_once __DIR__ . '/../../include/Bootstrap.php';


use Schoolbox\Assessment\Component\SubmissionBox\Submission\FileMapper;
use Crocodoc\Document;
use Alaress\Lumberjack\Logger;

$logger = new Logger;
if(Config::get('crocodoc_api_token')) {

    $pendingUpload = FileMapper::getInstance()->getCrocodoc();

    foreach($pendingUpload as $submission) {
        $logger->notice("Uploading ".$submission->getFileName()."(".$submission->getId().")");
        try{
            $document = new Document($submission);
            $result = $document->upload();
            if ($result['errors']) {
                $logger->error($result['errors']);
                switch ($result['responseCode']) {
                    case 401:
                        // This is an authentication error, caused by a wrong token.
                        // Give up because the token is not going to fix itself.
                        $logger->error('Authentication error received - exiting!');
                        exit;
                    default:
                        break;
                }
            }
            else {
                $logger->notice("Upload completed successfully");
            }
        } catch (DomainException $e) {
            $logger->error($e->getMessage());
        }
    }
} else {
    $logger->warning("Crocodoc API token not defined - exiting!");
}

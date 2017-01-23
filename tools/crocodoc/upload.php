<?php
/**
 * This file will scan for student submissions that have no uuid and are accepted by crocodoc and attempt to load
 * them into crocodoc so they are ready for teachers to mark inline
 *
 * @author      James Leckie <jleckie@alaress.com.au>
 * @copyright   Copyright 2015, Alaress Pty Ltd
 */
use Schoolbox\Assessment\Component\SubmissionBox\Submission\SubmissionMapper;
use Schoolbox\Learning\InlineGrader\Authenticator;
use Schoolbox\Assessment\Component\SubmissionBox\Submission\SingleFile\Mapper as SingleFileMapper;
use Schoolbox\Learning\InlineGrader\Asynchronous\Upload as AsynchronousCrocodocUpload;

use Crocodoc\Document;

require_once __DIR__ . '/../../conf/core/require.php';
spl_autoload_call('Schoolbox\\Application\\Daemon');
$kernel = new \Schoolbox\Application\Daemon\Kernel('Crocodoc Uploader', array('level'=>200));
$logger = $kernel->logger();

//if(Config::get('crocodoc_api_token')) {
//
    $pendingUpload = SubmissionMapper::getInstance()->getCrocodoc();
//
//    foreach($pendingUpload as $submission) {
//        $logger->notice("Uploading ".$submission->getFileName()."(".$submission->getId().")");
//        try{
//            $document = new Document($submission);
//            $result = $document->upload();
//            if ($result['errors']) {
//                $logger->error($result['errors']);
//                switch ($result['responseCode']) {
//                    case 401:
//                        // This is an authentication error, caused by a wrong token.
//                        // Give up because the token is not going to fix itself.
//                        $logger->error('Authentication error received - exiting!');
//                        exit;
//                    default:
//                        break;
//                }
//            }
//            else {
//                $logger->notice("Upload completed successfully");
//            }
//        } catch (\RangeException $e) {
//            $logger->error($e->getMessage());
//        }
//    }
//} else {
//    $logger->warning("Crocodoc API token not defined - exiting!");
//}
//
//



var_dump(get_class($logger));



try {
    $authenticator = new Authenticator();
    $uploader = new AsynchronousCrocodocUpload($authenticator, $logger, SingleFileMapper::getInstance());
}
catch (RangeException $e) {

    /* Crocodoc api not set, do nothing
     */
    $logger->warning("Crocodoc API token not defined - exiting!");
}

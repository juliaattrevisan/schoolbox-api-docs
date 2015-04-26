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

$pendingUpload = FileMapper::getInstance()->getCrocodoc();

$logger = new Logger;


foreach($pendingUpload as $submission) {
    $logger->notice("Uploading ".$submission->getFileName()."(".$submission->getId().")");
    $document = new Document($submission);
    $result = $document->upload();
    if($result['errors']) {
        $logger->error($result['errors']);
    }
    else {
        $logger->notice("Upload completed successfully");
    }
}
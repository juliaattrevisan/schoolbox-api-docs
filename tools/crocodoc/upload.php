<?php
/**
 * This file will scan for student submissions that have no uuid and are accepted by crocodoc and attempt to load
 * them into crocodoc so they are ready for teachers to mark inline
 *
 * @author      James Leckie <jleckie@alaress.com.au>
 * @copyright   Copyright 2015, Alaress Pty Ltd
 */
require_once __DIR__ . '/../../include/Bootstrap.php';


use Schoolbox\Assessment\Component\SubmissionBox\SubmissionMapper;
use Crocodoc\Document;

$pendingUpload = SubmissionMapper::getInstance()->getCrocodoc();


foreach($pendingUpload as $submission) {
    echo "Uploading ".$submission->getFileName();
    $document = new Document($submission);
    $result = $document->upload();
    if($result['errors']) {
        echo " Failed - ({$result['errors']})\n";
    }
    else {
        echo " Success\n";
    }
}
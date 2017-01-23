<?php
/**
 * This file will scan for student submissions that have no uuid and are accepted by crocodoc and attempt to load
 * them into crocodoc so they are ready for teachers to mark inline
 *
 * @author      James Leckie <jleckie@alaress.com.au>
 * @copyright   Copyright 2015, Alaress Pty Ltd
 */
use Schoolbox\Learning\InlineGrader\Authenticator;
use Schoolbox\Assessment\Component\SubmissionBox\Submission\SingleFile\Mapper as SingleFileMapper;
use Schoolbox\Learning\InlineGrader\Asynchronous\Upload as AsynchronousCrocodocUpload;

require_once __DIR__ . '/../../conf/core/require.php';
spl_autoload_call('Schoolbox\\Application\\Daemon');
$kernel = new \Schoolbox\Application\Daemon\Kernel('Crocodoc Uploader', array('level'=>200));
$logger = $kernel->logger();

try {
    (new AsynchronousCrocodocUpload(
        new Authenticator(),
        $logger,
        SingleFileMapper::getInstance())
    )->uploadPendingCrocodocs();
}
catch (RangeException $e) {

    /* Crocodoc api not set, do nothing
     */
    $logger->warning("Crocodoc API token not defined - exiting!");
}

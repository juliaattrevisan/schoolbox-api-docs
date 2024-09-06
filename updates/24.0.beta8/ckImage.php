#!/usr/bin/php
<?php

/**
 * Strip out base64 images from CKEditor fields
 *
 * @copyright Copyright 2024, Schoolbox Pty Ltd
 * @link      https://schoolbox.education/
 */

// For instances yet to update to 24.0: as of 24.0.4 this script no longer needs
// to run.
// Instead: `updates/24.0.1/ckImage.php` will run, and will do the same thing
// (except in the foreground rather than in the background).

//declare(strict_types=1);
//
//use Schoolbox\Application\CliTool\App;
//
//require_once __DIR__ . '/../../conf/core/require.php';
//
//$sudo         = 'sudo -u ' . escapeshellarg(App::WEB_PROCESS_USER);
//$importScript = 'php ' . __DIR__ . '/../../tools/ckImage/ckImage2Storage.php';
//$logFile      = '/var/log/schoolbox/' . INSTANCE_ID . '/ckImage.log';
//
//// Can't pipe whilst retaining sudo user AFAIK:
//// need to tee and send output to null
//$tee      = $sudo . ' tee -a ' . escapeshellarg($logFile) . ' > /dev/null';
//$command  = $sudo . ' ' . $importScript . ' --perform 2>&1 | ' . $tee;
//
//// Run the whole thing in background
//echo "Running CK2Image extractor command in background process:\n" . $command . "\n";
//$proc       = proc_open(
//    "($command)",
//    [
//        0 => STDIN,
//        1 => STDOUT,
//        2 => STDERR,
//    ],
//    $pipes,
//);
//$procStatus = proc_get_status($proc);
//printf("Beginning CK2Image Extract (pid: %d)\n", $procStatus['pid']);
//
//// Exit: the import will continue to run in background
//exit();

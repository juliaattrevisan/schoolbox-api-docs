<?php

/**
 * Created by JetBrains PhpStorm.
 * User: aetworowsky
 * Date: 9/20/11
 * Time: 3:56 PM
 * To change this template use File | Settings | File Templates.
 */

require_once __DIR__ . '/../../conf/core/require.php';
$kernel = new \Schoolbox\Application\WebRequest\Kernel();

use Schoolbox\Learning\Assessment\DueWork\Response\File\Plagscan\Callback as PlagscanCallback;

try {
    $callback = new PlagscanCallback();
} catch (\DomainException $e) {
    /* Plagscan not configured - nothing to do
     */
    exit();
}

/* Handles responses from PlagScan which indicate a document has completed plagiarism scanning
 */
$documentId = $_SERVER["QUERY_STRING"];

if (!empty($documentId)) {
    echo $callback->notifyComplete($documentId);
}

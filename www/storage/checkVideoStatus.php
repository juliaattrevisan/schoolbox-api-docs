<?php

/**
 * Check the progress of a video's encoding, given a video's hash in the `hash`
 * query string param.
 *
 * * if no hash is provided, or no queue is found for the hash: 404
 * * otherwise, output a JSON array with these keys:
 * ** id
 * ** hash
 * ** status (one of the `QueueMapper::STATUS_*` constants)
 * ** duration
 *
 * @copyright Copyright 2021, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

use Schoolbox\Application\BasicRequest\Kernel;
use Schoolbox\User;
use Schoolbox\Video\QueueMapper;
use Schoolbox\Video\QueueTranslator\ModelToArray;

// disallow SitePage context, only direct access is allowed
if (isset($this)) {
    http_response_code(404);
    die('Only direct access permitted');
}

require_once __DIR__ . '/../../conf/core/require.php';
$kernel = new Kernel();
$kernel->startSentryTransaction(__FILE__);

// You must at least be logged in to check file status
$user = $kernel->get('user');
if (!($user instanceof User && $user->getId())) {
    http_response_code(401);
    die('User not logged in');
}

// What's the file?
$hash = $_GET['hash'] ?? null;
if (!isset($hash)) {
    http_response_code(404);
    die('No hash provided');
}

// Is this a video? Output its transcode status if so
try {
    $queue = QueueMapper::getInstance()->findOneByColumn('hash', $hash);
} catch (\Schoolbox\Mapper\Exception $e) {
    http_response_code(404);
    die('Queue for hash not found');
}

$translator = new ModelToArray();

// Output
header('Content-Type: application/json');
echo json_encode($translator($queue));

<?php

require_once __DIR__ . '/../../conf/core/require.php';
$kernel = new \Schoolbox\Application\BasicRequest\Kernel();
$kernel->startSentryTransaction(__FILE__);

if (!isset($_SESSION['user'])) {
    http_response_code(401);
    exit;
}

/** @var \Schoolbox\User $user */
$user = $_SESSION['user'];

if ($user->isSuperuser()) {
    http_response_code(200);
} else {
    http_response_code(403);
}

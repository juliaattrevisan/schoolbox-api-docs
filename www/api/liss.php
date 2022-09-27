<?php

/**
 * @copyright   Copyright 2022, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

use Schoolbox\External\Connector\Liss\Receiver;

require_once __DIR__ . '/../../conf/core/require.php';

$kernel = new \Schoolbox\Application\WebRequest\Kernel();
$config = $kernel->get('config');

$liss = new Receiver(
    (int)$config->get('external_enable'),
    $config->get('external_type'),
    $config->get('external_liss_username'),
    $config->get('external_liss_password'),
    $config->get('external_liss_schoolcode'),
    $config->get('logs_dir')
);

$log = $liss->log();

try {
    $liss->start();
} catch (Throwable $e) {
    $log->critical("Internal Error: {$e->getMessage()}");
}

<?php

/**
 * @copyright   Copyright 2022, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

use Schoolbox\External\Connector\Liss\Base\Endpoint\Factory;
use Schoolbox\External\Connector\Liss\Base\Endpoint\Receiver;

require_once __DIR__ . '/../../conf/core/require.php';

$kernel = new \Schoolbox\Application\WebRequest\Kernel();
$config = $kernel->get('config');

$superuser = \Schoolbox\UserMapper::getInstance()->findByUsername(\Schoolbox\UserMapper::USERNAME_ALARESS_ADMIN);

/* Some schools (like Redlands) send multiple sets of data to this Receiver.
 * Each data set corresponds to a timetable, such as Senior TImetable and Junior Timetable.
 * They're meant to co-exist, despite being sent separately. This means their source identifier
 * needs to be configurable uniquely instead of blindly assuming Factory::SOURCE_NAME since one
 * push will delete everything and replace its contents with another. They're separated only
 * by this source identifier.
 *
 * This $sourceName is optional and is derived from the querystring param. It's a unique
 * usage that isn't really nice.
 */
$sourceName = $_REQUESTT['source'] ?? Factory::SOURCE_NAME;

$liss = new Receiver(
    (int)$config->get('external_enable'),
    $config->get('external_type'),
    $config->get('external_liss_username'),
    $config->get('external_liss_password'),
    $config->get('external_liss_exclusions'),
    $config->get('external_liss_default_event_type'),
    $config->get('external_liss_teacher_identifier'),
    (bool)$config->get('external_liss_storage_logging'),
    $sourceName,
    $superuser,
    \Schoolbox\External\Connector\Log\Mapper::getInstance(),
    \Schoolbox\Resource\Storage\Mapper::getInstance()
);

$log = $liss->log();

try {
    $liss->start();
} catch (\Exception | \Throwable $e) {
    $log->critical("Internal Error: {$e->getMessage()}");
}

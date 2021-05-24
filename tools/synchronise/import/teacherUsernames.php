#!/usr/bin/php
<?php

/**
 * Teacher username saver - internal use only
 */

use Alaress\Lumberjack\Logger;

require_once __DIR__ . '/../../../conf/core/require.php';
$kernel = new \Schoolbox\Application\Daemon\Kernel(__FILE__);

$options = getopt('v', ['perform-update',]);
$reader  = new \Alaress\Csv\Reader(STDIN);
$reader->setLoadFromFirst(true);
$logger = new Logger(
    STDOUT,
    STDERR,
    isset($options['v']) ? Monolog\Logger::DEBUG : Monolog\Logger::INFO,
    [],
    true
);
$mapper = new \Schoolbox\UserMapper();

foreach ($reader as $row) {
    $username   = $row['username'];
    $externalId = $row['external id'];
    if (empty($externalId)) {
        $logger->notice(sprintf('User "%s" has no external ID in CSV - skipping', $username));
        continue;
    }

    try {
        $user  = $mapper->findOneByColumn('username', $username);
        $clone = clone $user;
    } catch (\Exception $e) {
        $logger->notice(sprintf('User "%s" not found - skipping', $externalId));
        continue;
    }

    $logger->info(
        sprintf(
            'Updating user "%s" external ID to "%s" (was "%s")',
            $username,
            $externalId,
            $user->getExternalId()
        )
    );

    /** @var \Schoolbox\User $user */
    /** @var \Schoolbox\User $clone */
    $user->setExternalId($externalId);
    if (isset($options['perform-update'])) {
        $mapper->save($user);
    }
}

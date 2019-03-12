#!/usr/bin/php
<?php
/**
 * CLI Pastoral Importer
 *
 * Used when there are lots of records to import from the sis, because we have an 1800sec
 * timeout enforced by nginx that we can't override.
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2019, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */
declare(strict_types=1);

use Aura\Di;
use Psr\Log\LoggerInterface;
use React\EventLoop\LoopInterface;
use Schoolbox\Application\Daemon;

$appOpts = [
    'help',
    'user:',
    'default-owner:',
    'parent-access',
    'add-records',
    'add-attachments',
    'delete-records',
];

require __DIR__ . '/../../conf/core/require.php';

$kernel = new Daemon\Kernel(
    'Pastoral Import',
    [
        Daemon\Kernel::OPTION_LEVEL         => Monolog\Logger::CRITICAL,
        Daemon\Kernel::OPTION_ERRORS        => E_ALL,
        Daemon\Kernel::OPTION_APP_LONG_OPTS => $appOpts,
    ]
);

/** @noinspection PhpUnhandledExceptionInspection */
$kernel->run(function (
    Di\Container $di,
    /** @noinspection PhpUnusedParameterInspection */
    LoopInterface $loop,
    LoggerInterface $log,
    array $opts
) {

    /* command-line options
     */
    $importParentAccess = isset($opts['parent-access']);
    $importRecords      = isset($opts['add-records']);
    $importAttachments  = isset($opts['add-attachments']);
    $deleteRecords      = isset($opts['delete-records']);
    $defaultOwnerId     = array_key_exists('default-owner', $opts) ? (int)$opts['default-owner'] : false;
    /** @var int|bool $userId The user conducting the import (used in logging) */
    $userId             = array_key_exists('user', $opts) ? (int)$opts['user'] : false;

    /* Help
     */
    if (isset($opts['help'])) {
        echo <<<HELP

Imports Pastoral records from external source. Primarily used if there are many records.

Usage: php pastoralImport.php [--parent-access] [--add-records] [--add-attachments] [--user=1] [--default-user=1]
    --parent-access:    give parents access to pastoral records
    --add-records:      import pastoral records
    --add-attachments:  import documents associated with pastoral records
    --delete-records:   if record is already exists, delete it and recreate, otherwise skip
    --user:             the user id of someone who should be associated with this import (typically a superuser)
    --default-owner:    the user id of someone who should be assigned as owner of pastoral records
    
    
HELP;

        exit(0);
    }

    if (!$userId) {
        $log->critical('Invalid user id. Specify a user to whom the pastoral import is associated for logging purposes (typically a superuser)');
        exit(0);
    }

    $userMapper = new \Schoolbox\UserMapper();

    try {
        $user = $userMapper->findById($userId);
    } catch (\Schoolbox\Mapper\Exception $e) {
        $log->critical('Invalid user id. User not found.');
        exit(0);
    }

    if (!$defaultOwnerId) {
        $log->critical('Invalid default owner id. Specify a user to whom pastoral records are assigned.');
        exit(0);
    }

    try {
        $defaultOwner = $userMapper->findById($defaultOwnerId);
    } catch (\Schoolbox\Mapper\Exception $e) {
        $log->critical('Invalid default owner id. User not found');
        exit(0);
    }

    // Only process if pastoral docman writeback is actually enabled in config
    if (!$di->get('config')->get("external_pastoral")) {
        $log->critical('Pastoral external is disabled, exiting...');
        exit(0);
    }

    pcntl_signal(SIGCHLD, SIG_DFL);

    $lock = new Alaress_Lock('pastoral_import.' . INSTANCE_ID . '.lock');

    if (!$lock->acquire()) {
        $log->critical('A pastoral import is currently in progress, exiting...');
        exit(0);
    }

    $log->info("Beginning import of pastoral records...");

    $pastoralExternal = new External_Pastoral();
    $pastoralExternal->importPastoral($user, $defaultOwner, $importParentAccess, $importRecords, $importAttachments, $deleteRecords);

    $lock->release();
});

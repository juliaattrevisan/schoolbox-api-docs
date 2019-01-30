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

$fields = [
    'help',
    'user:',
    'default-owner:',
    'parent-access',
    'add-records',
    'add-attachments',
];

$opts = require __DIR__ . '/../../conf/core/require-cli.php';

spl_autoload_call('\\Schoolbox\\Application\\Daemon');

$kernel = new Schoolbox\Application\Daemon\Kernel(
    'Pastoral Import',
    [
        'verbose' => $opts['v'] ?? [],
        'errors'  => E_ALL,
        'opts'    => $opts,
    ]
);

/** @noinspection PhpUnhandledExceptionInspection */
$kernel->run(function (
    Di\Container $di,
    /** @noinspection PhpUnusedParameterInspection */
    LoopInterface $loop,
    LoggerInterface $log,
    array $options
) {

    /* command-line options
     */
    $opts               = $options['opts'];
    $importParentAccess = isset($opts['parent-access']);
    $importRecords      = isset($opts['add-records']);
    $importAttachments  = isset($opts['add-attachments']);
    $defaultOwner       = null;
    $user               = null;

    /* Help
     */
    if (isset($opts['help'])) {
        echo <<<HELP

Imports Pastoral records from external source. Primarily used if there are many records.

Usage: php pastoralImport.php [--parent-access] [--add-records] [--add-attachments] [--user=1] [--default-user=1]
    --parent-access:    give parents access to pastoral records
    --add-records:      import pastoral records
    --add-attachments:  import documents associated with pastoral records
    --user:             the user id of someone who should be associated with this import (typically a superuser)
    --default-owner:    the user id of someone who should be assigned as owner of pastoral records
    
    
HELP;

        exit(0);
    }

    /* The user conducting the import (used in logging)
     */
    $userId = array_key_exists('user', $opts) ? (int)$opts['user'] : false;

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

    /* The default owner of pastoral records
     */
    $defaultOwnerId = array_key_exists('default-owner', $opts) ? (int)$opts['default-owner'] : false;

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
    $pastoralExternal->importPastoral($user, $defaultOwner, $importParentAccess, $importRecords, $importAttachments);

    $lock->release();
});
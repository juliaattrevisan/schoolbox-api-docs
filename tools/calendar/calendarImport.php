#!/usr/bin/php
<?php
/**
 * The Routing Daemon.
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2016, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */

use Aura\Di;
use Psr\Log\LoggerInterface as Logger;
use React\EventLoop\LoopInterface;

$fields = ['campus:', 'role:', 'file:', 'owner:', 'source:'];
$opts = require __DIR__ . '/../../conf/core/require-cli.php';
spl_autoload_call('\\Schoolbox\\Application\\Daemon');

$kernel = new Schoolbox\Application\Daemon\Kernel('Routing', [
    'verbose' => isset($opts['v']) ? $opts['v'] : null,
    'errors'  => E_ALL,
    'fields'  => $opts,
]);


// ROUTING APP
$kernel->run(function(Di\Container $di, LoopInterface $loop, Logger $log, array $opts) {

    $opts = $opts['fields'];

    $fauxPost = [];
    $fauxSesh = [];
    $file     = null;
    
    if (!isset($opts['campus'])) {
        $log->error("No campus specified.\n");
    } else {
        $campusList = explode(',',$opts['campus']);
    }

    if (!isset($opts['role'])) {
        $log->error("No role specified.\n");
    } else {
        $groupList = explode(',', $opts['role']);
    }

    if (!isset($opts['owner']) && is_numeric($opts['owner'])) {
        $log->error("No owner id specified.\n");
    } else {
        $ownerId = $opts['owner'];
    }

    if (!file_exists($opts['file'])) {
        $log->error("Specified file not found.\n");
    } else {
        $file = $opts['file'];
    }

    //Mandatory options check
    if (empty($opts['campus']) || empty($opts['role']) || empty($opts['file'] || empty($opts['owner']))) {
        exit(1);
    }

    //Optional options
    if (isset($opts['source'])) {
        $source = $opts['source'];
        $log->notice("Events with source ".$opts['source']." will be deleted .\n");
    } else {
        $source = null;
    }

    //Not used in this context
    $categoryList = null;

    $bulkHandler = new Bulk_Calendar($file, $source, $ownerId, $categoryList, $groupList, $campusList);

    try {
        $bulkHandler->addCalendar();
    } catch (Schoolbox\Mapper\DefinitionException $e) {

    }

});


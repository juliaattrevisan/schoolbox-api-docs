#!/usr/bin/php
<?php
/**
 * Calendar CSV import tool.  Use to import calendar CSV files from the command line or via cron
 * Use the source name to continually override existing calendar source sets
 *
 * @author      Matt Schembri <matt.schembri@alaress.com.au>
 * @copyright   Copyright 2016, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */

use Aura\Di;
use Psr\Log\LoggerInterface as Logger;
use React\EventLoop\LoopInterface;

$fields = ['help','campus:', 'role:', 'file:', 'owner:', 'source::'];
$opts = require __DIR__ . '/../../conf/core/require-cli.php';
spl_autoload_call('\\Schoolbox\\Application\\Daemon');

$kernel = new Schoolbox\Application\Daemon\Kernel('Calendar CSV Import', [
    'verbose' => [true,true],
    'errors'  => E_ALL,
    'fields'  => $opts,
]);


// ROUTING APP
$kernel->run(function(Di\Container $di, LoopInterface $loop, Logger $log, array $opts) {
    $opts = $opts['fields'];

    $file = null;
    if (isset($opts['help']) || count($opts) == 0) {
        echo <<<HELP
Usage: calendarImport.php --file import.csv --owner=1 --campus=1,2 --role=1 [--source 'sourcename']
    --file:       CSV file that matches the schoolbox calendar template located in Admin->Calendar
    --owner:      The user ID of who the events should be owned by
    --campus:     Comma-seperated list of campus IDs that the events should be shown to
    --role:       Comma-seperated list of role IDs that the events should be shown to
    --source:     Optional source name, subsequent uploads will delete any events with the same source name
    
HELP;
        exit;
    }
    else {
        if (!isset($opts['campus'])) {
            $log->error("No campus specified. (Use --help for example usage)\n");
        } else {
            $campusList = explode(',', $opts['campus']);
        }

        if (!isset($opts['role'])) {
            $log->error("No role specified. (Use --hhelp for example usage)\n");
        } else {
            $groupList = explode(',', $opts['role']);
        }

        if (!isset($opts['owner'])) {
            $log->error("No owner id specified. (Use --help for example usage)\n");
        } else {
            $ownerId = $opts['owner'];
        }

        if (!file_exists($opts['file'])) {
            $log->error("Specified file not found. (Use --help for example usage)\n");
        } else {
            $file = $opts['file'];
        }
    }

    //Mandatory options check
    if (empty($opts['campus']) || empty($opts['role']) || empty($opts['file'] || empty($opts['owner']))) {
        exit(1);
    }

    //Optional options
    if (isset($opts['source'])) {
        $source = $opts['source'];
        $log->warning("Events with source ".$opts['source']." will be deleted\n");
    } else {
        $source = null;
    }

    //Not used in this context
    $categoryList = null;

    $bulkHandler = new Bulk_Calendar($file, $source, $ownerId, $categoryList, $groupList, $campusList);

    try {

        $result = $bulkHandler->addCalendar();
        foreach($result as $event) {
            $log->notice("{$event['status']} event: {$event['name']}, {$event['error']}");
        }

    } catch (Schoolbox\Mapper\DefinitionException $e) {
        $log->error("Error.\n");
    } catch (Schoolbox\Mapper\Exception $e) {
        $log->error("User or campus(es) not found.\n");
        $log->error("No events created.\n");
    } catch (PDOException $e) {
        var_dump($e);
        $log->error("One of the supplied roles was not found.\n");
        $log->error("No events created.\n");
    }

});


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

$fields = ['campus:', 'role:', 'file:', 'owner:', 'source:', 'h:'];
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

    $file = null;

    if (isset($opts['h']) || count($opts) == 1) {
        $log->info( "Example usage:\n".

                    "php /usr/share/schoolbox/tools/calendar/calendarImport.php --file import.csv --owner 1 --campus 1,2 --role 1\n\n".

                    "Where import.csv matches the template at Admin->Calendar within Schoolbox.\n".

                    "You can supply a comma seperated list for both campus and role.\n\n".

                    "Optional arg: --source 'sourcename'.\n\n".

                    "When importing with a source, events will linked to that source.\n".

                    "Future imports with the same source will delete all other items linked to that source.");
        exit(1);
    }
    
    if (!isset($opts['campus'])) {
        $log->error("No campus specified. (Use --h for example usage)\n");
    } else {
        $campusList = explode(',',$opts['campus']);
    }

    if (!isset($opts['role'])) {
        $log->error("No role specified. (Use --h for example usage)\n");
    } else {
        $groupList = explode(',', $opts['role']);
    }

    if (!isset($opts['owner'])) {
        $log->error("No owner id specified. (Use --h for example usage)\n");
    } else {
        $ownerId = $opts['owner'];
    }

    if (!file_exists($opts['file'])) {
        $log->error("Specified file not found. (Use --h for example usage)\n");
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
        $log->error("Error.\n");
    } catch (Schoolbox\Mapper\Exception $e) {
        $log->error("User or campus(es) not found.\n");
        $log->error("No events created.\n");
    } catch (PDOException $e) {
        $log->error("One of the supplied roles was not found.\n");
        $log->error("No events created.\n");
    }

});


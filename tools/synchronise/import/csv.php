#!/usr/bin/php
<?php

use Alaress\Csv\Reader;
use Alaress\Lumberjack\Logger;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\NotFoundExceptionInterface;
use Schoolbox\External\Importer;
use Schoolbox\Realtime\Routing\Cache\WritableCache;

require_once __DIR__ . '/../../../conf/core/require.php';
$kernel = new \Schoolbox\Application\Daemon\Kernel(__FILE__);

// Manage options
$options = getopt('v', ['perform-update', 'user::', 'structure::', 'timetable::', 'enrolment::',]);
$update  = isset($options['perform-update']);

/**
 * Method for outputting display format.
 *
 * @param array $fields
 * @return string
 */
$displayFormat = function ($fields) {
    $maxFieldLength = max(array_map('strlen', array_keys($fields)));
    $result         = '';
    foreach ($fields as $field => $text) {
        $result .= sprintf(
            "    %s:%s %s\n",
            $field,
            str_repeat(' ', $maxFieldLength - strlen($field)),
            $text
        );
    }
    return $result;
};

/**
 * Method for getting a reader from a filename. exit(1) if something went wrong.
 *
 * @param string $file
 * @param Logger $logger
 *
 * @return Reader
 */
$getReader = function ($file, Logger $logger) {
    try {
        if ($file === false) {
            $file = STDIN;
        }
        $reader = new Reader($file);
        $reader->setLoadFromFirst(true)->setFillDown(true);
        return $reader;
    } catch (Exception $e) {
        $logger->critical($e->getMessage());
        exit(1);
    }
};

// Output help
if (!isset($options['user']) && !isset($options['structure']) && !isset($options['timetable']) && !isset($options['enrolment'])) {
    $basename = basename(__FILE__);
    echo <<<HELP
Imports timetable from CSV into the Schoolbox user connector.

Usage: $basename [-v] [--perform-update]
       [--user=USER_FILE]
       [--structure=STRUCTURE_FILE]
       [--timetable=TIMETABLE_FILE]
       [--enrolment=ENROLMENT_FILE]
    -v:               Verbose mode
    --perform-update: If set, updates DB - otherwise, performs a dry run.
    --user:           User file to import.
                      Uses stdin if no USER_FILE given.
    --structure:      Timetable structure file to import.
                      Uses stdin if no STRUCTURE_FILE given.
    --timetable:      Timetable file to import.
                      Uses stdin if no TIMETABLE_FILE given.
                      Must not be imported before STRUCTURE_FILE.
    --enrolment:      Enrolment file to import.
                      Must not be imported before TIMETABLE_FILE.
                      Uses stdin if no ENROLMENT_FILE given.

Format for USER_FILE:
{$displayFormat(Importer\User::getFormat())}
Format for STRUCTURE_FILE:
{$displayFormat(Importer\TimetableStructure::getFormat())}
Format for TIMETABLE_FILE:
{$displayFormat(Importer\Timetable::getFormat())}
Format for ENROLMENT_FILE:
{$displayFormat(Importer\TimetableEnrolment::getFormat())}

HELP;
    exit(1);
}

// Set up the logger
$logger = new Logger(
    STDOUT,
    STDERR,
    isset($options['v']) ? Monolog\Logger::DEBUG : Monolog\Logger::INFO,
    [],
    true
);

if (isset($options['user'])) {
    // User import does not fill down - the others do
    $logger->info('Starting user import...');
    $parser = new Importer\User($getReader($options['user'], $logger)->setFillDown(false), $logger, $update);
    $parser->save();
    $logger->info('User import done!');
} else {
    $logger->info('Skipping user import - no file provided');
}

// For each of structure file, timetable file and enrolment file:
if (isset($options['structure'])) {
    $logger->info('Starting structure import...');
    $parser = new Importer\TimetableStructure($getReader($options['structure'], $logger), $logger, $update);
    $parser->save();
    $logger->info('Structure import done!');
} else {
    $logger->info('Skipping structure import - no file provided');
}

if (isset($options['timetable'])) {
    $logger->info('Starting timetable import...');
    $parser = new Importer\Timetable($getReader($options['timetable'], $logger), $logger, $update);
    $parser->save();
    $logger->info('Timetable import done!');
} else {
    $logger->info('Skipping timetable import - no file provided');
}

if (isset($options['enrolment'])) {
    $logger->info('Starting enrolment import...');
    $parser = new Importer\TimetableEnrolment($getReader($options['enrolment'], $logger), $logger, $update);
    $parser->save();
    $logger->info('Timetable import done!');
} else {
    $logger->info('Skipping enrolment import - no file provided');
}

if (isset($options['user']) || isset($options['enrolment'])) {
    // reload the subscriptions if either users or enrolments were altered
    try {
        /** @var \Schoolbox\Realtime\Routing\Cache\Redis\WriteCache $cache */
        $cache = $kernel->get('subscriptionCache');
        $cache->setLog($logger);
        $cache->cacheAllUsers(INSTANCE_ID);
    } catch (\RuntimeException | NotFoundExceptionInterface | ContainerExceptionInterface $e) {
        $logger->error($e->getMessage(), [ 'exception' => $e ]);
    }
}

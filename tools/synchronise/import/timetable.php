#!/usr/bin/php
<?php

require_once(__DIR__ . '/../../../include/Bootstrap.php');

use Alaress\Csv\Reader;
use Alaress\Lumberjack\Logger;
use Schoolbox\External\Importer;

// Manage options
$options = getopt('v', ['perform-update', 'structure-file:', 'timetable-file:', 'enrolment-file:',]);
$update  = $options['perform-update'];

/**
 * Method for outputting display format.
 *
 * @param $fields
 *
 * @return string
 */
$displayFormat = function($fields) {
    $maxFieldLength = max(array_map('strlen', array_keys($fields)));
    $result = '';
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
$getReader = function($file, Logger $logger) {
    try {
        $reader = new Reader($file);
        $reader->setLoadFromFirst(true)->setFillDown(true);
        return $reader;
    } catch (Exception $e) {
        $logger->critical($e->getMessage());
        exit(1);
    }
};

// Output help
if (!isset($options['structure-file']) && !isset($options['timetable-file']) && !isset($options['enrolment-file'])) {
    echo <<<HELP
Imports timetable into the Schoolbox user connector.

Usage: timetable.php [-v] [--perform-update]
       [--structure-file=STRUCTURE_FILE]
       [--timetable-file=TIMETABLE_FILE]
       [--enrolment-file=ENROLMENT_FILE]
    -v:               Verbose mode
    --perform-update: If set, updates DB - otherwise, performs a dry run.
    --structure-file: Timetable structure file to import, in CSV format.
    --timetable-file: Timetable file to import, in CSV format.
                      Must not be imported before STRUCTURE_FILE.
    --enrolment-file: Enrolment file to import, in CSV format.
                      Must not be imported before TIMETABLE_FILE.

Format for STRUCTURE_FILE:
{$displayFormat(Importer\TimetableStructure::getFormat())}
Format for TIMETABLE_FILE:
{$displayFormat(Importer\Timetable::getFormat())}
Format for ENROLMENT_FILE:
{$displayFormat(Importer\Enrolment::getFormat())}

HELP;
    exit(1);
}

// Set up the logger
$logger = new Logger();
if (isset($options['v'])) {
    $logger->setLevel(Monolog\Logger::DEBUG);
} else {
    $logger->setLevel(Monolog\Logger::INFO);
}

// For each of structure file, timetable file and enrolment file:
if (isset($options['structure-file'])) {
    $logger->info('Starting structure import...');
    $parser = new Importer\TimetableStructure($getReader($options['structure-file']), $logger, $update);
    $parser->save();
    $logger->info('Structure import done!');
} else {
    $logger->info('Skipping structure import - no file provided');
}

if (isset($options['timetable-file'])) {
    $logger->info('Starting timetable import...');
    $parser = new Importer\Timetable($getReader($options['timetable-file']), $logger, $update);
    $parser->save();
    $logger->info('Timetable import done!');
} else {
    $logger->info('Skipping timetable import - no file provided');
}

if (isset($options['enrolment-file'])) {
    $logger->info('Starting enrolment import...');
    $parser = new Importer\Enrolment($getReader($options['enrolment-file']), $logger, $update);
    $parser->save();
    $logger->info('Timetable import done!');
} else {
    $logger->info('Skipping enrolment import - no file provided');
}

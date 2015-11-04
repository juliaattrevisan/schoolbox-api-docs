#!/usr/bin/php
<?php

require_once(__DIR__ . '/../../../include/Bootstrap.php');

use Alaress\Csv\Reader;
use Alaress\Lumberjack\Logger;
use Schoolbox\External\Importer;

// Options: verbose, perform-update
// Last parameter: the file to import
$options = getopt('v', ['perform-update', 'structure-file:', 'timetable-file:', 'enrolment-file:',]);

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

if (count($argv) <= 1) {
    echo <<<HELP
Imports timetable structure from FILE into the Schoolbox user connector.
This must be imported before the timetable and enrolments are imported.

Usage: timetable-structure.php [-v] [--perform-update]
       [--structure-file=STRUCTURE_FILE]
       [--timetable-file=TIMETABLE_FILE]
       [--enrolment-file=ENROLMENT_FILE]
    -v:               Verbose mode
    --perform-update: If set, updates DB - otherwise, performs a dry run
    --structure-file: Timetable structure file to import, in CSV format
    --timetable-file: Timetable file to import, in CSV format
    --enrolment-file: Enrolment file to import, in CSV format

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
if (isset($options['structure_file'])) {
    $logger->info('Starting structure import...');
    try {
        $reader = new Reader($options['structure_file']);
        $reader->setLoadFromFirst(true)->setFillDown(true);
    } catch (Exception $e) {
        $logger->critical($e->getMessage());
        exit(1);
    }

    $parser = new Importer\TimetableStructure($reader, $logger, isset($options['perform-update']));
    $parser->save();
    $logger->info('Structure import done!');
} else {
    $logger->info('Skipping structure import - no file provided');
}

if (isset($options['timetable_file'])) {
    $logger->info('Starting timetable import...');
    try {
        $reader = new Reader($options['timetable_file']);
        $reader->setLoadFromFirst(true)->setFillDown(true);
    } catch (Exception $e) {
        $logger->critical($e->getMessage());
        exit(1);
    }

    $parser = new Importer\Timetable($reader, $logger, isset($options['perform-update']));
    $parser->save();
    $logger->info('Timetable import done!');
}

if (isset($options['enrolment_file'])) {
    $logger->info('Starting enrolment import...');
    try {
        $reader = new Reader($options['enrolment_file']);
        $reader->setLoadFromFirst(true)->setFillDown(true);
    } catch (Exception $e) {
        $logger->critical($e->getMessage());
        exit(1);
    }

    $parser = new Importer\Timetable($reader, $logger, isset($options['perform-update']));
    $parser->save();
    $logger->info('Timetable import done!');
}

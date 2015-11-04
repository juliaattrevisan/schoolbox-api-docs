#!/usr/bin/php
<?php

require_once(__DIR__ . '/../../../include/Bootstrap.php');

use Alaress\Csv\Reader;
use Alaress\Lumberjack\Logger;
use Schoolbox\External\Importer\TimetableStructure;

// Options: verbose, perform-update
// Last parameter: the file to import
$options = getopt('v', ['perform-update']);
$fields  = TimetableStructure::getFormat();

if (count($argv) <= 1) {
    echo <<<HELP
Imports timetable structure from FILE into the Schoolbox user connector.
This must be imported before the timetable and enrolments are imported.

Usage: timetable-structure.php [-v] [--perform-update] FILE
    -v:               Verbose mode
    --perform-update: If set, updates DB - otherwise, performs a dry run

Format for FILE:
    A CSV file which contains the following columns:

HELP;
    $maxFieldLength = max(array_map('strlen', array_keys($fields)));
    foreach ($fields as $field => $text) {
        printf(
            "        %s:%s %s\n",
            $field,
            str_repeat(' ', $maxFieldLength - strlen($field)),
            $text
        );
    }
    exit(1);
}

// Set up the logger
$logger = new Logger();
if (isset($options['v'])) {
    $logger->setLevel(Monolog\Logger::DEBUG);
} else {
    $logger->setLevel(Monolog\Logger::INFO);
}

// Set up the reader
try {
    $reader = new Reader(array_pop($argv));
    $reader->setLoadFromFirst(true)->setFillDown(true);
} catch (Exception $e) {
    $logger->critical($e->getMessage());
    exit(1);
}

$parser = new TimetableStructure($reader, $logger, isset($options['perform-update']));
$parser->save();

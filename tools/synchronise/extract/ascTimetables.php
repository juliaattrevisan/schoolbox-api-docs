#!/usr/bin/php
<?php

require_once __DIR__ . '/../../../conf/core/require.php';
$kernel = new \Schoolbox\Application\Daemon\Kernel(__FILE__);

/**
 * Deprecation notice
 */
echo "\033[1;33m/******************************************************************************************/\033[0m" . PHP_EOL;
echo "\033[1;33m/***************     Deprecated NOTICE - this will be deleted in future    ****************/\033[0m" . PHP_EOL;
echo "\033[1;33m/*    If you are running this, please add a comment of this scripts name into SA-19334    */\033[0m" . PHP_EOL;
echo "\033[1;33m/******************************************************************************************/\033[0m" . PHP_EOL;
echo "\033[1;33m/******************************************************************************************/\033[0m" . PHP_EOL;

$options = getopt('', ['structure', 'timetable', 'enrolment',]);

$reader = new XMLReader();
$file   = array_pop($argv);
if (!$reader->open($file)) {
    fwrite(STDERR, "Could not open file \"$file\"\n");
    exit(1);
}

if (isset($options['structure'])) {
    $translator = new External_AscTimetables_Translator_Structure($reader);
} elseif (isset($options['timetable'])) {
    $translator = new External_AscTimetables_Translator_Timetable($reader);
} elseif (isset($options['enrolment'])) {
    $translator = new External_AscTimetables_Translator_Enrolment($reader);
} else {
    $basename = basename(__FILE__);
    echo <<<HELP
Extracts a Schoolbox CSV import from an ASC Timetables XML export.
Outputs extract to stdout.

Usage: $basename [--structure] [--timetable] [--enrolment] FILE
    --structure: Extracts timetable structure data from FILE.
    --timetable: Extracts timetable data from FILE.
    --enrolment: Extracts enrolment data from FILE.

HELP;
    exit(1);
}

$writer = new \Alaress\Csv\Writer(STDOUT, $translator->translateXmlToArray());
$writer->write();

/**
 * Deprecation notice
 */
echo "\033[1;33m/******************************************************************************************/\033[0m" . PHP_EOL;
echo "\033[1;33m/***************     Deprecated NOTICE - this will be deleted in future    ****************/\033[0m" . PHP_EOL;
echo "\033[1;33m/*    If you are running this, please add a comment of this scripts name into SA-19334    */\033[0m" . PHP_EOL;
echo "\033[1;33m/******************************************************************************************/\033[0m" . PHP_EOL;
echo "\033[1;33m/******************************************************************************************/\033[0m" . PHP_EOL;
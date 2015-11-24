#!/usr/bin/php
<?php

require_once(__DIR__ . '/../../../include/Bootstrap.php');

$options = getopt('', ['structure', 'timetable', 'enrolment',]);

if (!isset($options['structure']) && !isset($options['timetable']) && !isset($options['enrolment'])) {
}

$reader = new XMLReader();
if (!$reader->open(array_pop($argv))) {

}

if (isset($options['structure'])) {
    $translator = new Synchronise_External_Lahore_Translator_Structure($reader);
} elseif (isset($options['timetable'])) {
    $translator = new Synchronise_External_Lahore_Translator_Timetable($reader);
} elseif (isset($options['enrolment'])) {
    $translator = new Synchronise_External_Lahore_Translator_Enrolment($reader);
} else {
    echo <<<HELP
Extracts a Schoolbox CSV import from an ASC Timetables XML export.
Outputs extract to stdout.

Usage: .php [--structure] [--timetable] [--enrolment] FILE
    --structure: Extracts timetable structure data from FILE.
    --timetable: Extracts timetable data from FILE.
    --enrolment: Extracts enrolment data from FILE.

HELP;
    exit(1);
}

$writer = new \Alaress\Csv\Writer(STDOUT, $translator->translateXmlToArray());
$writer->write();

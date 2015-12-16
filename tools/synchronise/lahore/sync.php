<?php
/**
 * Lahore's specialized sync.
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2015, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

require_once __DIR__ . '/../../../include/Bootstrap.php';

$options = getopt("v", array('perform-update'));

// Structure and Timetable come from the XML file
// Enrolments come from some other CSV
$xmlImport    = Config::get('external_sync_asctimetables_xml');
$enrolmentCsv = Config::get('external_sync_enrolment_csv');

$xmlImport    = SITE_root.'/tools/synchronise/import/ascTimetables.xml';
$enrolmentCsv = SITE_root.'/tools/synchronise/import/enrolment.csv';

/**
 * Gets an command for sync $type
 *
 * @param  string $type
 * @param  string $script
 * @param  string $infile
 * @return string
 */
$getCommand = function ($type, $script, $infile = null) use ($options) {
    $commandParts = ['php', SITE_root.$script];
    if (isset($options['v'])) {
        $commandParts[] = '-v';
    }
    if (isset($options['perform-update'])) {
        $commandParts[] = '--perform-update';
    }
    $commandParts[] = '--'.$type;
    if (isset($infile)) {
        $commandParts[] = $infile;
    }
    return implode(' ', array_map('escapeshellarg', $commandParts));
};

/**
 * Gets an extract command for sync $type
 *
 * @param  string $type
 * @param  string $infile
 * @return string
 */
$extractCommand = function ($type, $infile) use ($getCommand) {
    return $getCommand($type, '/tools/synchronise/extract/ascTimetables.php', $infile);
};

/**
 * Gets an import command for sync $type
 *
 * @param  string $type
 * @return string
 */
$importCommand = function ($type) use ($getCommand) {
    return $getCommand($type, '/tools/synchronise/import/csv.php');
};

$output = [];
passthru($extractCommand('structure', $xmlImport).' | '.$importCommand('structure'), $returnCode);
passthru($extractCommand('timetable', $xmlImport).' | '.$importCommand('timetable'), $returnCode);
passthru($importCommand('enrolment').' < '.escapeshellarg($enrolmentCsv), $returnCode);

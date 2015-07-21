#!/usr/bin/php
<?php
/**
 * Script for parsing iSAMS Batch API
 */

require_once __DIR__ . '/../../include/Bootstrap.php';

$reader = new External_Isams_Batch_Reader_CurrentPupil();
$reader->open($argv[1]);
foreach ($reader->getPupils() as $i => $pupil) {
    print_r($pupil);
}

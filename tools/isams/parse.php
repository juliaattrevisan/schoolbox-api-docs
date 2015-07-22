#!/usr/bin/php
<?php
/**
 * Script for parsing iSAMS Batch API
 */

require_once __DIR__ . '/../../include/Bootstrap.php';

$reader = new External_Isams_Batch_Reader_Parent();
$reader->open($argv[1]);

$i = 0;
foreach ($reader->getAccountInfo() as $account) {
    print_r($account);
    if (++$i >= 100) {
        exit;
    }
}

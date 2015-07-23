#!/usr/bin/php
<?php
/**
 * Script for testing iSAMS Batch API
 */

require_once __DIR__ . '/../../include/Bootstrap.php';

$api = new External_Isams_Batch_BaseApi('isamstest.cis.edu.sg', $argv[1]);
echo $api->retrieveDocument();

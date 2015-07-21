#!/usr/bin/php
<?php
/**
 * Script for testing iSAMS Batch API
 */

require_once __DIR__ . '/../../include/Bootstrap.php';

$api = new External_Isams_Batch_BaseApi(
    'isamstest.cis.edu.sg',
    '6AEE01B5-ABC5-4293-BE3F-B8FBAF452764'
);

echo $api->retrieveDocument();

<?php
/* Test for detecting browsers */

require_once __DIR__.'/../../library/Bootstrap.php';
echo "<pre>";
echo "Detected Browser Info:\n\n";

use BrowscapPHP\Browscap;

//$bc = new Browscap(__DIR__.'/../../tools/browscap');
$bc = new Browscap();

//$bc->remoteIniUrl = 'http://browscap.org/stream?q=Full_PHP_BrowsCapINI';
// Don't attempt to update
$bc->doAutoUpdate = false;
// Use local version of the browscap.ini file
$bc->updateMethod = 'local';
// really ensure we don't attemp to update the browscap.ini file (1 year)
$bc->updateInterval = '31536000';

$current_browser = $bc->getBrowser();

var_dump($current_browser);
echo "</pre>";

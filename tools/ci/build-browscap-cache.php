<?php
require __DIR__ . '/../../vendor/autoload.php';
echo "<pre>";
echo "Detected Browser Info:\n\n";

use BrowscapPHP\Browscap;

$bc = new Browscap();
$cacheDir = __DIR__.'/../../tools/browscap';
$adapter = new \WurflCache\Adapter\File([\WurflCache\Adapter\File::DIR => $cacheDir]);
$bc->setCache($adapter);

$bc->remoteIniUrl = 'http://browscap.org/stream?q=Lite_PHP_BrowsCapINI';
// Don't attempt to update
$bc->doAutoUpdate = true;
// Use local version of the browscap.ini file
$bc->updateMethod = 'cURL';
// really ensure we don't attemp to update the browscap.ini file (1 year)
//$bc->updateInterval = '0';

$current_browser = $bc->getBrowser();

var_dump($current_browser);
echo "</pre>";

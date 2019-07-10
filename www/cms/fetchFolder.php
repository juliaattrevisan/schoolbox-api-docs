<?php
/**
 * Script to put all resources from a folder into a zip and send to client.
 *
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

use Schoolbox\Application\WebRequest\ShutdownHandler;

require_once __DIR__ . '/../../conf/core/require.php';
$kernel = new \Schoolbox\Application\WebRequest\Kernel();

$page = new SitePage();
$zip = new ZipFolder($_GET['folder']);
ShutdownHandler::instance()->registerFunction([$zip, 'cleanUp'], 10000);
$zip->sendZip();

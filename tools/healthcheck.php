#!/usr/bin/php
<?php
/**
 * Perform external database permission checks
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2012, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

use Alaress\Database\Connector\IConnector;
use Schoolbox\Config;

require_once __DIR__ . '/../conf/core/require-cli.php';
$kernel = new \Schoolbox\Application\Daemon\Kernel(__FILE__);

$di = $kernel->getDependencyInjector();

/** @var IConnector $databaseExternal */
$databaseExternal = $di->get('databaseExternal');

/** @var Config\Loader $configLoader */
$configLoader = $di->get('config');

echo "[" . date('Y-m-d H:i:s') . "] Running health check script\n";

$healthCheck = new Healthcheck($databaseExternal, $configLoader);
$healthCheck->run();
$results = $healthCheck->results();

echo $results['log']."\n";

if(isset($results['errors']) && count($results['errors']) > 0 ) {
    echo "\n".implode("\n",$results['errors'])."\n";
}

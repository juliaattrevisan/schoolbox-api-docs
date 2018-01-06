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
use Schoolbox\Application\WebRequest\Kernel;
use Schoolbox\Config;

/** @var Kernel $kernel */
$kernel = require_once(dirname(__FILE__) . '/../library/Bootstrap.php');

$di = $kernel->getDependencyInjector();

/** @var IConnector $databaseExternal */
$databaseExternal = $di->get('databaseExternal');

/** @var Config\Loader $configLoader */
$configLoader = $di->get('config');

echo "[" . date('Y-m-d H:i:s') . "] Running health check script\n";

$healthCheck = new Healthcheck($databaseExternal, $configLoader);
$healthCheck->run();
$results = $healthCheck->results();

echo $results['log'];

if(isset($results['errors']) && count($results['errors']) > 0 ) {
    echo "\n".implode("\n",$results['errors'])."\n";
}

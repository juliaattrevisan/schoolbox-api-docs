#!/usr/bin/php
<?php
/**
 * Perform Schoolbox User/Synergetic synchronisations
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2012, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

use Alaress\Database\Connector\IConnector;
use Aura\Di\Container as DependencyInjector;
use Schoolbox\Application\WebRequest\Kernel;
use Schoolbox\Config\Loader as ConfigLoader;

/** @var Kernel $kernel */
$kernel = require_once(dirname(__FILE__) . '/../library/Bootstrap.php');

/** @var DependencyInjector $di */
$di = $kernel->getDependencyInjector();

/** @var IConnector $databaseExternal */
$databaseExternal = $di->get('databaseExternal');

/** @var ConfigLoader $configLoader */
$configLoader = $di->get('config');

echo "[" . date('Y-m-d H:i:s') . "] Running health check script\n";

$healthCheck = new Healthcheck($databaseExternal, $configLoader);
$results = $healthCheck->run();

echo "{$results['log']}\n\n{$results['error']}";

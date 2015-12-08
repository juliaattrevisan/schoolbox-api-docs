#!/usr/bin/php
<?php

/**
 * User Provisioning System command line entry point
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2015, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

$options = getopt("v", array('perform-update'));

require_once __DIR__ . '/../../conf/core/require.php';
spl_autoload_call('\\Schoolbox\\Application\\Daemon');

$kernel = new Schoolbox\Application\Daemon\Kernel('UPS');

/** @var Schoolbox\Sync\Provisioner\Provisioner $ups */
$ups = $kernel->getDependencyInjector()->get('ups.provisioner');
// Note that trying to overwrite the logger (and thus destroy the old one)
// will cause an exit as soon as stdout is written
// (because stdout is closed when the old logger is destroyed)
$ups->run(isset($options['perform-update']));

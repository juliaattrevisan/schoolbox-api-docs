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

require_once(dirname(__FILE__) . '/../../include/Bootstrap.php');

// A logger
// TODO use Monolog - it supplies a Redis logger
$logger = new Alaress\Lumberjack\Logger(
    STDOUT,
    STDERR,
    (isset($options['v']) ? Monolog\Logger::DEBUG : Monolog\Logger::INFO)
);

// Inject:
// * logger
// * config/ruleset
// * UserMapper
// * SyncFactory
// TODO set this up in DI so that dry run can use it with the exact same configuration
$ups = new Schoolbox\Sync\Provisioner\Provisioner();
$ups->run(isset($options['perform-update']));

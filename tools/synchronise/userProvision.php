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

// A logger
// TODO use Monolog - it supplies a Redis logger
$logger = new Alaress\Lumberjack\Logger(
    STDOUT,
    STDERR,
    (isset($options['v']) ? Monolog\Logger::DEBUG : Monolog\Logger::INFO)
);

// Rulesets
// This factory is intended for Twig data but I can easily repurpose it
$ruleFactory = new Schoolbox\Sync\Ruleset\Factory();

// Sync factory
$syncFactory = new \Schoolbox\External\Connector\SyncFactory();

// User mapper
$userMapper = Schoolbox\UserMapper::getInstance();

// Grammar factory
$grammarFactory = new Schoolbox\Sync\Grammar\Factory(
    $userMapper,
    Schoolbox\Sync\Rule\Mapper::getInstance()
);

// Inject:
// * logger
// * config/ruleset
// * UserMapper
// * SyncFactory
// TODO set this up in DI so that dry run can use it with the exact same configuration
$ups = new Schoolbox\Sync\Provisioner\Provisioner(
    $ruleFactory,
    $grammarFactory,
    $syncFactory->build(),
    $userMapper,
    $logger
);
$ups->run(isset($options['perform-update']));

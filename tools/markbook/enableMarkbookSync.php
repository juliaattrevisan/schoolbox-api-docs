#!/usr/bin/php
<?php

/**
 * Lazy way to enable/disable external markbook sync
 *
 * @author      Damian Sloane <dsloane@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

use Schoolbox\Config\ConfigMapper;

// Setup kernel
require_once __DIR__ . '/../../conf/core/require.php';
$kernel = new Schoolbox\Application\Daemon\Kernel('Enable Markbook External Sync');

// Get this kernel started

$kernel->run(function ($di, $loop, $log, $opts) {

    $configMapper = ConfigMapper::getInstance();
    $configMapper->getDatabase()->query("UPDATE config SET value = 1 WHERE name = 'external_markbook'");
});

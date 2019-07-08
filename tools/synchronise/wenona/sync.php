#!/usr/bin/php
<?php
/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 * @shamelessly_pilfered_from <steve.chan@alaress.com.au>
 */
require_once __DIR__ . '/../../../conf/core/require.php';
$kernel = new \Schoolbox\Application\Daemon\Kernel(__FILE__);

/*
 *
 * If you want to actually perform the DB update, use:
 * $sync->setPerformUpdate(true);
 *
 * Otherwise it will echo out a plan
 */
$allStrategies = array(
    //  'Staff',
    'Parent',
    'Student',
);

/* Do each strategy
 */
foreach ($allStrategies as $currentStrategy) {
    $sync = new Synchronise_Synergetic_Main('Synchronise_Synergetic_Wenona_Strategy_' . $currentStrategy);

    // Do not alter the db
    $sync->setPerformUpdate(true);

    // Set Verbosity so you can see intentions
    $sync->setVerbose(true);

    $sync->generateTitle();
    $sync->synchronise();

    echo "\n\n";
}


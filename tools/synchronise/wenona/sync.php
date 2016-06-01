<?php
/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2012, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 * @shamelessly_pilfered_from <steve.chan@alaress.com.au>
 */
require_once(dirname(__FILE__) . '/../../../library/Bootstrap.php');

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


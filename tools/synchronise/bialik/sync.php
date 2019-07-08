#!/usr/bin/php
<?php
/**
 * Perform Schoolbox User/Synergetic synchronisations
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */
require_once __DIR__ . '/../../../conf/core/require.php';
$kernel = new \Schoolbox\Application\Daemon\Kernel(__FILE__);

/* If you want to actually perform the DB update, use:
 * $sync->setPerformUpdate(true);
 *
 * Otherwise it will echo out a plan
 */
$allStrategies = array(
	'Synchronise_Synergetic_Bialik_Strategy_Staff',
	'Synchronise_Synergetic_Bialik_Strategy_Parent',
	'Synchronise_Synergetic_Bialik_Strategy_Student',
);

/* Do each strat
 */
foreach($allStrategies as $currentStrategy) {
	$sync = new Synchronise_Synergetic_Main($currentStrategy);

	/* Do not alter the db
	 */
	$sync->setPerformUpdate(true);

	/* Set Verbosity so you can see intentions
	 */
	$sync->setVerbose(true);

	$sync->generateTitle();
	$sync->synchronise();

	echo "\n\n";
}


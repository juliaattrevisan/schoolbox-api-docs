<?php
/**
 * Perform Schoolbox User/Synergetic synchronisations
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2011, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */
require_once(dirname(__FILE__) . '/../../include/Bootstrap.php');

require_once(dirname(__FILE__) . '/../../include/Config.php');
require_once(dirname(__FILE__) . '/../../library/user.php');
require_once(dirname(__FILE__) . '/../../library/group.php');
require_once(dirname(__FILE__) . '/../../library/synergetic/ExternalUser.php');

/* Main classes
 */
require_once(dirname(__FILE__) . '/main/SynergeticSync.php');
require_once(dirname(__FILE__) . '/main/SynergeticSyncStrategyInterface.php');
require_once(dirname(__FILE__) . '/main/SynergeticSyncStrategy.php');

/* Bialik custom classes
 */
require_once(dirname(__FILE__) . '/bialik/BialikSynergeticSyncStrategy.php');
require_once(dirname(__FILE__) . '/bialik/StaffSynergeticSyncStrategy.php');
require_once(dirname(__FILE__) . '/bialik/ParentSynergeticSyncStrategy.php');
require_once(dirname(__FILE__) . '/bialik/StudentSynergeticSyncStrategy.php');

/* MGGS custom classes
 */
require_once(dirname(__FILE__) . '/mggs/UpdateParentSynergeticSyncStrategy.php');

/* lol
 *
 * If you want to actually perform the DB update, use:
 * $sync->setPerformUpdate(true);
 *
 * Otherwise it will echo out a plan
 */
$allStrategies = array(
	'UpdateParentSynergeticSyncStrategy',
	'StaffSynergeticSyncStrategy',
	'ParentSynergeticSyncStrategy',
	'StudentSynergeticSyncStrategy',
);

/* Do each strat
 */
foreach($allStrategies as $currentStrategy) {
	$sync = new SynergeticSync($currentStrategy);

	/* Do not alter the db
	 */
	$sync->setPerformUpdate(false);

	/* Set Verbosity so you can see intentions
	 */
	$sync->setVerbose(true);

	$sync->generateTitle();
	$sync->synchronise();

	echo "\n\n";
}


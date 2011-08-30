<?php
/**
 * Perform Schoolbox User/Synergetic synchronisations
 */
require_once(dirname(__FILE__) . '/../../include/Bootstrap.php');

require_once(dirname(__FILE__) . '/../../include/Config.php');
require_once(dirname(__FILE__) . '/../../library/user.php');
require_once(dirname(__FILE__) . '/../../library/group.php');
require_once(dirname(__FILE__) . '/../../library/synergetic/ExternalUser.php');

/* Main classes
 */
require_once(dirname(__FILE__) . '/SynergeticSync.php');
require_once(dirname(__FILE__) . '/SynergeticSyncStrategyInterface.php');
require_once(dirname(__FILE__) . '/SynergeticSyncStrategy.php');
require_once(dirname(__FILE__) . '/BialikSynergeticSyncStrategy.php');

/* Individual Strategies
 */
require_once(dirname(__FILE__) . '/StaffSynergeticSyncStrategy.php');
require_once(dirname(__FILE__) . '/ParentSynergeticSyncStrategy.php');
require_once(dirname(__FILE__) . '/StudentSynergeticSyncStrategy.php');
require_once(dirname(__FILE__) . '/UpdateParentSynergeticSyncStrategy.php');

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
	$sync->setVerbose(false);

	$sync->generateTitle();
	$sync->synchronise();

	echo "\n\n";
}


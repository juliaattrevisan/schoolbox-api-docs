<?php
/**
 * Perform Schoolbox User/Synergetic synchronisations
 */
require_once(dirname(__FILE__) . '/../../include/Bootstrap.php');

require_once(dirname(__FILE__) . '/../../include/Config.php');
require_once(dirname(__FILE__)  .'/../../library/user.php');
require_once(dirname(__FILE__)  .'/../../library/group.php');
require_once(dirname(__FILE__)  .'/../../library/synergetic/ExternalUser.php');

/* Main classes
 */
require_once(dirname(__FILE__) . '/SynergeticSync.php');
require_once(dirname(__FILE__) . '/SynergeticSyncStrategyInterface.php');
require_once(dirname(__FILE__) . '/SynergeticSyncStrategy.php');

/* Individual Strategies
 */
require_once(dirname(__FILE__) . '/StaffSynergeticSyncStrategy.php');
require_once(dirname(__FILE__) . '/ParentSynergeticSyncStrategy.php');
require_once(dirname(__FILE__) . '/StudentSynergeticSyncStrategy.php');

/* lol
 *
 * If you want to actually perform the DB update, use:
 * $sync->setPerformUpdate(true);
 *
 * Otherwise it will echo out a plan
 */
$strategies = array(
	'StaffSynergeticSyncStrategy',
	'ParentSynergeticSyncStrategy',
	'StudentSynergeticSyncStrategy'
);

/* Do each strat
 */
foreach($strategies as $currentStrategy) {
	$sync = new SynergeticSync($currentStrategy);

	$sync->getStrategy()->generateTitle();
	$sync->synchronise();
}


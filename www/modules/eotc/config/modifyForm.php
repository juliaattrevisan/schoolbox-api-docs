<?php
/**
 * Config ModifyForm
 *
 * Process new Config settings
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2011, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

require_once(dirname(__FILE__) . '/../../../../library/Bootstrap.php');

use EOTC\ConfigMapper;

$page = new SitePage();

new \EOTC\Authenticator($page);

/** @var \Schoolbox\User $sessionUser */
$sessionUser = $_SESSION['user'];

if ($sessionUser->isSuperuser()) {
	$configMapper = new ConfigMapper();
    $allConfigs = $configMapper->findAll();

	$firstReminder = $allConfigs->getBy('name', 'first_reminder');
    $firstReminder->setValue((int)$_POST['firstReminder']);

	$dailyReminder = $allConfigs->getBy('name', 'daily_reminder');
    $dailyReminder->setValue((int)$_POST['dailyReminder']);

	$urgentThreshold = $allConfigs->getBy('name', 'urgent_threshold');
    $urgentThreshold->setValue((int)$_POST['urgentThreshold']);

	$policy = $allConfigs->getBy('name', 'policy');
    $policy->setValue($_POST['policy']);

	$ramsWarning = $allConfigs->getBy('name', 'rams_warning');
    $ramsWarning->setValue($_POST['ramsWarning']);

    $externalDbSync = $allConfigs->getBy('name', 'external_db_sync');
    $externalDbSync->setValue((int)$_POST['externalDbSync']);

	$configMapper->save($firstReminder);
	$configMapper->save($dailyReminder);
	$configMapper->save($urgentThreshold);
	$configMapper->save($policy);
	$configMapper->save($ramsWarning);
    $configMapper->save($externalDbSync);

	$page->localRedir('/modules/eotc/config/modify');
}
else {
	error(19);
}

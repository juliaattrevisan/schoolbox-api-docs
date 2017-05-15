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

$page = new SitePage();

new \EOTC\Authenticator($page);

/** @var \Schoolbox\User $sessionUser */
$sessionUser = $_SESSION['user'];

if ($sessionUser->isSuperuser()) {
	$configMapper = new \EOTC\ConfigMapper();

	$firstReminder = $configMapper->findByColumn('name', 'first_reminder')->first();
	$dailyReminder = $configMapper->findByColumn('name', 'daily_reminder')->first();
	$urgentThreshold = $configMapper->findByColumn('name', 'urgent_threshold')->first();
	$policy = $configMapper->findByColumn('name', 'policy')->first();
	$ramsWarning = $configMapper->findByColumn('name', 'rams_warning')->first();

	$firstReminder->setValue($_POST['firstReminder']);
	$dailyReminder->setValue($_POST['dailyReminder']);
	$urgentThreshold->setValue($_POST['urgentThreshold']);
	$policy->setValue($_POST['policy']);
	$ramsWarning->setValue($_POST['rams_warning']);

	$configMapper->save($firstReminder);
	$configMapper->save($dailyReminder);
	$configMapper->save($urgentThreshold);
	$configMapper->save($policy);
	$configMapper->save($ramsWarning);

	$page->localRedir('/modules/eotc/config/modify');
}
else {
	error(19);
}

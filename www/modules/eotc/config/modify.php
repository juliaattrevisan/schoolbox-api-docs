<?php
/**
 * Config Modify
 *
 * Modify EOTC Configuration settings
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2011, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

new \EOTC\Authenticator($this);

/** @var \Schoolbox\User $sessionUser */
$sessionUser = $_SESSION['user'];

if ($sessionUser->isSuperuser()) {
	$configMapper = new \EOTC\ConfigMapper();

	$firstReminder = $configMapper->findByColumn('name', 'first_reminder')->first();
	$dailyReminder = $configMapper->findByColumn('name', 'daily_reminder')->first();
	$urgentThreshold = $configMapper->findByColumn('name', 'urgent_threshold')->first();
	$policy = $configMapper->findByColumn('name', 'policy')->first();
	$ramsWarning = $configMapper->findByColumn('name', 'rams_warning')->first();

	/* Render it out
	 */
	$this->setFile(TEMPLATE_content, 'modules/eotc/config/modify.html');

	$this->setVar(array(
		'CONFIG_FIRST_REMINDER' => $firstReminder->getValue(),
		'CONFIG_DAILY_REMINDER' => $dailyReminder->getValue(),
		'CONFIG_URGENT_THRESHOLD' => $urgentThreshold->getValue(),
		'CONFIG_POLICY' => $policy->getValue(),
		'CONFIG_RAMS_WARNING' => $ramsWarning->getValue(),
	));
}
else {
	error(19);
}

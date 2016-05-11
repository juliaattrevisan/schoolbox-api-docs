<?php
/**
 * 
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2014, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

require_once('../../../library/Bootstrap.php');

if(isset($this) || empty($_POST)) {
	SitePage::localRedir('/adminv2/messages');
}

foreach($_POST['role'] as $roleId => $data) {
	if(isset($data['day']) && is_array($data['day'])) {
		// set to $data
		$days = implode(',', $data['day']);
		list($hour, $min) = explode(':', $data['time'], 2);
		$hour = intval($hour);
		$min  = intval($min);

		$schedule = "$min $hour * * $days";
	}
	else {
		// unset the schedule for this role
		$schedule = "0";
	}

	/** @var \Schoolbox\Role $role */
	$role = \Schoolbox\RoleMapper::getInstance()->findById($roleId);
	$role->setDigestSchedule($schedule);
	\Schoolbox\RoleMapper::getInstance()->save($role);
}

// advise digest daemon to reload
$cmdr = new \Schoolbox\Realtime\Digest\DigestCommander();
$cmdr->adviseReload();

// advise schedule daemon to reload as well
$cmdr = new \Schoolbox\Realtime\Rerouter\RerouterCommander(\Schoolbox\UserMapper::getInstance());
$cmdr->adviseReload();

SitePage::localRedir('/adminv2/messages');

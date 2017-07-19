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

use EOTC\Authenticator;
use EOTC\ConfigMapper;

new Authenticator($this);

/** @var \Schoolbox\User $sessionUser */
$sessionUser = $_SESSION['user'];

if ($sessionUser->isSuperuser()) {
	$configMapper = new ConfigMapper();

	$allConfigs = $configMapper->findAll();

	/* Render it out
	 */
	$this->setFile(TEMPLATE_content, 'modules/eotc/config/modify.html');

	$this->setVar([
		'CONFIG_FIRST_REMINDER' => $allConfigs->getBy('name', 'first_reminder')->getValue(),
		'CONFIG_DAILY_REMINDER' => $allConfigs->getBy('name', 'daily_reminder')->getValue(),
		'CONFIG_URGENT_THRESHOLD' => $allConfigs->getBy('name', 'urgent_threshold')->getValue(),
		'CONFIG_POLICY' => $allConfigs->getBy('name', 'policy')->getValue(),
		'CONFIG_RAMS_WARNING' => $allConfigs->getBy('name', 'rams_warning')->getValue(),
        'CONFIG_EXTERNAL_DB_SYNC_CHECKED' => $allConfigs->getBy('name', 'external_db_sync')->getValue() == 1 ? 'checked' : ''
	]);
}
else {
	error(19);
}

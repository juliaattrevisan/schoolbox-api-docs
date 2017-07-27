<?php
/**
 * Config Modify
 *
 * Modify EOTC Configuration settings
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2011, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */
use EOTC\Authenticator;
use EOTC\ConfigMapper;
use Schoolbox\Error\Mapper as ErrorMapper;
use Schoolbox\User;

new Authenticator($this);

/** @var User $sessionUser */
$sessionUser = $_SESSION['user'];

if ($sessionUser->isSuperuser()) {
	$configMapper = new ConfigMapper();

	$allConfigs = $configMapper->findAll();

	/* Render it out
	 */
	$this->setFile(TEMPLATE_content, 'modules/eotc/config/modify.html');

	$this->setVar([
        'CONFIG_FIRST_REMINDER'                      => $allConfigs->getBy('name', 'first_reminder')->getValue(),
        'CONFIG_DAILY_REMINDER'                      => $allConfigs->getBy('name', 'daily_reminder')->getValue(),
        'CONFIG_URGENT_THRESHOLD'                    => $allConfigs->getBy('name', 'urgent_threshold')->getValue(),
        'CONFIG_POLICY'                              => $allConfigs->getBy('name', 'policy')->getValue(),
        'CONFIG_RAMS_WARNING'                        => $allConfigs->getBy('name', 'rams_warning')->getValue(),
        'CONFIG_EXTERNAL_DB_SYNC_CHECKED'            => (int)$allConfigs->getBy('name', 'external_db_sync')->getValue() == 1 ? 'checked' : '',
        'CONFIG_STUDENTS_ATTENDEES_REQUIRED_CHECKED' => (int)$allConfigs->getBy('name', 'student_attendees_required')->getValue() == 1 ? 'checked' : ''
	]);
}
else {
	error(ErrorMapper::ERROR_PERM_PAGE_VIEW);
}

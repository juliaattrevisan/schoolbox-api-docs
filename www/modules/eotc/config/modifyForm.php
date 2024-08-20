<?php

/**
 * Save changes to EOTC configuration
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

use EOTC\Authenticator;
use EOTC\ConfigMapper;
use Schoolbox\Error\Mapper as ErrorMapper;
use Schoolbox\User;

require_once __DIR__ . '/../../../../conf/core/require.php';
$kernel = new \Schoolbox\Application\WebRequest\Kernel();

/** @var SitePage $page */
$page = $kernel->get('sitePage');
$page->initSession();

new Authenticator($page);

/** @var User $sessionUser */
$sessionUser = $_SESSION['user'];

if ($sessionUser->isSuperuser()) {
    $configMapper = ConfigMapper::getInstance();
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

    $mandatoryStudents = $allConfigs->getBy('name', 'student_attendees_required');
    $mandatoryStudents->setValue((int)$_POST['studentAttendeesRequired']);

    /* Synergetic Filetypes are comma-separated in EOT Config
     * Explode this csv and forcibly uppercase everything
     */
    $arrayFileTypes = explode(',', $_POST['externalFiletypeBlacklist']);

    $validFileTypes = array_map(static function($elem) {
        return strtoupper(trim($elem));
    }, $arrayFileTypes);

    // Sort it alphabetically

    asort($validFileTypes);

    $externalFiletypeBlacklist = $allConfigs->getBy('name', 'external_filetype_blacklist');
    $externalFiletypeBlacklist->setValue(implode(",", $validFileTypes));

    $externalEventtype = $allConfigs->getBy('name', 'external_eventtype');
    $externalEventtype->setValue(trim($_POST['externalEventtype']));

    // Save config changes back to database

    $configMapper->save($firstReminder);
    $configMapper->save($dailyReminder);
    $configMapper->save($urgentThreshold);
    $configMapper->save($policy);
    $configMapper->save($ramsWarning);
    $configMapper->save($externalDbSync);
    $configMapper->save($externalFiletypeBlacklist);
    $configMapper->save($mandatoryStudents);
    $configMapper->save($externalEventtype);

    // Redirect back to the config page

    $page->localRedir('/modules/eotc/config/modify');
} else {
    error(ErrorMapper::ERROR_PERM_PAGE_VIEW);
}

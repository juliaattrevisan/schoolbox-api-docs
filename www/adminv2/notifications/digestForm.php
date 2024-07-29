<?php

/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

// If in SitePage context
use Alaress\Redis;
use Schoolbox\Error\Mapper as ErrorMapper;

if (isset($this)) {
    SitePage::localRedir('/adminv2/notifications');
}

require_once __DIR__ . '/../../../conf/core/require.php';
$kernel = new \Schoolbox\Application\WebRequest\Kernel();

if (empty($_POST)) {
    SitePage::localRedir('/adminv2/notifications');
}

if (!$kernel->getDependencyInjector()->get('session')->get('user')->isSuperuser()) {
    ErrorMapper::getInstance()->generateException(ErrorMapper::ERROR_PERM_PAGE_VIEW, null, '', 403);
}

foreach ($_POST['role'] as $roleId => $data) {
    if (isset($data['day']) && is_array($data['day'])) {
        // set to $data
        $days = implode(',', $data['day']);
        [$hour, $min] = explode(':', $data['time'], 2);
        $hour = intval($hour);
        $min  = intval($min);

        $schedule = "$min $hour * * $days";
    } else {
        // unset the schedule for this role
        $schedule = "0";
    }

    /** @var \Schoolbox\Role $role */
    $role = \Schoolbox\RoleMapper::getInstance()->findById($roleId);
    $role->setDigestSchedule($schedule);
    \Schoolbox\RoleMapper::getInstance()->save($role);
}

$redis    = Redis::getSignalQueueClientInstance();
$instance = INSTANCE_ID;

// advise digest daemon to reload
$redis->publish("digestd.$instance.reload", '');

// advise schedule daemon to reload as well
$redis->publish("scheduled.$instance.reload", '');

SitePage::localRedir('/adminv2/notifications');

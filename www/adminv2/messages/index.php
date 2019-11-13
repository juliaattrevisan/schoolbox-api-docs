<?php
/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

use Schoolbox\IConfigLoader;


if(!isset($this)) {
    require_once __DIR__ . '/../../../conf/core/require.php';
    $kernel = new \Schoolbox\Application\WebRequest\Kernel();
    SitePage::localRedir('/adminv2/messages');
}


if (!$_SESSION['user']->isSuperuser()) {
    error(19);
}

/** @var SitePage $this */

if(in_array($this->arg[0], array('enable','disable'))) {
    $this->localRedir('/adminv2/messages/indexForm.php?enabled='.$this->arg[0]);
}

$this->setFile(TEMPLATE_content, '/adminv2/messages/index.html');
$nav = new AdminV2_Nav($this->getDi()->get('config'));
$nav->parse($this, 'messages');
$this->setTitle('Admin - Messages');

$this->setBlock(TEMPLATE_content, 'global_online', 'global_online_block');
$this->setBlock(TEMPLATE_content, 'global_offline', 'global_offline_block');
$this->setBlock(TEMPLATE_content, 'digest_offline', 'digest_offline_block');
$this->setBlock(TEMPLATE_content, 'digest_online', 'digest_online_block');
$this->setBlock(TEMPLATE_content, 'push_online', 'push_online_block');
$this->setBlock(TEMPLATE_content, 'push_offline', 'push_offline_block');
$this->setBlock('digest_online', 'role_digest', 'role_digest_block');
$this->setBlock('role_digest', 'day_check', 'day_check_block');

// global setting
/** @var IConfigLoader $config */
$config  = $this->getDi()->get('config');
$enabled = $config->get('messaging_enabled');
$pushing = $config->get('push_notifications_enabled');

if($enabled) {
    $this->setVar(array(
        'MSG_SYS_ENABLED' => 'ENABLED',
        'MSG_SYS_TOGGLE'  => 'disable',
    ));

    // compile a week to test against
    /** @var DateTime[] $dayTests */
    $dayTests = array();
    for($i = 0; $i < 7; ++$i) {
        $d = new DateTime("+$i day");
        $dayTests[$d->format('w')] = $d;
    }
    // aaaand put 0 at the start
    ksort($dayTests);

    foreach(\Schoolbox\RoleMapper::getInstance()->findAll() as $role) {

        /** @var \Schoolbox\Role $role */

        $this->setVar(array(
            'ROLE_NAME' => Scrub::text($role->getName()),
            'ROLE_ID'   => $role->getId(),
            'TIME_VALUE' => '00:00'
        ));

        $schedule = $role->getDigestSchedule();

        // Only attempt to parse crontab if it's valid, don't create a fake one.
        // This prevents an issue where the check against
        // \Schoolbox\Realtime\Digest\DigestScheduler::NEVER takes forever.
        $datecron = null;
        $timecron = null;
        try {
            $datecron = \Cron\CronExpression::factory($schedule)
                ->setPart(\Cron\CronExpression::MINUTE, '*')
                ->setPart(Cron\CronExpression::HOUR, '*');

            $timecron = \Cron\CronExpression::factory($schedule)
                ->setPart(\Cron\CronExpression::DAY, '*')
                ->setPart(\Cron\CronExpression::MONTH, '*')
                ->setPart(\Cron\CronExpression::WEEKDAY, '*');
        } catch(InvalidArgumentException $e) {
            // no schedule for group
        }

        foreach($dayTests as $i => $date) {
            $checked = false;
            try {
                if ($datecron instanceof \Cron\CronExpression) {
                    $checked = $datecron->isDue($date);
                }
            } catch(RuntimeException $e) {
                $checked = false;
            }
            $dayName = $date->format('l');
            $char = substr($dayName, 0, 1);

            $this->setVar(array(
                'DAY_VALUE' => $i,
                'DAY_CHECKED' => $checked ? 'checked="checked"' : '',
                'DAY_CHAR' => $char,
                'DAY_NAME' => $dayName
            ));

            $this->parse('day_check_block', 'day_check', true);
        }

        $valid = false;
        $hr = '00';
        $mi = '00';
        try {
            if ($timecron instanceof \Cron\CronExpression) {
                $nextTime = $timecron->getNextRunDate();
                $hr = $nextTime->format('H');
                $mi = $nextTime->format('i');
                $valid = true;
            }
        } catch(RuntimeException $e) {
            $valid = false;
        }

        if ($valid) {
            $this->setVar('TIME_VALUE', sprintf("%02d:%02d", $hr, $mi));
        }

        $this->parse('role_digest_block', 'role_digest', true);
        $this->setVar('day_check_block', '');
    }

    if ($pushing) {
        $this->parse('push_online_block', 'push_online');
        $this->setVar('push_offline_block', '');

    } else {
        // messaging online, but push offline:
        $this->setVar('push_online_block', '');
        $this->setVar('PUSH_OFFLINE', 'Enable push notifications to set app settings.');
        $this->parse('push_offline_block', 'push_offline');
    }

    $this->parse('digest_online_block', 'digest_online');
    $this->setVar('digest_offline_block', '');

    $this->parse('global_online_block', 'global_online');
    $this->setVar('global_offline_block', '');
} else {
    $this->setVar(array(
        'MSG_SYS_ENABLED' => 'DISABLED',
        'MSG_SYS_TOGGLE'  => 'enable'
    ));

    $this->parse('digest_offline_block', 'digest_offline');
    $this->setVar('digest_online_block', '');

    $this->parse('global_offline_block', 'global_offline');
    $this->setVar('global_online_block', '');

    $this->setVar('PUSH_OFFLINE', 'Enable messaging and push notifications to set to set app settings.');
    $this->parse('push_offline_block', 'push_offline');
    $this->setVar('push_online_block', '');
}

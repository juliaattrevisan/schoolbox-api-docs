<?php
/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2014, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

if(!isset($this)) {
    require_once __DIR__ . '/../../../include/Bootstrap.php';
    SitePage::localRedir('/adminv2/messages');
}

$groupHandler = new Group();

if (!$groupHandler->isSuperuser()) {
    error(19);
}

/** @var SitePage $this */

if(in_array($this->arg[0], array('enable','disable'))) {
    $this->localRedir('/adminv2/messages/indexForm.php?enabled='.$this->arg[0]);
}

$this->setFile(TEMPLATE_content, '/adminv2/messages/index.html');
$nav = new AdminV2_Nav();
$nav->parse($this, 'messages');
$this->setTitle('Admin - Messages');

$this->setBlock(TEMPLATE_content, 'global_online', 'global_online_block');
$this->setBlock(TEMPLATE_content, 'global_offline', 'global_offline_block');
$this->setBlock(TEMPLATE_content, 'digest_offline', 'digest_offline_block');
$this->setBlock(TEMPLATE_content, 'digest_online', 'digest_online_block');
$this->setBlock('digest_online', 'role_digest', 'role_digest_block');
$this->setBlock('role_digest', 'day_check', 'day_check_block');

// global setting

$enabled = \Schoolbox\Config\ConfigMapper::getInstance()->get('messaging_enabled');

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

    $timeDatetime = new DateTime();
    $timeTests = array();
    for($i = 0; $i < 24; ++$i) {
        for($j = 0; $j < 60; $j += 30) {
            $timeTests[] = array($i, $j);
        }
    }

    foreach(\Schoolbox\RoleMapper::getInstance()->findAll() as $role) {

        /** @var \Schoolbox\Role $role */

        $this->setVar(array(
            'ROLE_NAME' => Scrub::text($role->getName()),
            'ROLE_ID'   => $role->getId(),
            'TIME_VALUE' => '00:00'
        ));

        $schedule = $role->getDigestSchedule();

        switch($schedule) {
            case \Schoolbox\Role::DIGEST_DAILY:
                $schedule = \Schoolbox\Realtime\Digest\DigestScheduler::DAILY;
                break;
            case \Schoolbox\Role::DIGEST_WEEKLY:
                $schedule = \Schoolbox\Realtime\Digest\DigestScheduler::WEEKLY;
                break;
        }

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
                ->setPart(\Cron\CronExpression::WEEKDAY, '*')
                ->setPart(\Cron\CronExpression::YEAR, '*');
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
            $char = substr($date->format('D'), 0, 1);

            $this->setVar(array(
                'DAY_VALUE' => $i,
                'DAY_CHECKED' => $checked ? 'checked="checked"' : '',
                'DAY_CHAR' => $char
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

        foreach($timeTests as $time) {
            if($valid) {
                $selected = ($hr == $time[0] && $mi == $time[1]);
            } else {
                $selected = false;
            }

            if ($selected) {
                $this->setVar(array(
                    'TIME_VALUE' => sprintf("%02d:%02d", $time[0], $time[1])
                ));
            }
        }

        $this->parse('role_digest_block', 'role_digest', true);
        $this->setVar('day_check_block', '');
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
}

<?php
/**
 * 
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2014, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace Schoolbox\Realtime\Digest;


use Alaress\Scheduler\CronScheduler;
use Alaress\WorkerCrew\Pooling\DefaultDispatchStrategy;
use Alaress\WorkerCrew\Pooling\DispatchStrategyInterface;
use InvalidArgumentException;
use React\EventLoop\LoopInterface;
use React\Stomp\Client;
use RuntimeException;
use Schoolbox\Realtime\Schedule\Trigger\DigestTrigger;
use Schoolbox\Role;
use Schoolbox\RoleMapper;

class DigestScheduler extends CronScheduler {

    /**
     * @param LoopInterface $loop
     * @param DispatchStrategyInterface $dispatchStrategy
     * @param \Alaress\Cli\Debug $cli
     */
    public function __construct($loop, $dispatchStrategy, $cli) {
        parent::__construct($loop, $dispatchStrategy, $cli);
        $this->method = DefaultDispatchStrategy::TASK_DIGEST;

        // read each role entry, and schedule a cron based on it
		foreach(RoleMapper::getInstance()->findAll() as $role) {
			/** @var Role $role */

			$schedule = $role->getDigestSchedule();

			// cast 0 to "0", because loose typing equality because PHP -_-
			if($schedule == (string)Role::DIGEST_NEVER) {
				continue;
			}

			// convert old DAILY and WEEKLY enum into crontab
			switch($schedule) {
				case Role::DIGEST_DAILY:
					$schedule = '0 6 * * 1-5'; // weekdaily, 6am
					break;
				case Role::DIGEST_WEEKLY:
					$schedule = '0 11 * * 5'; // Friday, 11am
					break;
			}

			try {
				$this->scheduleNotification(new DigestTrigger($schedule, $role));
			}
			catch(InvalidArgumentException $e) {
				echo $e->getMessage(), "\n";
			}
		}
	}

} 
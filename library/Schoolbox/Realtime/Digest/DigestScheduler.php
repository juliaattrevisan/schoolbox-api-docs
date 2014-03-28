<?php
/**
 * 
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2014, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace Schoolbox\Realtime\Digest;


use Alaress\WorkerCrew\Pooling\DefaultDispatchStrategy;
use InvalidArgumentException;
use React\Stomp\Client;
use Schoolbox\Realtime\Schedule\AbstractScheduler;
use Schoolbox\Realtime\Schedule\Recurrence\Recurrence;
use Schoolbox\Realtime\Schedule\Trigger\DigestTrigger;
use Schoolbox\Role;
use Schoolbox\RoleMapper;

class DigestScheduler extends AbstractScheduler {

	/**
	 * Return the method that Workers will use to process this task
	 *
	 * @return string
	 */
	protected function getMethod() {
		return DefaultDispatchStrategy::TASK_DIGEST;
	}


	/**
	 * Set up this scheduler
	 */
	protected function initialise() {

		// this Scheduler is initialised by reading a config (WHERE?) and scheduling the FIRST of each type
		// which will subsequently spawn their future events

		foreach(RoleMapper::getInstance()->findAll() as $role) {
			/** @var Role $role */

			$schedule = $role->getDigestSchedule();

			// not digesting, ignore and move on
			if($schedule == Role::DIGEST_NEVER) {
				continue;
			}

			// convert old DAILY and WEEKLY enum into crontab
			switch($schedule) {
				case Role::DIGEST_DAILY:
					$schedule = '0 9 * * *'; // daily, 9am
					break;
				case Role::DIGEST_WEEKLY:
					$schedule = '0 9 * * 1'; // Monday, 9am
					break;
			}

			try {
				$this->scheduleNotification(new DigestTrigger(new Recurrence($schedule), $role));
			}
			catch(InvalidArgumentException $e) {
				echo $e->getMessage(), "\n";
			}
		}
	}

	/**
	 * Handles a response from a Worker
	 *
	 * @param DigestTrigger $trigger - the identifier passed as the first argument to Dispatcher::enqueue()
	 * @param string $command        - the command that was executed by the Worker
	 * @param array $data            - the data with which the Worker responded
	 * @return void                  - this method does not return any value
	 */
	public function handleResponse($trigger, $command, $data) {
		$data = unserialize($data['0']);

		// worker did the work, advise of the outcome
		$this->verboser->v("Emails sent: $data");

		// and reschedule the trigger to go again
		$this->scheduleNotification($trigger);
	}


} 
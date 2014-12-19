<?php
/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2014, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace Alaress\Scheduler;


use Alaress\Scheduler\Trigger\TriggerInterface;

/**
 * A scheduler for recurring tasks; handling of a response is simply to reschedule it
 *
 * Class CronScheduler
 * @package Alaress\Scheduler
 */
abstract class CronScheduler extends AbstractScheduler {


    /**
     * Handles a response from a Worker
     *
     * @param TriggerInterface $trigger - the identifier passed as the first argument to Dispatcher::enqueue()
     * @param string $command           - the command that was executed by the Worker
     * @param array $data               - the data with which the Worker responded
     * @return void                     - this method does not return any value
     */
    public function handleResponse($trigger, $command, $data) {
        // and reschedule the trigger to go again
        $this->report($data);
	    $this->log->info(sprintf("Rescheduling trigger '%s' for %s", $trigger->getIdString(), date('Y-m-d H:i:s', $trigger->getTimestamp())),
            array('char' => '+')
        );
	    $this->scheduleNotification($trigger);
    }

	/**
	 * Report in an appropriate way, based on the data returned by the worker
	 *
	 * @param $data
	 * @return mixed
	 */
	protected abstract function report($data);

} 
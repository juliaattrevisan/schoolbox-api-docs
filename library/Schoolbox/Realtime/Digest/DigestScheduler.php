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
use Alaress\Scheduler\Trigger\TriggerInterface;
use Alaress\WorkerCrew\Pool;
use Alaress\WorkerCrew\Pooling\DefaultDispatchStrategy;
use Alaress\WorkerCrew\Pooling\DispatchStrategyInterface;
use InvalidArgumentException;
use Psr\Log\LoggerInterface;
use React\EventLoop\LoopInterface;
use React\Stomp\Client;
use React\Stomp\Protocol\Frame;
use RuntimeException;
use Schoolbox\IConfigLoader;
use Schoolbox\Notif\Formatter\CompressedFormatter;
use Schoolbox\Realtime\Schedule\Trigger\DigestTrigger;
use Schoolbox\Role;
use Schoolbox\RoleMapper;

class DigestScheduler extends CronScheduler {

	const NEVER  = '0 1 31 2 *'; // 1am, Feb 31st (good luck)
	const DAILY  = '0 6 * * 1-5'; // 6am, weekdays
	const WEEKLY = '0 11 * * 5'; // 11am, Fridays

	const QUEUE_COMMAND  = '.sb.cmd.digest';

	/** @var DigestTrigger[] */
	protected $triggers = array();


    /**
     * @param LoopInterface $loop
     * @param DispatchStrategyInterface $dispatchStrategy
     * @param Client $client
     * @param LoggerInterface $log
     * @param IConfigLoader $conf
     */
    public function __construct($loop, $dispatchStrategy, $client, $log, $conf = null) {
        parent::__construct($loop, $dispatchStrategy, $log, $conf);
        $this->method = DefaultDispatchStrategy::TASK_DIGEST;

	    $self = $this;
	    $client->connect()->then(function($client) use ($self) {
		    /** @var Client $client */
		    $client->subscribe(INSTANCE_ID.DigestScheduler::QUEUE_COMMAND, array($self, 'onCommand'));
	    });

		$this->on(SIGHUP, array($this, 'onReload'));

		// instantiate schedule
		$this->reload();
	}


	/**
	 * Callback executed when SIGHUP received by process
	 */
	public function onReload() {
		$this->conf->dropCache();
		$this->reload();
	}


	/**
	 * Drop existing schedule and reinitialise
	 *
	 * @return bool
	 */
	public function reload() {
		// drop existing
		foreach($this->triggers as $k => $t) {
			$this->unschedule($t);
			unset($this->triggers[$k]);
		}

        // gently release workers in case they're digesting
        $this->getDispatcher()->releaseWorkerPool(Pool::RELEASE_SOFT);

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
					$schedule = self::DAILY; // weekdaily, 6am
					break;
				case Role::DIGEST_WEEKLY:
					$schedule = self::WEEKLY; // Friday, 11am
					break;
			}

			try {
				$this->triggers[$role->getId()] = new DigestTrigger($schedule, $role);
				$this->scheduleNotification($this->triggers[$role->getId()]);
			}
			catch(InvalidArgumentException $e) {
				$this->log->error(get_class($e) . "in DigestScheduler: " . $e->getMessage());
			}
			catch(RuntimeException $e) {
				$this->log->error(get_class($e) . "in DigestScheduler: " . $e->getMessage());
			}
		}
	}


    /**
     * @param TriggerInterface $trigger
     * @param string           $command
     * @param array            $data
     */
    public function handleError($trigger, $command, $data)
    {
        // we are assured that the worked that caused this error has already died
        $this->log->warning("Worker failed to complete, resetting digest triggers");
        $this->reload();
    }


    /**
	 * Report in an appropriate way, based on the data returned by the worker
	 *
	 * @param $data
	 * @return mixed
	 */
	protected function report($data) {
		$this->log->notice("Emails sent: ".unserialize($data[0]));
	}


	/**
	 * @param Frame $frame
	 */
	public function onCommand($frame) {
		$this->log->info("Received command");
		$commander = new DigestCommander();
		$body = CompressedFormatter::uncompress($frame->body);
		$commander->exec($this, $body['message']);
	}

} 
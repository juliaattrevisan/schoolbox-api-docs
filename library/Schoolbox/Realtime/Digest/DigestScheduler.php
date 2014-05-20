<?php
/**
 * 
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2014, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace Schoolbox\Realtime\Digest;


use Alaress\Scheduler\CommandableInterface;
use Alaress\Scheduler\CronScheduler;
use Alaress\WorkerCrew\Pooling\DefaultDispatchStrategy;
use Alaress\WorkerCrew\Pooling\DispatchStrategyInterface;
use InvalidArgumentException;
use React\EventLoop\LoopInterface;
use React\Stomp\Client;
use React\Stomp\Factory;
use React\Stomp\Protocol\Frame;
use RuntimeException;
use Schoolbox\Notif\MQ\Connector;
use Schoolbox\Notif\Notif;
use Schoolbox\Realtime\Schedule\Trigger\DigestTrigger;
use Schoolbox\Role;
use Schoolbox\RoleMapper;

class DigestScheduler extends CronScheduler implements CommandableInterface {

	const NEVER  = '0 1 31 2 *'; // 1am, Feb 31st (good luck)
	const DAILY  = '0 6 * * 1-5'; // 6am, weekdays
	const WEEKLY = '0 11 * * 5'; // 11am, Fridays

	const QUEUE_COMMAND  = '.sb.cmd.digest';

	/** @var DigestTrigger[] */
	protected $triggers;

	/**
	 * Binds the queues that the Scheduler requires
	 */
	public static function bindQueues() {
		// make sure our endpoints exist
		$mqc = new Connector();
		$queues = array(
			INSTANCE_ID . self::QUEUE_COMMAND       => '*.' . Notif::TOPIC_CMD_DIGEST
		);

		foreach($queues as $q => $route) {
			$mqc->declareQueue($q);
			$mqc->bindQueue($q, $route);
		}
	}


    /**
     * @param LoopInterface $loop
     * @param DispatchStrategyInterface $dispatchStrategy
     * @param \Alaress\Cli\Debug $cli
     */
    public function __construct($loop, $dispatchStrategy, $cli) {
        parent::__construct($loop, $dispatchStrategy, $cli);
        $this->method = DefaultDispatchStrategy::TASK_DIGEST;
	    self::bindQueues();

	    $stompFactory = new Factory($this->loop);
	    $client = $stompFactory->createClient(array(
		    'host'      => $this->conf->get('amqp_host'),
		    'port'      => $this->conf->get('amqp_stomp_port'),
		    'vhost'     => '/',
		    'login'     => $this->conf->get('amqp_user'),
		    'passcode'  => $this->conf->get('amqp_pass'),
	    ));

	    $self = $this;
	    $client->on('connect', function($client) use ($self) {
		    /** @var Client $client */
		    $client->subscribe(INSTANCE_ID.DigestScheduler::QUEUE_COMMAND, array($self, 'onCommand'));
	    });

	    // instantiate schedule
		$this->reload();
	}


	/**
	 * Drop existing schedule and reinitialise
	 *
	 * @return bool
	 */
	public function reload() {
		// drop existing
		foreach($this->triggers as $t) {
			$this->unschedule($t);
		}

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
				echo $e->getMessage(), "\n";
			}
			catch(RuntimeException $e) {
				echo $e->getMessage(), "\n";
			}
		}
	}


	/**
	 * Report in an appropriate way, based on the data returned by the worker
	 *
	 * @param $data
	 * @return mixed
	 */
	protected function report($data) {
		$this->debug->v("Emails sent: ".unserialize($data[0]));
	}


	/**
	 * @param Frame $frame
	 */
	public function onCommand($frame) {
		$commander = new DigestCommander($this);
		$commander->exec($frame->body);
	}

} 
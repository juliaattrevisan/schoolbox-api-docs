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
use Alaress\Scheduler\Trigger\RecurringTrigger;
use Alaress\Scheduler\Trigger\TriggerInterface;
use Alaress\WorkerCrew\Pool;
use Alaress\WorkerCrew\Pooling\DefaultDispatchStrategy;
use Alaress\WorkerCrew\Pooling\DispatchStrategyInterface;
use InvalidArgumentException;
use Psr\Log\LoggerInterface;
use React\EventLoop\LoopInterface;
use RuntimeException;
use Schoolbox\IConfigLoader;
use Schoolbox\Realtime\Schedule\Trigger\DigestTrigger;
use Schoolbox\Realtime\Schedule\Trigger\Factory\DigestTriggerFactory;
use Schoolbox\Realtime\WorkNotifier;
use Schoolbox\Role;
use Schoolbox\RoleMapper;

class DigestScheduler extends CronScheduler
{

	/** @var RecurringTrigger[] */
	protected $triggers = [];

    /** @var DigestTriggerFactory */
    private $factory;

    /**
     * @param LoopInterface             $loop
     * @param DispatchStrategyInterface $dispatchStrategy
     * @param LoggerInterface           $log
     * @param IConfigLoader             $conf
     * @param DigestTriggerFactory      $factory
     */
    public function __construct(
        LoopInterface $loop,
        DispatchStrategyInterface $dispatchStrategy,
        LoggerInterface $log,
        IConfigLoader $conf,
        DigestTriggerFactory $factory
    ) {
        parent::__construct($loop, $dispatchStrategy, $log, $conf);
        $this->method = DefaultDispatchStrategy::TASK_DIGEST;
        $this->factory = $factory;

		$this->on(SIGHUP, [ $this, 'onReload' ]);

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
	 * @return void
	 */
	public function reload()
    {
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

			try {
				$this->triggers[$role->getId()] = $this->factory->build($schedule, $role);
				$this->scheduleNotification($this->triggers[$role->getId()]);

			} catch(InvalidArgumentException $e) {
				$this->log->error(get_class($e) . 'in DigestScheduler: ' . $e->getMessage());

			} catch(RuntimeException $e) {
				$this->log->error(get_class($e) . 'in DigestScheduler: ' . $e->getMessage());
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
        $this->log->warning('Worker failed to complete, resetting digest triggers');
        $this->reload();
    }


    /**
	 * Report in an appropriate way, based on the data returned by the worker
	 *
	 * @param $data
	 * @return void
	 */
	protected function report($data)
    {
		$this->log->notice('Emails sent: ' . unserialize($data[0]));
	}

}
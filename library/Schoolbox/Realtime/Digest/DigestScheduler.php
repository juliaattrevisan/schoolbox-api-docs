<?php

/**
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Schoolbox\Realtime\Digest;

use Alaress\Date\IClock;
use Alaress\Scheduler\CronScheduler;
use Alaress\Scheduler\Trigger\TriggerInterface;
use Alaress\WorkerCrew\Pool;
use Alaress\WorkerCrew\Pooling\DefaultDispatchStrategy;
use Alaress\WorkerCrew\Pooling\DispatchStrategyInterface;
use InvalidArgumentException;
use Psr\Log\LoggerInterface;
use React\EventLoop\LoopInterface;
use RuntimeException;
use Schoolbox\IConfigLoader;
use Schoolbox\Realtime\Schedule\Trigger\Factory\DigestTriggerFactory;
use Schoolbox\Role;
use Schoolbox\RoleMapper;

/**
 * Class DigestScheduler
 *
 * @package Schoolbox\Realtime\Digest
 */
class DigestScheduler extends CronScheduler
{

    /**
     * @var DigestTriggerFactory
     */
    private $factory;


    /**
     * @var IConfigLoader
     */
    private $conf;


    /**
     * @var RoleMapper
     */
    private $roleMapper;


    /**
     * @param LoopInterface             $loop
     * @param DispatchStrategyInterface $dispatchStrategy
     * @param LoggerInterface           $log
     * @param IClock                    $clock
     * @param RoleMapper                $roleMapper
     * @param IConfigLoader             $conf
     * @param DigestTriggerFactory      $factory
     */
    public function __construct(
        LoopInterface $loop,
        DispatchStrategyInterface $dispatchStrategy,
        LoggerInterface $log,
        IClock $clock,
        RoleMapper $roleMapper,
        IConfigLoader $conf,
        DigestTriggerFactory $factory
    ) {
        parent::__construct($loop, $dispatchStrategy, $log, $clock);
        $this->roleMapper = $roleMapper;
        $this->conf       = $conf;
        $this->factory    = $factory;

        // instantiate schedule
        $this->reload();
    }


    /**
     * (Re)compute the triggers for this particular scheduler
     *
     * @return TriggerInterface[]
     */
    protected function computeTriggers()
    {
        $triggers = [];

        // gently release workers in case they're digesting
        $this->getDispatcher()->releaseWorkerPool(Pool::RELEASE_SOFT);

        // read each role entry, and schedule a cron based on it
        foreach ($this->roleMapper->findAll() as $role) {
            $schedule = $role->getDigestSchedule();

            if ($schedule === null || $schedule === Role::DIGEST_NEVER) {
                continue;
            }

            try {
                $triggers[$role->getId()] = $this->factory->build($schedule, $role);
            } catch (InvalidArgumentException $e) {
                $this->logException($e);
            } catch (RuntimeException $e) {
                $this->logException($e);
            }
        }

        return $triggers;
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
     * @param array $data
     * @return void
     */
    protected function report($data)
    {
        $this->log->notice('Emails sent: ' . unserialize($data[0]));
    }


    /**
     * @return string
     */
    protected function getMethod()
    {
        return DefaultDispatchStrategy::TASK_DIGEST;
    }


    /**
     * @return void
     */
    protected function dropCache()
    {
        $this->conf->dropCache();
    }
}

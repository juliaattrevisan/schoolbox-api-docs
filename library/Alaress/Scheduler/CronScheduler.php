<?php

/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Alaress\Scheduler;

use Alaress\Date\IClock;
use Alaress\Redis\Async\Event\IEvent;
use Alaress\Redis\Async\Event\Message;
use Alaress\Redis\Async\EventListener;
use Alaress\Scheduler\Trigger\TriggerInterface;
use Alaress\WorkerCrew\Pooling\DispatchStrategyInterface;
use InvalidArgumentException;
use Psr\Log\LoggerInterface;
use React\EventLoop\LoopInterface;
use RuntimeException;

/**
 * A scheduler for recurring tasks; handling of a response is simply to reschedule it.
 *
 * @package Alaress\Scheduler
 */
abstract class CronScheduler extends AbstractScheduler implements EventListener
{

    /** @var TriggerInterface[] */
    private $triggers = [];

    /**
     * @param LoopInterface             $loop
     * @param DispatchStrategyInterface $dispatchStrategy
     * @param LoggerInterface           $log
     * @param IClock                    $clock
     */
    public function __construct(LoopInterface $loop, DispatchStrategyInterface $dispatchStrategy, LoggerInterface $log, IClock $clock)
    {
        parent::__construct($loop, $dispatchStrategy, $log, $clock);

        $this->on(SIGHUP, [ $this, 'onReload' ]);
    }


    /**
     * Handles a response from a Worker
     *
     * @param TriggerInterface $trigger - the identifier passed as the first argument to Dispatcher::enqueue()
     * @param string           $command - the command that was executed by the Worker
     * @param array            $data    - the data with which the Worker responded
     * @throws InvalidArgumentException
     * @return void                     - this method does not return any value
     */
    public function handleResponse($trigger, $command, $data)
    {
        // and reschedule the trigger to go again
        $this->report($data);

        $this->log->info(
            sprintf("Rescheduling trigger '%s' for %s", $trigger->getIdString(), date('Y-m-d H:i:s', $trigger->getTimestamp())),
            [ 'char' => '+' ]
        );

        $this->scheduleNotification($trigger);
    }

    /**
     * Callback executed when SIGHUP received by process
     */
    public function onReload()
    {
        $this->dropCache();
        $this->reload();
    }


    /**
     * Handle the given redis event
     *
     * @param IEvent $event
     * @return void
     */
    public function handle(IEvent $event)
    {
        if (!$event instanceof Message) {
            // ignore pings
            return;
        }

        // comes in the form 'digestd.vagrantx.reload'
        list (, , $action) = explode('.', $event->getChannel());

        if ($action === 'reload') {
            // this is the only action we currently know about
            $this->onReload();
        }
    }


    /**
     * @param \Exception $e
     * @return void
     */
    protected function logException(\Exception $e)
    {
        $this->log->error(sprintf(
            '%s in %s: %s',
            get_class($e),
            substr(static::class, strrpos(static::class, '\\')),
            $e->getMessage()
        ), [ 'exception' => $e ]);
    }


    /**
     * Drop existing schedule and reinitialise
     *
     * @return void
     */
    protected function reload()
    {
        // drop existing
        foreach ($this->triggers as $key => $trigger) {
            $this->unschedule($trigger);
            unset($this->triggers[$key]);
        }

        // compute the set of triggers
        $this->triggers = $this->computeTriggers();


        foreach ($this->triggers as $id => $trigger) {
            try {
                $this->scheduleNotification($trigger);
            } catch (InvalidArgumentException $e) {
                $this->logException($e);
            } catch (RuntimeException $e) {
                $this->logException($e);
            }
        }
    }


    /**
     * Report in an appropriate way, based on the data returned by the worker
     *
     * @param array $data
     * @return mixed
     */
    abstract protected function report($data);


    /**
     * (Re)compute the triggers for this particular scheduler
     *
     * @return TriggerInterface[]
     */
    abstract protected function computeTriggers();


    /**
     * @return void
     */
    abstract protected function dropCache();
}

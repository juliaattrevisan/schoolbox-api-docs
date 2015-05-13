<?php
/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2014, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace Alaress\Scheduler;


use Alaress\Scheduler\Interactive\Interactor;
use Alaress\Scheduler\Trigger\TriggerInterface;
use Alaress\WorkerCrew\Pooling\DefaultDispatchStrategy;
use Alaress\WorkerCrew\Pooling\DispatchStrategyInterface;
use Alaress_Date_Format;
use Exception;
use Psr\Log\LoggerInterface;
use React\EventLoop\LoopInterface;
use React\EventLoop\Timer\TimerInterface;
use React\Stomp\AckResolver;
use React\Stomp\Client;
use React\Stomp\Exception\ConnectionException;
use React\Stomp\Protocol\Frame;

abstract class StompScheduler extends AbstractScheduler {

	/** @var string */
    protected $scheduleQueue;

	/** @var array */
	protected $subscriptions;

	/** @var Client */
	protected $stomp;


    /**
     * @param LoopInterface $loop
     * @param DispatchStrategyInterface $dispatchStrategy
     * @param string $scheduleQueue
     * @param LoggerInterface $log
     */
    public function __construct($loop, $dispatchStrategy, $scheduleQueue, $log) {
        parent::__construct($loop, $dispatchStrategy, $log);
        $this->method = DefaultDispatchStrategy::TASK_PUBLISH;
        $this->scheduleQueue = $scheduleQueue;

        try {
            // defer to implementing classes
            $this->stomp = $this->getStompConnection();
            $this->stomp->connect()->then(array($this, 'onConnect'));
        }
        catch(ConnectionException $e) {
            // if the connection bugged out...
            $this->log->alert('Rabbitmq is offline or STOMP misconfigured: '. $e->getMessage(), array($e));
            $this->onException($e);
        }
    }


    /**
     * Subscribes, with ACKnowledgement, to the queue provided in construction
     *
     * @return void
     */
    public function onConnect() {
        $this->log->info("Connected to STOMP", array('char' => '+'));
	    $this->subscribeAck($this->scheduleQueue, array($this, 'onMessage'));
	    $this->stomp->on('error', array($this, 'onException'));
    }


	/**
	 * @param string $queue
	 * @param callable $callback
	 * @return bool
	 */
	protected function subscribe($queue, $callback) {
		if(isset($this->subscriptions[$queue])) {
			return false;
		}
		$this->subscriptions[$queue] = $this->stomp->subscribe($queue, $callback);
		return true;
	}


	/**
	 * @param string $queue
	 * @param callable $callback
	 * @param string $mode
	 * @return bool
	 */
	protected function subscribeAck($queue, $callback, $mode = 'client-individual') {
		if(isset($this->subscriptions[$queue])) {
			return false;
		}
		$this->subscriptions[$queue] = $this->stomp->subscribeWithAck($queue, $mode, $callback);
		return true;
	}


	/**
	 * @param string $queue
	 * @return bool
	 */
	protected function unsubscribe($queue) {
		if(isset($this->subscriptions[$queue])) {
			$this->stomp->unsubscribe($this->subscriptions[$queue]);
			unset($this->subscriptions[$queue]);
			return true;
		}
		return false;
	}


	/**
	 * Accepts a command (from STDIN) to log data about the scheduler's current state
	 *
	 * @param string $data
	 */
	public function _stdincmd($data) {
		$interactor = new Interactor($this);
		$interactor->handle($data);
	}


	/**
	 * Return the state-data requested
	 *
	 * @param array $fields
	 * @return array
	 */
	public function getStatus($fields = array()) {
		$status = array();
		if(in_array('pool', $fields)) {
			$status['pool'] = $this->getDispatcher()->getPoolStatus();
		}

        if (in_array('schedule', $fields)) {
            $schedule = [];

            $now = Alaress_Date_Format::now();

            foreach ($this->timers as $class => $labels) {
                foreach ($labels as $label => $hashes) {
                    foreach ($hashes as $hash => $timer) {
                        /** @var TimerInterface $timer */

                        /** @var TriggerInterface $trigger */
                        $trigger = $timer->getData();

                        $object = $trigger->getEvent()->getObject();

                        if (method_exists($object, 'getId')) {
                            $objectString = $hash = "$class ({$object->getId()})";
                        } else {
                            $objectString = $class;
                        }

                        $hash = substr(sha1($hash), 0, 8);
                        $time = Alaress_Date_Format::now()->setTimestamp($trigger->getTimestamp());
                        $when = $time->getDatabase();
                        $diff = $time->diffInMinutes($now);

                        if ($diff < 60 && $diff >= 0) {
                            $when .= " ({$time->getRelativeTime()})";
                        }

                        $schedule[$hash]['__obj'] = $objectString;
                        $schedule[$hash][$label]  = $when;
                    }
                }
            }


            $status['schedule'] = $schedule;
        }

		return $status;
	}


	/**
     * @return Client
     */
    protected abstract function getStompConnection();

    /**
     * @param Frame $frame
     * @param AckResolver $resolver
     * @return void
     */
    public abstract function onMessage($frame, $resolver);

    /**
     * @param Exception $e
     * @return void
     */
    public abstract function onException($e);

} 
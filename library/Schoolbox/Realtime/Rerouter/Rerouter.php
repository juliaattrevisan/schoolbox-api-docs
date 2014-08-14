<?php
/**
 * 
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2014, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace Schoolbox\Realtime\Rerouter;


use Alaress\Async\AMQP\AMQPInterface;
use Alaress\Async\SignalEmitter;
use Alaress\Async\Stomp\Subscriber;
use Alaress\WorkerCrew\Dispatcher;
use Alaress\WorkerCrew\DispatcherInterface;
use Alaress\WorkerCrew\Pooling\DispatchStrategyInterface;
use Alaress\WorkerCrew\TaskResponseHandlerInterface;
use Alaress\WorkerCrew\Transaction\Transaction;
use Exception;
use Psr\Log\LoggerInterface;
use React\EventLoop\LoopInterface;
use React\EventLoop\Timer\TimerInterface;
use React\Stomp\Client;
use React\Stomp\Protocol\Frame;
use Schoolbox\Mapper;
use Schoolbox\Notif\MQ\Connector;
use Schoolbox\Notif\Notif;
use Schoolbox\Notif\Queue\UserQueueFactory;
use Schoolbox\Realtime\Message\RoutePermissionMapper;
use Schoolbox\User;
use Schoolbox\UserMapper;

class Rerouter extends SignalEmitter implements TaskResponseHandlerInterface {

	const TRANSACTION_CACHE_TIMEOUT = 0.5;
	const TRANSACTION_COUNT_LIMIT   = 80;

	/** @var LoopInterface */
	protected $loop;

	/** @var RoutePermissionMapper */
	protected $permissionMapper;

	/** @var UserMapper */
	protected $userMapper;

	/** @var Notif */
	protected $notif;

	/** @var LoggerInterface */
	public $log;

	/** @var DispatcherInterface */
	protected $dispatcher;

	/** @var UserQueueFactory */
	protected $uqf;

	/** @var Connector */
	protected $conn;

	/** @var Subscriber */
	protected $subscriber;

	/** @var bool */
	protected $running;


	/**
	 * @param LoopInterface             $loop
	 * @param DispatchStrategyInterface $dispatchStrategy
	 * @param LoggerInterface           $log
	 * @param Subscriber                $subscriber
	 * @param LoggerInterface           $notif
	 * @param AMQPInterface             $conn
	 * @param UserQueueFactory          $uqFactory
	 */
	public function __construct($loop, $dispatchStrategy, $log, $subscriber, $notif = null, $conn = null, $uqFactory = null) {
		parent::__construct($loop);

		$this->loop       = $loop;
		$this->running    = false;
		$this->subscriber = $subscriber;
		$this->log        = $log;

		// cast optional params
		$this->notif      = $notif ?: new Notif();
		$this->conn       = $conn ?: new Connector();
		$this->uqf        = $uqFactory ?: new UserQueueFactory($this->conn);

		// register signal listeners
		$this->on(SIGTSTP, array($this, 'onPause'));
		$this->on(SIGCONT, array($this, 'onResume'));
		$this->on(SIGHUP,  array($this, 'onHangup'));
		$this->on(SIGPOLL, array($this, 'onPoll'));
		$this->on(SIGINT,  array($this, 'onInterrupt'));
		$this->on(SIGTERM, array($this, 'onTerminate'));

		// TODO transpose these NEWs and getInstances into a factory
		$this->permissionMapper = RoutePermissionMapper::getInstance();
		$this->userMapper       = UserMapper::getInstance();
		$this->dispatcher =  $d = new Dispatcher($loop, $this, $log, $dispatchStrategy);

		$this->subscriber->connect(array($this, 'onConnect'), array($this, 'onException'));

		$this->transaction = new Transaction($loop, function($timer) use ($d) {
			/** @var $timer TimerInterface */
			$d->enqueue($timer, RerouterDispatchStrategy::TASK_REROUTE, $timer->getData());
		}, self::TRANSACTION_CACHE_TIMEOUT, self::TRANSACTION_COUNT_LIMIT);
	}


	/**
	 * @return DispatcherInterface
	 */
	public function getDispatcher() {
		return $this->dispatcher;
	}


	/**
	 * Callback executed when the STOMP client connects
	 * @override this daemon has no "main queue", so don't use parent onConnect()
	 */
	public function onConnect() {
		$this->log->info("Connected to STOMP", array('char' => '+'));
		// pick up user subscriptions
		$this->resume();
	}


	/**
	 * @param Exception $e
	 * @return void
	 */
	public function onException($e) {
		$this->log->critical(sprintf('%s in %s:%s: "%s"', get_class($e), $e->getFile(), $e->getLine(), $e->getMessage()), array($e));
		// TODO improve the email handling of these messages to be less spammy
		// $this->notif->setTopic(Notif::TOPIC_NOTIFICATION)->alert("Cannot bind to messaging system: ".$e->getMessage());
	}


	/**
	 * Callback executed when SIGTSTP received by the process
	 */
	public function onPause() {
		$this->log->info("SIGTSTP", array('char' => '>'));
		$this->pause();
	}


	/**
	 * @return void
	 */
	protected function pause() {
		$this->log->notice($this->isRunning() ? 'Halting' : 'Cannot halt, already paused');
		// if active
		if($this->isRunning()) {
			// drop all subscriptions
			$count = $this->subscriber->unsubscribeAll();

			$this->log->notice("Dropped $count subscriptions", array('char' => '-'));

			// set state to pause
			$this->running = false;
		}
	}


	/**
	 * Callback executed when SIGCONT received by the process
	 */
	public function onResume() {
		$this->log->info('SIGCONT', array('char' => '>'));
		$this->resume();
	}


	/**
	 * @return void
	 */
	protected function resume() {
		$this->log->notice($this->isRunning() ? 'Cannot resume, already running' : 'Resuming');
		// if paused
		if(!$this->isRunning()) {
			// pick up all subscriptions
			$tx = $this->transaction;

			$this->subscriber->getSubscriptionList()->importFromSource($this->userMapper);
			$count = $this->subscriber->subscribeAll(function($frame, $data) use ($tx) {
				/**
				 * This closure is executed every time a message is sent to the client based on the given subscription
				 * Adds the frame to a loop cache, and dispatches appropriately
				 *
				 * @var Frame $frame
				 * @var User $data
				 */
				$tx->put(sha1($frame->dump()), array($data, $frame));
			});

			$this->log->notice("Added $count subscriptions", array('char' => '+'));

			// set state to active
			$this->running = true;
		}
	}


	/**
	 * @return void
	 */
	public function onPoll() {
		$this->log->info("SIGPOLL", array('char' => '>'));
		$this->poll();
	}

	/**
	 * @return void
	 */
	protected function poll() {
		$this->log->notice(print_r($this->subscriber->getSubscriptionList()->getAllQueueNames()));
	}


	/**
	 * Callback executed when SIGHUP is received by the process
	 */
	public function onHangup() {
		$this->log->info("SIGHUP", array('char' => '>'));
		$this->rebind();
	}


	/**
	 * @return void
	 */
	protected function rebind() {
		$this->log->notice("Releasing workers");
		$this->getDispatcher()->releaseWorkerPool(false);
		$this->log->debug("Rebind should be performed");
	}


	/**
	 * Callback executed when SIGINT is received by the process
	 */
	public function onInterrupt() {
		$this->log->info("SIGINT: Interrupting starfish!", array('char' => '>'));
		// for now, just treat as a termination request
		$this->stop();
	}


	/**
	 * Callback executed when SIGTERM is received by the process
	 */
	public function onTerminate() {
		$this->log->info("SIGTERM", array('char' => '>'));
	}


	/**
	 * @return void
	 */
	protected function stop() {
		$this->pause();
		// die safely by removing all possible references to the dispatcher, so it destructs
		$this->dispatcher = null;
		$this->transaction = null;
		$this->loop->stop();

	}


	/**
	 * @return bool
	 */
	public function isRunning() {
		return $this->running;
	}


	/**
	 * Handles a response from a Worker
	 *
	 * @param object $identifier - the identifier passed as the first argument to Dispatcher::enqueue()
	 * @param string $command    - the command that was executed by the Worker
	 * @param array  $data       - the data with which the Worker responded
	 * @return void              - this method does not return any value
	 */
	public function handleResponse($identifier, $command, $data) {
		// nothing to do, the worker did the work
		$this->log->notice("Reroute count: $data[0]");
	}

}

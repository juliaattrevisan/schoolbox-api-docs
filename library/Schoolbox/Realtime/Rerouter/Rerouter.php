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
use Alaress\Async\Notification\LoggerInterface;
use Alaress\Async\Stomp\Subscriber;
use Alaress\Cli\Debug;
use Alaress\WorkerCrew\Dispatcher;
use Alaress\WorkerCrew\DispatcherInterface;
use Alaress\WorkerCrew\Pooling\DispatchStrategyInterface;
use Alaress\WorkerCrew\TaskResponseHandlerInterface;
use Alaress\WorkerCrew\Transaction\Transaction;
use Evenement\EventEmitter;
use Exception;
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

class Rerouter extends EventEmitter implements TaskResponseHandlerInterface {

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

	/** @var Debug */
	public $debug;

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
	 * @param Debug                     $debug
	 * @param Subscriber                $subscriber
	 * @param LoggerInterface           $notif
	 * @param AMQPInterface             $conn
	 * @param UserQueueFactory          $uqFactory
	 */
	public function __construct($loop, $dispatchStrategy, $debug, $subscriber, $notif = null, $conn = null, $uqFactory = null) {
		$this->loop       = $loop;
		$this->running    = false;
		$this->subscriber = $subscriber;
		$this->debug      = $debug;

		// cast optional params
		$this->notif      = $notif ?: new Notif();
		$this->conn       = $conn ?: new Connector();
		$this->uqf        = $uqFactory ?: new UserQueueFactory($this->conn);

		$this->loop->addPeriodicTimer(0.1, array($this, 'signalDispatch'));

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
		$this->dispatcher =  $d = new Dispatcher($loop, $this, $debug, $dispatchStrategy);

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
	 * Receive signals
	 *
	 * @param int $event
	 * @param callable $listener
	 */
	public function on($event, $listener) {
		pcntl_signal($event, array($this, 'emit'));
		parent::on($event, $listener);
	}


	/**
	 * Check if a signal has been raised
	 */
	public function signalDispatch() {
		pcntl_signal_dispatch();
	}


	/**
	 * Callback executed when the STOMP client connects
	 * @override this daemon has no "main queue", so don't use parent onConnect()
	 */
	public function onConnect() {
		$this->debug->v("Connected to STOMP", '+');
		// pick up user subscriptions
		$this->resume();
	}


	/**
	 * @param Exception $e
	 * @return void
	 */
	public function onException($e) {
		$this->debug->v($e->getMessage(), "!");
		$this->notif->setTopic(Notif::TOPIC_NOTIFICATION)->alert("Cannot bind to messaging system: ".$e->getMessage());
	}


	/**
	 * Callback executed when SIGTSTP received by the process
	 */
	public function onPause() {
		$this->debug->v("SIGTSTP", '>');
		$this->pause();
	}


	/**
	 * @return void
	 */
	protected function pause() {
		$this->debug->v($this->isRunning() ? 'Halting' : 'Cannot halt, already paused');
		// if active
		if($this->isRunning()) {
			// drop all subscriptions
			$count = $this->subscriber->unsubscribeAll();

			$this->debug->v("Dropped $count subscriptions", '-');

			// set state to pause
			$this->running = false;
		}
	}


	/**
	 * Callback executed when SIGCONT received by the process
	 */
	public function onResume() {
		$this->debug->v('SIGCONT', ">");
		$this->resume();
	}


	/**
	 * @return void
	 */
	protected function resume() {
		$this->debug->v($this->isRunning() ? 'Cannot resume, already running' : 'Resuming');
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

			$this->debug->v("Added $count subscriptions", '+');

			// set state to active
			$this->running = true;
		}
	}


	/**
	 * @return void
	 */
	public function onPoll() {
		$this->debug->v("SIGPOLL", '>');
		$this->poll();
	}

	/**
	 * @return void
	 */
	protected function poll() {
		$this->debug->d($this->subscriber->getSubscriptionList()->getAllQueueNames());
	}


	/**
	 * Callback executed when SIGHUP is received by the process
	 */
	public function onHangup() {
		$this->debug->v("SIGHUP", ">");
		$this->rebind();
	}


	/**
	 * @return void
	 */
	protected function rebind() {
		$this->debug->v("Performing rebind");
	}


	/**
	 * Callback executed when SIGINT is received by the process
	 */
	public function onInterrupt() {
		$this->debug->v("SIGINT: Interrupting starfish!", '>');
		// for now, just treat as a termination request
		$this->stop();
	}


	/**
	 * Callback executed when SIGTERM is received by the process
	 */
	public function onTerminate() {
		$this->debug->v("SIGTERM", '>');
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
		$this->debug->v("Reroute count: $data[0]");
	}

}

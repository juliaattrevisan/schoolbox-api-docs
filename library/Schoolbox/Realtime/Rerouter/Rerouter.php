<?php
/**
 * 
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2014, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace Schoolbox\Realtime\Rerouter;


use Alaress\Async\AMQP\Bunny\ControlInterface;
use Alaress\Async\SignalEmitter;
use Alaress\Async\Stomp\Subscriber;
use Alaress\Async\UserQueue\UserQueue;
use Alaress\WorkerCrew\Dispatcher;
use Alaress\WorkerCrew\DispatcherInterface;
use Alaress\WorkerCrew\Pooling\DispatchStrategyInterface;
use Alaress\WorkerCrew\TaskResponseHandlerInterface;
use Alaress\WorkerCrew\Transaction\Transaction;
use Exception;
use OutOfBoundsException;
use Psr\Log\LoggerInterface;
use React\EventLoop\LoopInterface;
use React\EventLoop\Timer\TimerInterface;
use React\Stomp\Client;
use React\Stomp\Protocol\Frame;
use Schoolbox\Notif\Formatter\CompressedFormatter;
use Schoolbox\Notif\Formatter\CompressionException;
use Schoolbox\Notif\Queue\Bindings\UserQueueBinder;
use Schoolbox\User;
use Schoolbox\UserMapper;

class Rerouter extends SignalEmitter implements TaskResponseHandlerInterface {

    const QUEUE_COMMAND             = '.sb.cmd.reroute';
	const TRANSACTION_CACHE_TIMEOUT = 0.5;
	const TRANSACTION_COUNT_LIMIT   = 80;

	/** @var LoopInterface */
	protected $loop;

	/** @var UserMapper */
	protected $userMapper;

	/** @var LoggerInterface */
	public $log;

	/** @var DispatcherInterface */
	protected $dispatcher;

    /** @var ControlInterface */
    protected $bunny;

    /** @var UserQueueBinder */
    protected $binder;

	/** @var Subscriber */
	protected $subscriber;

	/** @var bool */
	protected $running;

	/** @var Transaction */
    protected $transaction;

    /** @var callable */
    private $onFrame;


    /**
     * @param LoopInterface             $loop
     * @param DispatchStrategyInterface $dispatchStrategy
     * @param Subscriber                $subscriber
     * @param LoggerInterface           $log
     * @param Client                    $stomp
     * @param UserQueueBinder           $binder
     * @param ControlInterface          $rabbitControl
     */
	public function __construct(
        LoopInterface $loop,
        DispatchStrategyInterface $dispatchStrategy,
        Subscriber $subscriber,
        LoggerInterface $log,
        Client $stomp,
        UserQueueBinder $binder,
        ControlInterface $rabbitControl
    ) {
		parent::__construct($loop);

		$this->loop       = $loop;
		$this->running    = false;
		$this->subscriber = $subscriber;
		$this->log        = $log;
        $this->bunny      = $rabbitControl;
        $this->binder     = $binder;

        // TODO transpose these NEWs and getInstances into a factory
        $this->userMapper      = UserMapper::getInstance();
        $this->dispatcher = $d = new Dispatcher($loop, $this, $log, $dispatchStrategy);

        // connect to the command queue - make sure you've already registered this queue
        $stomp->subscribe(INSTANCE_ID.self::QUEUE_COMMAND, array($this, 'onCommand'));

        // register signal listeners
		$this->on(SIGTSTP, array($this, 'onPause'));
		$this->on(SIGCONT, array($this, 'onResume'));
		$this->on(SIGHUP,  array($this, 'onHangup'));
		$this->on(SIGPOLL, array($this, 'onPoll'));
		$this->on(SIGINT,  array($this, 'onInterrupt'));
		$this->on(SIGTERM, array($this, 'onTerminate'));

		$this->transaction = new Transaction($loop, function($timer) use ($d) {
			/** @var $timer TimerInterface */
			$d->enqueue($timer, RerouterDispatchStrategy::TASK_REROUTE, $timer->getData());
		}, self::TRANSACTION_CACHE_TIMEOUT, self::TRANSACTION_COUNT_LIMIT);

		// define the callback to be used for subscription handling
		$this->onFrame = function (Frame $amqpFrame, User $user) {
            // This closure is executed every time a message is sent to the client based on the given subscription
            // Adds the frame to a loop cache, and dispatches appropriately
            $this->transaction->put(sha1($amqpFrame->dump()), array($user, $amqpFrame));
        };
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

            $this->log->notice("Releasing workers");
            $this->getDispatcher()->releaseWorkerPool(false);

			// set state to pause
			$this->running = false;
		}
	}


    /**
     * @param int $userId
     * @return bool
     */
    public function unsubscribeUser($userId)
    {
        $this->log->debug("Unsubscribing user $userId");
        $subscriber = $this->subscriber;
        $subList    = $subscriber->getSubscriptionList();
        return $subscriber->unsubscribe($subList->createQueueName($userId));
    }


	/**
	 * Callback executed when SIGCONT received by the process
	 */
	public function onResume() {
		$this->log->info('SIGCONT', array('char' => '>'));
		$this->resume();
		$this->log->debug("Reload complete");
	}


	/**
	 * @return void
	 */
	protected function resume() {
		$this->log->notice($this->isRunning() ? 'Cannot resume, already running' : 'Resuming');
		// if paused

		if(!$this->isRunning()) {
			// pick up all subscriptions
            $this->log->debug("Importing user data");
			$this->subscriber->getSubscriptionList()->importFromSource($this->userMapper);
			$count = $this->subscriber->subscribeAll($this->onFrame);

			$this->log->notice("Added $count subscriptions", array('char' => '+'));

			// set state to active
			$this->running = true;
		}
	}


    /**
     * @param int  $userId
     * @param bool $refreshCache
     * @return bool
     */
    public function subscribeUser($userId, $refreshCache = true)
    {
        $this->log->debug("Subscribing user $userId");

        $subscriber = $this->subscriber;
        $subList    = $subscriber->getSubscriptionList();

        if ($refreshCache) {
            $subList->importFromSource($this->userMapper);
        }

        return $subscriber->subscribe($subList->createQueueName($userId), $this->onFrame);
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
     * @throws OutOfBoundsException
     * @return void
     */
    protected function rebind()
    {
        $this->log->notice("Releasing workers");
        $this->getDispatcher()
             ->releaseWorkerPool(false);

        $this->log->notice("Rebinding all users");

        // recache the user list into the subscription list
        $this->log->debug("Reloading user list from database");

        $subList = $this->subscriber->getSubscriptionList();

        $subList->importFromSource($this->userMapper);

        // drop the cache of existing bindings
        $this->binder->dropCache();

        $this->log->debug("Iterating users to bind");

        // iterate the set and
        foreach ($subList->getAllQueueNames() as $queue) {
            $this->rebindUser($subList->getQueueData($queue));
        }

        $this->log->debug("Full rebind complete; cleaning up stale queues");

        // get all current user IDs
        $userIds = $this->userMapper->findAllIds();

        $this->binder->destroyStaleBindings($userIds);

        $this->log->debug("Stale queue cleanup complete");
    }


    /**
     * @param User $user
     */
    public function rebindUser(User $user)
    {
        // pause the current subscription
        $this->unsubscribeUser($user->getId());

        list($removed, $added) = $this->binder->setUser($user)->performBind(array(UserQueue::Q_TOSORT, UserQueue::Q_UNREAD));

        $this->log->debug(sprintf("BIND %4s %4d  %s", "-{$removed}", "+{$added}", $user->getUsername()));

        $this->subscribeUser($user->getId(), false);
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
     * @param Frame $frame
     * @throws CompressionException
     */
    public function onCommand(Frame $frame)
    {
        $this->log->info("Received command");
        $commander = new RerouterCommander($this->userMapper);
        $body = CompressedFormatter::uncompress($frame->body);
        $commander->exec($this, $body['message']);
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


    /**
     * Handles a response from a Worker
     *
     * @param object $identifier - the identifier passed as the first argument to Dispatcher::enqueue()
     * @param string $command    - the command that was executed by the Worker
     * @param array  $data       - the data with which the Worker responded
     * @return void              - this method does not return any value
     */
    public function handleError($identifier, $command, $data)
    {
        $this->log->notice("Received worker error for '$command': $data");
    }

}

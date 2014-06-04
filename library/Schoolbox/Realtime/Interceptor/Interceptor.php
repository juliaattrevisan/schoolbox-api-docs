<?php
/**
 * 
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2014, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace Schoolbox\Realtime\Interceptor;


use Alaress\Async\Notification\Instance\Instance;
use Alaress\Cli\Debug;
use Alaress\WorkerCrew\Dispatcher;
use Alaress\WorkerCrew\TaskResponseHandlerInterface;
use Alaress\WorkerCrew\Transaction\Transaction;
use Exception;
use InvalidArgumentException;
use Monolog\Logger;
use PhpAmqpLib\Message\AMQPMessage;
use React\EventLoop\LoopInterface;
use React\EventLoop\Timer\Timer;
use React\EventLoop\Timer\TimerInterface;
use React\Stomp\Protocol\Frame;
use Schoolbox\Mapper;
use Schoolbox\Notif\Message\External\TargetedConverter;
use Schoolbox\Notif\MQ\Connector;
use Schoolbox\Notif\Notif;
use Schoolbox\Notif\Queue\UserQueueFactory;
use Schoolbox\Realtime\Message\RerouteMapper;
use Schoolbox\Realtime\Message\RoutePermissionMapper;
use Schoolbox\Role;
use Schoolbox\User;
use Schoolbox\UserMapper;

class Interceptor implements TaskResponseHandlerInterface {

	const TRANSACTION_CACHE_TIMEOUT = 0.5;
	const TRANSACTION_COUNT_LIMIT   = 80;

	/** @var UserMapper */
	protected $userMapper;

	/** @var RerouteMapper */
	protected $rerouteMapper;

	/** @var RoutePermissionMapper */
	protected $permissionMapper;

	/** @var Notif */
	protected $notif;

	/** @var Dispatcher */
	protected $dispatcher;

	/** @var Debug  */
	protected $v;

	/** @var UserQueueFactory */
	protected $uqf;

	/** @var Connector */
	protected $conn;


	/**
	 * @param Debug $debug
	 * @param LoopInterface $loop
	 */
	function __construct($debug, $loop = null) {
		$this->loop  = $loop;
		$this->v     = $debug;
		$this->notif = new Notif();
		$this->conn  = new Connector();
		$this->uqf   = new UserQueueFactory($this->conn);

		$this->rerouteMapper    = RerouteMapper::getInstance();
		$this->permissionMapper = RoutePermissionMapper::getInstance();
		$this->userMapper       = UserMapper::getInstance();

		// optional construction in case null was passed for $loop
		if($loop instanceof LoopInterface) {
			$d = $this->dispatcher = new Dispatcher($loop, $this, $debug, new InterceptorDispatchStrategy());
			$this->transaction     = new Transaction($loop, function($timer) use ($d) {
				/** @var $timer TimerInterface */
				$d->enqueue($timer, InterceptorDispatchStrategy::TASK_INTERCEPT, $timer->getData());
			}, self::TRANSACTION_CACHE_TIMEOUT);
		}
	}


	/**
	 * Handles a response from a Worker
	 *
	 * @param Timer $identifier  - the identifier passed as the first argument to Dispatcher::enqueue()
	 * @param string $command    - the command that was executed by the Worker
	 * @param array $data        - the data with which the Worker responded
	 * @return void              - this method does not return any value
	 */
	public function handleResponse($identifier, $command, $data) {
		// nothing to do, the worker did the work
		$this->v->v("Reroute count: $data[0]");
	}


	/**
	 * Adds the frame to a loop cache, and dispatches appropriately
	 *
	 * @param User $user
	 * @param Frame $frame
	 * @return bool
	 */
	public function process($user, $frame) {
		$this->transaction->put(sha1($frame->dump()), array($user, $frame));
	}


	/**
	 * @apc called from WorkerProcess::process()
	 * @param User $user
	 * @param Frame $frame
	 * @throws InvalidArgumentException
	 * @return bool
	 */
	public function reroute($user, $frame) {
		// echoes in this method are caught in an output buffer and piped to the log
		$formatted = json_decode($frame->body, true);

		try {
			$instance = Instance::jsonUnserialize($formatted['message']);
		}
		catch(InvalidArgumentException $e) {
			// TODO once TargetedMessage has been fully deprecated, this catch can be removed
			$conv = new TargetedConverter();
			$instance = $conv->convert($formatted['message']);
		}

		// fail fast: if it's already been rerouted, don't try again
		if(isset($formatted['extra']['rerouted'])) {
			$this->v->v("Message has already been rerouted: rejecting");
			return false;
		}

		// yes we are "rerouting" this message...rerouting it to /dev/null! *mwa ha ha*
		if($this->canDismiss($user, $instance, $formatted['context'])) {
			$this->v->v("Message not relevant to user: routing to /dev/null");
			return true;
		}

		$newLevel = $this->getReroutedLevel($user, $formatted['channel']);

		// if the user in question has no digest schedule and digest level is selected, escalate to instant
		if((string)$user->getRole()->getDigestSchedule() == (string)Role::DIGEST_NEVER && $newLevel == Logger::INFO) {
			$this->v->v("User doesn't have digest schedule: upgrading to instant");
			$newLevel = Logger::NOTICE;
		}

		switch($newLevel) {
			// total suppression
			case 0:
				$this->v->v("Reroute level is zero: routing to /dev/null");
				return true;

			// digest
			case Logger::INFO:
				$method = 'digest';
				break;

			// instant
			case Logger::NOTICE:
				$method = 'instant';
				break;

			// instant + email
			case Logger::WARNING:
			case Logger::ERROR:
				$method = 'instant';
				break;

			default:
				$this->v->v("Reroute level is unacceptable ($newLevel): rerouting to /dev/null");
				return true;
		}

		$newTopic = "$method.user.{$user->getId()}";

		try {
			$this->v->v("Rerouting message to '$newTopic' at level $newLevel!");
			$this->notif->logTo($newTopic, $newLevel, $formatted['message'], $formatted['context']);
			return true;
		}
		catch(Exception $e) {
			$this->v->v("Exception occurred during rerouting: $e");
			return false;
		}

	}


	/**
	 * @param User $user
	 * @param string $channel
	 * @return int
	 */
	protected function getReroutedLevel($user, $channel) {
		$levels = $this->permissionMapper->findRouteLevels($channel, $user->getRole()->getId(), $user->getId());

		if(!count($levels)) {
			// if we got nothing back, this user isn't meant to see this message
			return 0;
		}

		// ensure bounds checking
		$intent = is_null($levels['out']) ? $levels['def'] : $levels['out'];
		$intent = max($intent, $levels['min']);
		$intent = min($intent, $levels['max']);

		return $intent;
	}


	/**
	 * If this message was generated by myself, or I don't match the filter, I don't want to see it
	 *
	 * @param User $user
	 * @param Instance $message
	 * @param array $context
	 * @return bool
	 */
	protected function canDismiss($user, $message, $context) {

		// if the originating user matches current, dismiss
		if(isset($context['user']) && $context['user'] == $user->getId()) {
			return true;
		}

		// if campus set, must be involved in one or more campuses listed
		if(isset($context['campus'])) {
			if(!$this->testContextValue($context['campus'], $user->getCampusCollection()->getColumnValues('id'))) {
				return true;
			}
		}

		// if role set, must be in role listed
		if(isset($context['role'])) {
			if(!$this->testContextValue($context['role'], $user->getRole()->getId())) {
				return true;
			}
		}

		// if roletype set, must be of role specified
		if(isset($context['roletype'])) {
			if(!$this->testContextValue($context['roletype'], $user->getRole()->getType())) {
				return true;
			}
		}

		// if we've got this far, check for duplicates
		return $this->uqf->userHasSeen($user->getId(), $message);
	}


	/**
	 * Tests the overlap of a context value with the corresponding data from the user object
	 *
	 * @param array|string $contextValue
	 * @param array|string $comparisonValue
	 * @return bool
	 */
	protected function testContextValue($contextValue, $comparisonValue) {
		if(is_array($contextValue)) {
			if(is_array($comparisonValue)) {
				return !!count(array_intersect($contextValue, $comparisonValue));
			}
			return in_array($comparisonValue, $contextValue);
		}

		if(is_array($comparisonValue)) {
			return in_array($contextValue, $comparisonValue);
		}
		return $contextValue == $comparisonValue;
	}

}

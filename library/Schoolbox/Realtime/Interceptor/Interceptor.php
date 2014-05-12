<?php
/**
 * 
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2014, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace Schoolbox\Realtime\Interceptor;


use Alaress\Cli\Debug;
use Alaress\WorkerCrew\Dispatcher;
use Alaress\WorkerCrew\TaskResponseHandlerInterface;
use Alaress\WorkerCrew\Transaction\Transaction;
use Monolog\Logger;
use PhpAmqpLib\Message\AMQPMessage;
use React\EventLoop\LoopInterface;
use React\EventLoop\Timer\Timer;
use React\EventLoop\Timer\TimerInterface;
use React\Stomp\Protocol\Frame;
use Schoolbox\Mapper;
use Schoolbox\Notif\Message\External\TargetedMessage;
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

	/** @var bool */
	protected $patient = true;

	/**
	 * @param Debug $debug
	 * @param LoopInterface $loop
	 */
	function __construct($debug, $loop = null) {
		$this->loop = $loop;
		$this->v = $debug;

		$this->rerouteMapper = RerouteMapper::getInstance();
		$this->permissionMapper = RoutePermissionMapper::getInstance();
		$this->userMapper = UserMapper::getInstance();
		$this->notif = new Notif();

		// optional construction in case null was passed for $loop
		if($loop instanceof LoopInterface) {
			$d = $this->dispatcher = new Dispatcher($loop, $this, $debug, new InterceptorDispatchStrategy());
			$this->transaction = new Transaction($loop, function($timer) use ($d) {
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
		// after receiving the first batch, disable patience and enforce cache limits
		if($this->isPatient()) {
			$this->v->v("Moving out of patient mode");
			$this->setPatience(false);
			$this->transaction->setSize(self::TRANSACTION_COUNT_LIMIT);
		}
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
	 * @return bool
	 */
	public function reroute($user, $frame) {

		$formatted = json_decode($frame->body, true);
		$message = unserialize($formatted['message']);

		if(!$message instanceof TargetedMessage) {
//			echo "INCORRECT FORMAT: DISMISS!\n";
			return false;
		}

		// fail fast: if it's already been rerouted, don't try again
		if(isset($formatted['extra']['rerouted'])) {
//			echo "ALREADY REROUTED, SHOULDN'T HAVE ENDED UP BACK HERE!\n";
			return false;
		}

		// yes we are "rerouting" this message...rerouting it to /dev/null! *mwa ha ha*
		if($this->canDismiss($user, $message, $formatted['context'])) {
//			echo "SELF-SEND: DISMISS!\n";
			return true;
		}

		$newLevel = $this->getReroutedLevel($user, $formatted['channel']);

		// if the user in question has no digest schedule and digest level is selected, escalate to instant
		if((string)$user->getRole()->getDigestSchedule() == Role::DIGEST_NEVER && $newLevel == Logger::INFO) {
			$newLevel = Logger::NOTICE;
		}

		switch($newLevel) {
			// total suppression
			case 0:
//				echo "NEW LEVEL IS ZERO: DISMISS!\n";
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
//				echo "UNACCEPTABLE LEVEL: DISMISS!\n";
				return true;
		}

		try {
			/** @var TargetedMessage $message */
			$message->setTargets(array($method));
//			echo "I REROUTED YOUR MESSAGE to '$method.user.{$user->getId()}' at level $newLevel!\n";
			return $message->resend($this->notif, $newLevel, "user.{$user->getId()}");
		}
		catch(\Exception $e) {
//			echo $e;
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
	 * @param TargetedMessage $message
	 * @param array$context
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

		$uqf = new UserQueueFactory(new Connector());

		// only perform extra dedupe checks if we're in patient (aka boot) mode
		return $this->isPatient() ? $uqf->userHasSeen($user->getId(), $message) && false : false;
	}


	/**
     * @deprecated
	 * @param AMQPMessage $m
	 * @return array
	 */
	public function unwrapMessageBody($m) {
		return json_decode($m->body, true);
	}


	/**
	 * Tests the overlap of a context value with the corresponding data from the user object
	 *
	 * @param array|string $contextValue
	 * @param array|string $comparisonValue
	 * @return bool
	 */
	protected function testContextValue($contextValue, $comparisonValue) {

//		echo "Comparing ", json_encode($contextValue), " to ", json_encode($comparisonValue), "\n";

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



	/**
	 * Are we being patient and doing extra dedupe checks?
	 *
	 * @return bool
	 */
	public function isPatient() {
		return $this->patient;
	}

	/**
	 * Set patience.
	 *
	 * @param bool
	 * @return self
	 */
	protected function setPatience($patient) {
		$this->patient = $patient;
		return $this;
	}

}

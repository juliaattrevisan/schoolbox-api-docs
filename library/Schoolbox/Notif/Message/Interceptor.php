<?php
/**
 * 
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2014, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace Schoolbox\Notif\Message;


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
use Schoolbox\User;
use Schoolbox\UserMapper;

class Interceptor implements TaskResponseHandlerInterface {

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
			$d = $this->dispatcher = new Dispatcher($loop, $this, $verboser, new InterceptorDispatchStrategy());
			$this->transaction = new Transaction($loop, function($timer) use ($d) {
				/** @var $timer TimerInterface */
				$d->enqueue($timer, InterceptorDispatchStrategy::TASK_INTERCEPT, $timer->getData());
			}, 0.5);
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
		// after receiving the first batch, disable patience
		if($this->isPatient()) {
			$this->v->v("Moving out of patient mode");
			$this->setPatience(false);
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
	 * @param User $user
	 * @param Frame $frame
	 * @return bool
	 */
	public function reroute($user, $frame) {

//		echo "Received frame $frame->body\n";

		$body = json_decode($frame->body, true);

		/** @var External $external */
		$external = unserialize($body['message']);

		if(!$external instanceof External) {
//			echo "INCORRECT FORMAT: DISMISS!\n";
			return false;
		}

		// fail fast: if it's already been rerouted, don't try again
		if(isset($body['extra']['rerouted'])) {
//			echo "ALREADY REROUTED, SHOULDN'T HAVE ENDED UP BACK HERE!\n";
			return false;
		}

		// yes we are "rerouting" this message...rerouting it to /dev/null! *mwa ha ha*
		if($this->canDismiss($user, $body)) {
//			echo "SELF-SEND: DISMISS!\n";
			return true;
		}

		$newLevel = $this->getReroutedLevel($user, $body['channel'], $body['level']);

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
			// change case in event of targeted message (which is possibly always...)
			if($external instanceof TargetedMessage) {
				/** @var TargetedMessage $external */
				$external->setTopicPrepend('');
				$external->setTargets(array($method));
			}
			else {
				$external->setTopicPrepend("$method.");
			}
//			echo "I REROUTED YOUR MESSAGE to '$method.user.{$user->getId()}' at level $newLevel!\n";

			return $external->resend($this->notif, $newLevel, "user.{$user->getId()}");

		}
		catch(\Exception $e) {
//			echo $e;
			return false;
		}

	}


	/**
	 * @param User $user
	 * @param string $channel
	 * @param string $level
	 * @return int
	 */
	protected function getReroutedLevel($user, $channel, $level) {
		$levels = $this->permissionMapper->findRouteLevels($channel, $user->getRole()->getId(), $user->getId());

		if(!count($levels)) {
			// if in doubt, return the original level
			return $level;
		}

		$finishLevels = array();

		// in theory, $levels could have multiple entries
		foreach($levels as $l) {
			// ensure bounds checking
			$intent = is_null($l['outgoing_level']) ? $l['default_level'] : $l['outgoing_level'];
			$intent = max($intent, $l['min_level']);
			$intent = min($intent, $l['max_level']);

			$finishLevels[] = $intent;
		}

		// return the highest value found from the resultset
		return max($finishLevels);
	}


	/**
	 * If this message was generated by myself, I don't want to see it
	 *
	 * @param User $user
	 * @param AMQPMessage|array $body
	 * @return bool
	 */
	protected function canDismiss($user, $body) {
		// unwrap if AMQP
		if($body instanceof AMQPMessage) {
			$body = $this->unwrapMessageBody($body);
		}

		// read the context, determine if we should dismiss this message or not
		$context = $body['context'];

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
		return $this->isPatient() ? $uqf->userHasSeen($user->getId(), unserialize($body['message'])) : false;
	}


	/**
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

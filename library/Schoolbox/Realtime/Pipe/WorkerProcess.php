<?php
/**
 * 
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2014, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace Schoolbox\Realtime\Pipe;


use Alaress\Cli\Debug;
use Alaress\WorkerCrew\Pooling\DefaultDispatchStrategy;
use Alaress\WorkerCrew\Pooling\DispatchStrategyInterface;
use Alaress\WorkerCrew\Worker\AbstractProcess;
use InvalidArgumentException;
use RuntimeException;
use Schoolbox\Notif\Message\Decorator;
use Schoolbox\Notif\Message\Messenger;
use Schoolbox\Notif\Queue\UserQueueFactory;
use Schoolbox\Realtime\Interceptor\Interceptor;
use Schoolbox\User;

class WorkerProcess extends AbstractProcess {

	/** @var UserQueueFactory  */
	protected $mqf;

	/** @var Interceptor */
	protected $interceptor;


	/**
	 * @param DispatchStrategyInterface $strat
	 * @param Debug $debug
	 */
	public function __construct($strat = null, $debug = null) {
		parent::__construct($strat, $debug);
		$this->mqf = new UserQueueFactory();
		$this->interceptor = new Interceptor($debug);
	}


	/**
	 * @param string $command
	 * @param array $args
	 * @throws RuntimeException
	 * @throws InvalidArgumentException
	 * @return mixed
	 */
	protected function process($command, $args) {
		$this->debug->v("Received $command command");

		switch($command) {

			// retrieve messages
			case DefaultDispatchStrategy::TASK_FETCH:
				list($userId, $count) = $args;
				return $this->fetch($userId, $count);

			// publish or digest messages
			case DefaultDispatchStrategy::TASK_PUBLISH:
			case DefaultDispatchStrategy::TASK_DIGEST:
				$data = unserialize($args[0]);
				if(!(is_array($data) && count($data) == 4)) {
					throw new RuntimeException("Data should be array of size 4; is ".gettype($data)." (".count($data).")");
				}
				list($callback, $model, $user, $diff) = $data;
				$this->debug->v(sprintf("%sing model %s for user %s", ucfirst($command), get_class($model), $user->getId()));
				return $this->publish($callback, $model, $user, $diff);

			// process interceptions
			case DefaultDispatchStrategy::TASK_INTERCEPT:
				return $this->reroute($args);
		}

		throw new InvalidArgumentException("Can't process '$command'.");
	}


	/**
	 * Process fetch
	 *
	 * @param $userId
	 * @param $count
	 * @return array
	 */
	protected function fetch($userId, $count) {
		$this->debug->v("Fetching $count for user $userId");
		$decor = new Decorator(null);

		$queue = $this->mqf->buildUserQueue($userId, $count, false);
		$output = array();

		// make messages pretty
		foreach($queue->getMessages($count, false, true) as $msgId => $targetedMessage) {
			$decor->setMessage($targetedMessage);
			$output[$msgId] = $decor->formatArray(false);
			$output[$msgId]['id'] = $msgId;
		}

		// wrap in an array to preserve keys
		return array($output);
	}

	/**
	 * Process publish/digest
	 *
	 * @param callable $callback
	 * @param object $model
	 * @param User $user
	 * @param array $diff
	 * @return string
	 * @throws \RuntimeException
	 * @throws \InvalidArgumentException
	 */
	protected function publish($callback, $model, $user, $diff) {

		if(!is_callable($callback)) {
			throw new RuntimeException("Unable to execute callback, got ".json_encode($callback));
		}

		$this->debug->v("Executing ".$this->callable2string($callback));

		// this call SHOULD return a Messenger object
		ob_start();
		$return = call_user_func($callback, $model, $user, $diff);
		if($buffer = ob_get_contents()) {
			$this->debug->v($buffer);
		}
		ob_end_clean();

		$this->debug->v("Callback complete");

		if(is_null($return)) {
			$this->debug->v("Response is NULL, casting to empty Messenger");
			$return = new Messenger();
		}

		if(!$return instanceof Messenger) {
			throw new InvalidArgumentException("Async method response must be an instance of Messenger; got "
				. (is_object($return) ? get_class($return) : gettype($return))
			);
		}

		$this->debug->v("Messenger ". ($return->hasMessages() ? 'contains messages' : 'does not contain messages'));

		$serial = serialize($return);
		if($serial === false) {
			throw new RuntimeException("Failed to serialize Messenger");
		}

		$this->debug->d($serial);

		return $serial;
	}


	/**
	 * Process reroute/intercept/whatever you want to call it
	 *
	 * @param array $args
	 * @return int
	 */
	protected function reroute($args) {
		$count = 0;
		$this->debug->v("Iterating frames provided");
		foreach($args as $v) {
			list($user, $frame) = $v;
			ob_start();
			$count += $this->interceptor->reroute($user, $frame) ? 1 : 0;
			if($output = ob_get_contents()) {
				$this->debug->v("Iteration result (user {$user->getId()}): $output");
			}
			ob_end_clean();
		}
		$this->debug->v("Rerouted $count message".($count == 1 ? '' : 's'));
		return $count;
	}

	/**
	 * @param callable $callable
	 * @return string
	 */
	protected function callable2string($callable) {
		if(is_array($callable)) {
			return get_class($callable[0]) . '::' . $callable[1];
		}
		return (string)$callable;
	}
}
<?php
/**
 * 
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2014, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace Schoolbox\Realtime\Pipe;


use Alaress\Async\Notification\Instance\InstanceBundle;
use Alaress\Cli\Debug;
use Alaress\WorkerCrew\Pooling\DefaultDispatchStrategy;
use Alaress\WorkerCrew\Pooling\DispatchStrategyInterface;
use Alaress\WorkerCrew\Worker\AbstractProcess;
use InvalidArgumentException;
use React\EventLoop\LoopInterface;
use RuntimeException;
use Schoolbox\Notif\Queue\UserQueueFactory;
use Schoolbox\Realtime\Interceptor\Interceptor;
use Schoolbox\Realtime\Notification\Decorator;
use Schoolbox\Realtime\Notification\Envelope;
use Schoolbox\User;

class WorkerProcess extends AbstractProcess {

	/** @var UserQueueFactory  */
	protected $mqf;

	/** @var Interceptor */
	protected $interceptor;


	/**
	 * @param LoopInterface $loop
	 * @param DispatchStrategyInterface $strat
	 * @param Debug $debug
	 */
	public function __construct($loop, $strat = null, $debug = null) {
		parent::__construct($loop, $strat, $debug);
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
					throw new RuntimeException("Data should be array(4); is ".gettype($data)."(".count($data).")");
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
		$decorator = new Decorator();

		$queue = $this->mqf->buildUserQueue($userId, $count, false);

		// make messages pretty
		$decorator->appendNotifications($queue->getNotifications($count, true, true));
		// wrap in an array to preserve keys
		return array($decorator->renderAsSocketArray(false));
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

		// this call SHOULD return an instance of InstanceBundle (be that Messenger or Envelope)
		$return = call_user_func($callback, $model, $user, $diff);
		$this->debug->v("Callback complete");

		if(is_null($return)) {
			$this->debug->v("Response is NULL, casting to empty Envelope");
			$return = new Envelope();
		}

		if(!$return instanceof InstanceBundle) {
			throw new InvalidArgumentException(
				"Async method response must be an instance of Alaress\\Async\\Notification\\InstanceBundle; got "
				. (is_object($return) ? get_class($return) : gettype($return))
			);
		}

		$this->debug->v("Envelope ". ($return->isEmpty() ? 'does not contain messages' : 'contains messages'));

		$serial = serialize($return);
		if($serial === false) {
			throw new RuntimeException("Failed to serialize Envelope");
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
			$count += $this->interceptor->reroute($user, $frame) ? 1 : 0;
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
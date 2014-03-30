<?php
/**
 * 
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2014, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace Schoolbox\Realtime\Pipe;


use Alaress\WorkerCrew\Pooling\DefaultDispatchStrategy;
use Alaress\WorkerCrew\Pooling\DispatchStrategyInterface;
use Alaress\WorkerCrew\Worker\AbstractProcess;
use InvalidArgumentException;
use RuntimeException;
use Schoolbox\Notif\MQ\Connector;
use Schoolbox\Notif\Queue\UserQueueFactory;
use Schoolbox\Realtime\Interceptor\Interceptor;
use Schoolbox\Realtime\Verboser;

class WorkerProcess extends AbstractProcess {

	/** @var UserQueueFactory  */
	protected $mqf;

	/** @var Interceptor */
	protected $interceptor;


	/**
	 * @param DispatchStrategyInterface $strat
	 * @param Verboser $verboser
	 */
	public function __construct($strat = null, $verboser = null) {
		parent::__construct($strat, $verboser);
		$this->mqf = new UserQueueFactory(new Connector());
		$this->interceptor = new Interceptor($verboser);
	}


	/**
	 * @param string $command
	 * @param array $args
	 * @throws RuntimeException
	 * @throws InvalidArgumentException
	 * @return mixed
	 */
	protected function process($command, $args) {
		switch($command) {

			// retrieve messages
			case DefaultDispatchStrategy::TASK_FETCH:
				list($userId, $count) = $args;

				$queue = $this->mqf->buildUserQueue($userId, $count, false);

				// wrap in an array to preserve keys
                return array($queue->getMessages($count, false, true));

			// push or digest messages
			case DefaultDispatchStrategy::TASK_PUBLISH:
			case DefaultDispatchStrategy::TASK_DIGEST:
				$data = unserialize($args[0]);

				if(!(is_array($data) && count($data) == 4)) {
					throw new RuntimeException("Datacount should be 4, is ".count($data));
				}

				// making a certain assumption that the callable survives the serialization process
				list($callback, $model, $userId, $diff) = $data;

				if(!is_callable($callback)) {
					throw new RuntimeException("Unable to execute callback");
				}

				// this call SHOULD return a Messenger object
				return serialize(call_user_func($callback, $model, $userId, $diff));

			// process interceptions
			case DefaultDispatchStrategy::TASK_INTERCEPT:
				$count = 0;
				foreach($args as $v) {
					list($user, $frame) = $v;
					ob_start();
					$count += $this->interceptor->reroute($user, $frame) ? 1 : 0;
					if($output = ob_get_contents()) {
						$this->v->v($output);
					}
					ob_end_clean();
				}
				return $count;
		}

		throw new InvalidArgumentException("Can't process '$command'.");
	}

}
<?php
/**
 * 
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2014, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace Schoolbox\Realtime\Pipe;



use Alaress\WorkerCrew\Pooling\PoolingInterface;
use Alaress\WorkerCrew\Worker\AbstractProcess;
use InvalidArgumentException;
use RuntimeException;
use Schoolbox\Notif\MQ\Connector;
use Schoolbox\Notif\Queue\UserQueueFactory;
use Schoolbox\Realtime\Verboser;

class WorkerProcess extends AbstractProcess {


	/**
	 * @param PoolingInterface $strat
	 * @param Verboser $verboser
	 */
	public function __construct($strat = null, $verboser = null) {
		parent::__construct($strat, $verboser);
		$this->mqf = new UserQueueFactory(new Connector());
	}


	/**
	 * @param string $command
	 * @param string $args
	 * @throws RuntimeException
	 * @throws InvalidArgumentException
	 * @return mixed
	 */
	protected function process($command, $args) {
		switch($command) {

			// retrieve messages
			case 'fetch':
				$data = json_decode($args, true);

				list($userId, $count) = array_values($data);

				$messages = array();

				$queue = $this->mqf->buildUserQueue($userId, $count, false);
				$allMessages = $queue->getMessages($count);

				foreach($allMessages as $read) {
					$messages[$read->getId()] = $read->getBody();
				}
				return $messages;

			// push messages
			case 'publish':
				$data = unserialize($args);

				if(!(is_array($data) && count($data) == 4)) {
					break;
				}

				// making a certain assumption that the callable survives the serialization process
				list($callback, $model, $userId, $diff) = $data;

				if(!is_callable($callback)) {
					throw new RuntimeException("Unable to execute callback");
				}

				// this call SHOULD return a Messenger object
				return serialize(call_user_func($callback, $model, $userId, $diff));
		}

		throw new InvalidArgumentException("Can't process '$command'");
	}

}
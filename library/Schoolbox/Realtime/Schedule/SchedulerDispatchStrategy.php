<?php
/**
 * 
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2014, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace Schoolbox\Realtime\Schedule;


use Alaress\WorkerCrew\Pooling\DefaultDispatchStrategy;
use Schoolbox\Realtime\Schedule\Persistence\QueueItem;
use Schoolbox\Realtime\Schedule\Persistence\QueueItemMapper;
use SplQueue;

/**
 * The only difference between this strategy and the default is that it persists to the database.
 *
 * Class PoolingStrategy
 * @package Schoolbox\Realtime\Schedule
 */
class SchedulerDispatchStrategy extends DefaultDispatchStrategy {

	/** @var QueueItemMapper */
	protected $mapper;


	public function __construct() {
		parent::__construct();
		$this->mapper = QueueItemMapper::getInstance();
	}


	/**
	 * Get the name of the strategy currently being used
	 *
	 * @return string
	 */
	public function getName() {
		return 'Schedule';
	}


	/**
	 * @return SplQueue
	 */
	public function buildQueue() {
		$queue = new SplQueue();

		/** @var QueueItem[] $qiColl */
		$qiColl = $this->mapper->findAll();

		foreach($qiColl as $qi) {
			$identifier = $qi->getIdentifier();
			$command = $qi->getCommand();
			$data = $qi->getData();
			$queue->enqueue(array($identifier, $command, $data));
		}

		return $queue;
	}


	/**
	 * @param object $identifier
	 * @param string $command
	 * @param array $data
	 */
	public function penqueue($identifier, $command, $data) {
		$qi = new QueueItem();
		$qi->setIdentifier($identifier);
		$qi->setCommand($command);
		$qi->setData($data);
		$this->mapper->save($qi);
	}


	/**
	 * @param object $identifier
	 */
	public function pdequeue($identifier) {
		$q = new QueueItem();
		$q->setIdentifier($identifier);
		$this->mapper->delete($q);
	}

}

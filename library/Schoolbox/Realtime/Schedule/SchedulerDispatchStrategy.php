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
use RuntimeException;
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

}

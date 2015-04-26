<?php
/**
 * 
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2014, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace Schoolbox\Realtime\Worker;


use Alaress\WorkerCrew\Pooling\DispatchStrategyInterface;
use Alaress\WorkerCrew\Worker\AbstractProcess;
use Alaress\WorkerCrew\Worker\PipeWriter;
use InvalidArgumentException;
use Psr\Log\LoggerInterface;
use React\EventLoop\LoopInterface;
use RuntimeException;
use Schoolbox\Realtime\Rerouter\QueueSorter;
use Schoolbox\Realtime\Rerouter\RerouterDispatchStrategy;

class RerouteWorker extends AbstractProcess {

	/**
	 * @param QueueSorter $queueSorter
	 * @param LoopInterface $loop
     * @param LoggerInterface $log
     * @param PipeWriter $writer
	 * @param DispatchStrategyInterface $strat
	 */
	public function __construct($queueSorter, $loop, $log, $writer, $strat) {
		parent::__construct($loop, $log, $writer, $strat);
		$this->sorter = $queueSorter;
	}


	/**
	 * @param string $command
	 * @param array  $args
	 * @throws RuntimeException
	 * @throws InvalidArgumentException
	 * @return mixed
	 */
	protected function process($command, $args) {
		if($command != RerouterDispatchStrategy::TASK_REROUTE) {
			throw new InvalidArgumentException("Worker can only reroute");
		}
		$count = 0;
		$this->log->debug("Iterating frames provided");
		foreach($args as $v) {
			list($user, $frame) = $v;
			$count += $this->sorter->process($user, $frame) ? 1 : 0;
		}
		$this->log->info("Rerouted $count message".($count == 1 ? '' : 's'));
		return $count;

	}

} 
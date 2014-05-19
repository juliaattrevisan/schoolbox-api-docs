<?php
/**
 * 
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2014, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace Alaress\WorkerCrew\Worker;


use Alaress\Cli\Debug;
use Alaress\WorkerCrew\Pooling\DispatchStrategyInterface;

class Worker extends PipeWriter {

	const READY = 0x1;
	const BUSY  = 0x2;
	const DONE  = 0x4;
	const ENDED = 0x8;
	const ERROR = 0x10;


	/** @var string  */
	protected $cmd;
	/** @var resource  */
	protected $pp;
	/** @var array  */
	protected $pipes;
	/** @var int  */
	protected $status;
	/** @var float  */
	protected $start;
	/** @var Task */
	protected $task;
	/** @var Task[] */
	protected $previousTasks = array();

	/** @var int */
	protected $taskRetainCount = 0;

	/**
	 * @param string $cmd
	 * @param resource $pp
	 * @param array $pipes
	 * @param DispatchStrategyInterface $strat
	 */
	function __construct($cmd, $pp, $pipes, $strat) {
		parent::__construct($strat, null, $pipes[0]);
		$this->cmd = $cmd;
		$this->pp = $pp;
		$this->pipes = $pipes;
		$this->status = self::READY;
		$this->start = microtime(true);
		$this->strat = $strat;
	}

	/**
	 * Ensure we clean up after ourselves
	 */
	function __destruct() {
		// if the process has already terminated, these closures won't do anything (so suppress warnings)
		foreach($this->pipes as &$p) {
			@fclose($p);
		}
		@pclose($this->pp);
	}

	/**
	 * @param int $count
	 */
	public function setTaskRetention($count) {
		$this->taskRetainCount = $count;
	}

	/**
	 * @param Debug $debug
	 */
	public function attachDebug($debug) {
		$this->debug = $debug;
	}

	/**
	 * @return string
	 */
	public function getCommand() {
		return $this->cmd;
	}

	/**
	 * @return array
	 */
	public function getPipes() {
		return $this->pipes;
	}

	/**
	 * @return resource
	 */
	public function getProcessHandle() {
		return $this->pp;
	}

	/**
	 * @return float
	 */
	public function getStart() {
		return $this->start;
	}

	/**
	 * @param int $status
	 * @return self
	 */
	public function setStatus($status) {
		$this->status = $status;
		return $this;
	}

	/**
	 * @return int
	 */
	public function getStatus() {
		return $this->status;
	}

	/**
	 * @param object $identifier
	 * @param string $cmd
	 * @param string $args
	 * @return self
	 */
	public function setTask($identifier, $cmd, $args) {
		if($this->task) {
			array_unshift($this->previousTasks, $this->previousTasks);
			while(count($this->previousTasks) > $this->taskRetainCount) {
				array_pop($this->previousTasks);
			}
		}

		$this->write($this->strat->composeTaskString($cmd, $args));
		$this->setStatus(self::BUSY);
		$this->task = new Task($identifier, $cmd, $args);
		return $this;
	}

	/**
	 * @param $data
	 * @return self
	 */
	public function appendData($data) {
		$this->task->append($data);
		return $this;
	}

	/**
	 * @return self
	 */
	public function markTaskComplete() {
		$this->task->setFinish(microtime(true));
		$this->setStatus(self::READY);
		return $this;
	}

	/**
	 * @return Task
	 */
	public function getTask() {
		return $this->task;
	}



} 
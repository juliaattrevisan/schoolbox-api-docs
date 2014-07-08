<?php
/**
 * 
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2014, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace Alaress\WorkerCrew\Worker;


use Alaress\WorkerCrew\IPC\AlaressIPCProtocol;
use OutOfBoundsException;
use RuntimeException;

class WorkerArray implements WorkerBundleInterface {

	/** @var Worker[] */
	protected $workers = array();

	/** @var AlaressIPCProtocol */
	protected $protocol;

	/**
	 * @param AlaressIPCProtocol $protocol
	 */
	public function __construct($protocol) {
		$this->protocol = $protocol;
	}

	/**
	 * Will workers gracefully
	 */
	public function __destruct() {
		foreach($this->workers as $pid => $worker) {
			unset($this->workers[$pid]);
		}
		unset($this->workers);
	}


	/**
	 * @param int    $pid
	 * @param Worker $worker
	 * @throws RuntimeException
	 */
	public function attach($pid, $worker) {
		if(isset($this->workers[$pid])) {
			throw new RuntimeException("Cannot redeclare PID $pid");
		}
		$this->workers[$pid] = $worker;
	}

	/**
	 * @param int $pid
	 */
	public function detach($pid) {
//		echo "MORTON REPORTS DETACHMENT OF $pid\n";
		unset($this->workers[$pid]);
	}

	/**
	 * @param int $status
	 */
	public function detachWhere($status) {
		foreach($this->workers as $pid => $worker) {
			// use bitwise comparison so $status can be things like (Worker::ENDED | Worker::ERROR)
			if($this->bitwiseMatch($worker->getStatus(), $status)) {
				$this->detach($pid);
			}
		}
	}

	/**
	 * @param int $pid
	 * @return bool
	 */
	public function contains($pid) {
		return array_key_exists($pid, $this->workers);
	}

	/**
	 * @param int $pid
	 * @return Worker
	 */
	public function get($pid) {
		return $this->workers[$pid];
	}

	/**
	 * Returns a (single) Worker that matches the given status bitwise grouping
	 * Throws OutOfBounds exception if no matching workers are found
	 *
	 * @param int $status
	 * @throws OutOfBoundsException
	 * @return Worker
	 */
	public function getWorkerWhere($status) {
		return $this->getWhere($status, 'worker');
	}

	/**
	 * Returns the PID of a (single) Worker that matches the given status bitwise grouping
	 * Throws OutOfBounds exception if no matching workers are found
	 *
	 * @param int $status
	 * @throws OutOfBoundsException
	 * @return Worker
	 */
	public function getPidWhere($status) {
		return $this->getWhere($status, 'pid');
	}


	/**
	 * @param int $status
	 * @param string $type
	 * @throws OutOfBoundsException
	 * @return Worker
	 */
	protected function getWhere($status, $type) {
		foreach($this->workers as $pid => $worker) {
			if($this->bitwiseMatch($worker->getStatus(), $status)) {
				return $type == 'pid' ? $pid : $worker;
			}
		}
		throw new OutOfBoundsException("No matching worker found");
	}

	/**
	 * Returns the number of Workers this bundle contains
	 *
	 * @return int
	 */
	public function count() {
		return count($this->workers);
	}

	/**
	 * Returns the number of Workers that match the given bitwise status grouping
	 *
	 * @param int $status
	 * @return int
	 */
	public function countWhere($status) {
		$count = 0;
		foreach($this->workers as $worker) {
			if($this->bitwiseMatch($worker->getStatus(), $status)) {
				++$count;
			}
		}
		return $count;
	}

	/**
	 * Is there any common ground between the two given ints?
	 *
	 * @param int $a
	 * @param int $b
	 * @return bool
	 */
	protected function bitwiseMatch($a, $b) {
		return ($a & $b) > 0;
	}

	/**
	 * Return the status of the workers
	 *
	 * @return array
	 */
	public function getStatus() {
		$status = array();

		$list = array(
			Worker::READY => 'READY',
			Worker::BUSY  => 'BUSY',
			Worker::DONE  => 'DONE',
			Worker::ENDED => 'ENDED',
			Worker::ERROR => 'ERROR',
		);

		foreach($this->workers as $pid => $w) {
			$status[$pid] = array(
				'proc'    => $w->getCommand(),
				'status'  => $list[$w->getStatus()],
				'task'    => '(none)'
			);
			if($w->getTask() instanceof Task) {
				$status[$pid]['task'] = $w->getTask()->getCommand();
			}
		}

		return $status;
	}


	/**
	 * Unceremoniously terminate all workers
	 *
	 * @return void
	 */
	public function terminateAll() {
		foreach($this->workers as $pid => $w) {
			posix_kill((int)$pid, SIGKILL);
		}
	}

}
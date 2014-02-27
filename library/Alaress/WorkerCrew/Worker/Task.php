<?php
/**
 * 
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2014, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace Alaress\WorkerCrew\Worker;


use Schoolbox\Realtime\Verboser;

class Task {

	/** @var object */
	protected $id;
	/** @var string */
	protected $command;
	/** @var string */
	protected $args;
	/** @var string */
	protected $data;
	/** @var float */
	protected $start;
	/** @var float */
	protected $finish;

	/**
	 * @param object $identifier
	 * @param string $command
	 * @param string $args
	 */
	public function __construct($identifier, $command, $args) {
		$this->id = $identifier;
		$this->command = $command;
		$this->args = $args;
		$this->data  = '';
		$this->start = microtime(true);
	}

	public function append($data) {
		$this->data .= $data;
	}

	/**
	 * @return string
	 */
	public function getCommand() {
		return $this->command;
	}

	public function getArgs() {
		return $this->args;
	}

	/**
	 * @param bool $clean
	 * @return string
	 */
	public function getData($clean = true) {
		if($clean) {
			// remove all non-printable (control) characters from the data
			return preg_replace('/[\x00-\x1F\x80-\xFF]/', '', $this->data);
		}
		return $this->data;
	}

	/**
	 * @return float
	 */
	public function getFinish() {
		return $this->finish;
	}

	/**
	 * @return object
	 */
	public function getIdentifier() {
		return $this->id;
	}

	/**
	 * @return float
	 */
	public function getStart() {
		return $this->start;
	}

	/**
	 * @param float $end
	 * @return self
	 */
	public function setFinish($end) {
		$this->finish = $end;
		return $this;
	}

	/**
	 * Debug yourself into the given Verboser
	 *
	 * @param Verboser $v
	 */
	public function debug($v) {
		if(!$v instanceof Verboser) {
			return;
		}
		// clear the majority of fields, we don't need them
		$v->setVisibleFields(array('time'));

		$duration = ($this->finish - $this->start) * 1000;

		$v->v(sprintf("cmd=%s|args=%s|user=%s|time=%10.04f|data=%s",
			$this->command, json_encode(array_slice($this->args, 1)), $this->args[0], $duration, $this->data
		));

	}

}
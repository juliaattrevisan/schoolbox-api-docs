<?php
/**
 * Base
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2011, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace EOTC\ListGroup\Type;

use \EOTC\Event;
use \EOTC\EventStatusMapper;
use \EOTC\ListGroup\Mapping;

abstract class Base {

	/**
	 * An instance of \EOTC\Event
	 *
	 * @var null
	 */
	protected $event = null;

	/**
	 * An instance of \EOTC\ListGroup\Mapping
	 *
	 * @var null
	 */
	protected $mapping = null;

	/**
	 * eotc_status primary key
	 *
	 * This is the next status in the sequence
	 *
	 * @var int
	 */
	protected $nextStatusId = EventStatusMapper::STATUS_INPRINCIPLE;

	/**
	 * @param \EOTC\Event $event
	 */
	public function __construct(Event $event) {
		$this->setEvent($event);
		$this->setMapping(new Mapping());

		$this->setup();
	}

	/**
	 * Determine what the next status is, and where the
	 * event should currently be grouped
	 *
	 * @return mixed
	 */
	abstract public function determine();

	/**
	 * Hook method
	 */
	protected function setup() {
	}

	/* Getters/Setters
 	 */

	/**
	 * Set event
	 *
	 * @param null $event
	 */
	protected function setEvent($event) {
		$this->event = $event;
	}

	/**
	 * Get event
	 *
	 * @return null
	 */
	protected function getEvent() {
		return $this->event;
	}

	/**
	 * Set next
	 *
	 * @param  $next
	 */
	public function setNext($next) {
		$this->next = $next;
	}

	/**
	 * Get next
	 *
	 * @return bool
	 */
	public function getNext() {
		return $this->next;
	}

	/**
	 * Set mapping
	 *
	 * @param null $mapping
	 */
	protected function setMapping($mapping) {
		$this->mapping = $mapping;
	}

	/**
	 * Get mapping
	 *
	 * @return null
	 */
	protected function getMapping() {
		return $this->mapping;
	}

	/**
	 * Set next status id
	 *
	 * @param int $nextStatusId
	 */
	protected function setNextStatusId($nextStatusId) {
		$this->nextStatusId = $nextStatusId;
	}

	/**
	 * Get next status id
	 *
	 * @return int
	 */
	protected function getNextStatusId() {
		return $this->nextStatusId;
	}
}
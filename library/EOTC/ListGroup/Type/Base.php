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


abstract class Base {

	/**
	 * An instance of \EOTC\Event
	 *
	 * @var null
	 */
	protected $event = null;

	/**
	 * @param \EOTC\Event $event
	 */
	public function __construct(\EOTC\Event $event) {
		$this->setEvent($event);
	}

	/**
	 * Determine what the next status is, and where the
	 * event should currently be grouped
	 *
	 * @return mixed
	 */
	abstract public function determine();

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
} 
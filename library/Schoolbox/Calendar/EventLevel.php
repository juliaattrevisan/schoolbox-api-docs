<?php
/**
 * EventLevel
 *
 * Hardcoded values for Personal, Group and Campus
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2011, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace Schoolbox\Calendar;


class EventLevel {

	/**
	 * Id of the event level
	 *
	 * @var string
	 */
	private $id = '';

	/**
	 * Name of the event level
	 *
	 * @var string
	 */
	private $name = '';

	/**
	 * Constructor
	 *
	 * @param $id
	 * @param $name
	 */
	public function __construct($id, $name) {
		$this->setId($id);
		$this->setName($name);
	}

	/**
	 * Set id
	 *
	 * @param string $id
	 */
	public function setId($id) {
		$this->id = $id;
	}

	/**
	 * Get id
	 *
	 * @return string
	 */
	public function getId() {
		return $this->id;
	}

	/**
	 * Set name
	 *
	 * @param string $name
	 */
	public function setName($name) {
		$this->name = $name;
	}

	/**
	 * Get name
	 *
	 * @return string
	 */
	public function getName() {
		return $this->name;
	}
}
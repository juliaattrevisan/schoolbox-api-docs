<?php
/**
 * HostType
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2011, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

class HostType {

	/**
	 * Identifier
	 *
	 * @var int
	 */
	private $id = -1;

	/**
	 * Name of the host type
	 *
	 * @var string
	 */
	private $name = '';

	/**
	 * Constructor
	 */
	public function __construct() {
	}

	/* Getters/Setters
	 */

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

	/**
	 * Set id
	 *
	 * @param int $id
	 */
	public function setId($id) {
		$this->id = $id;
	}

	/**
	 * Get id
	 *
	 * @return int
	 */
	public function getId() {
		return $this->id;
	}
}
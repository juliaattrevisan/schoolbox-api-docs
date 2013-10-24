<?php
/**
 * Item
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2011, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace Schoolbox\Usage;


class Item {

	/**
	 * Name of disk usage
	 *
	 * @var string
	 */
	private $name = '';

	/**
	 * Occupies this many bytes
	 *
	 * @var int
	 */
	private $bytes = 0;

	/**
	 * Constructor
	 */
	public function __construct($name) {
		$this->setName($name);
	}

	/**
	 * Add bytes to the already bitten
	 *
	 * @param $bytes
	 */
	public function add($bytes) {
		$this->bytes += (int)$bytes;
	}

	/* Getters/Setters
 	 */

	/**
	 * Set bytes
	 *
	 * @param int $bytes
	 */
	public function setBytes($bytes) {
		$this->bytes = $bytes;
	}

	/**
	 * Get bytes
	 *
	 * @return int
	 */
	public function getBytes() {
		return $this->bytes;
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
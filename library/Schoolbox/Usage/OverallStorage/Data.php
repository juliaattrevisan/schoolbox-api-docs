<?php
/**
 * Data
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2011, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */
namespace Schoolbox\Usage\OverallStorage;

class Data {

	/**
	 * An instance of UsageMapper
	 *
	 * @var null
	 */
	private $mapper = null;

	/**
	 * Constructor
	 */
	public function __construct() {
		$this->setMapper(new \Schoolbox\Usage\UsageMapper());
	}

	/**
	 * Delegation method
	 *
	 * Find total usage
	 *
	 * @return bool|float|int|string
	 */
	public function findTotalUsage() {
		return json_encode($this->getMapper()->findTotalUsage());
	}

	/**
	 * Delegation method
	 *
	 * Find total group usage
	 *
	 * @return bool|float|int|string
	 */
	public function findGroupUsage() {
		return json_encode($this->getMapper()->findGroupUsage());
	}

	/**
	 * Delegation method
	 *
	 * Find total type usage
	 *
	 * @return bool|float|int|string
	 */
	public function findTypeUsage() {
		return json_encode($this->getMapper()->findTypeUsage());
	}

	/**
	 * Delegation method
	 *
	 * Find total user usage
	 *
	 * @return bool|float|int|string
	 */
	public function findUserUsage() {
		return json_encode($this->getMapper()->findUserUsage());
	}

	/* Getters/Setters
	 */

	/**
	 * Set mapper
	 *
	 * @param null $mapper
	 */
	private function setMapper($mapper) {
		$this->mapper = $mapper;
	}

	/**
	 * Get mapper
	 *
	 * @return null
	 */
	public function getMapper() {
		return $this->mapper;
	}
}
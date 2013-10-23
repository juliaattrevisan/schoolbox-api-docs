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
		$this->findData();
	}

	/**
	 * Find the total amount of usage by non-deleted and
	 * non-softdeleted files
	 */
	public function findData() {
		echo 'Type Storage:';
		new \Alaress_Probe($this->getMapper()->findTypeUsage());

		echo 'Total Storage:';
		new \Alaress_Probe($this->getMapper()->findTotalUsage());

		echo 'Group Storage:';
		new \Alaress_Probe($this->getMapper()->findGroupUsage());
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
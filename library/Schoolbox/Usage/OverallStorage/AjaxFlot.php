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

class AjaxFlot {

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
		$currentData = $this->getMapper()->findTotalUsage();

		$result = array();
		$result['label'] = $currentData['name'];

		$available = 80000000 - $currentData['bytes'];

		$result['data'] = array(
			array(array(1, (int)$currentData['bytes'])),
			array(array(1, (int)$available))
		);

		return json_encode($result);
	}

	/**
	 * Delegation method
	 *
	 * Find total group usage
	 *
	 * @return bool|float|int|string
	 */
	public function findGroupUsage() {
		$allData = $this->getMapper()->findGroupUsage();
		$allResults = array();

		foreach ($allData as $currentData) {
			$result = array();
			$result['label'] = $currentData['name'];
			$result['data'] = array(1, $currentData['bytes']);
			$result['bars'] = array('show' => true, 'fill' => true);

			$allResults[] = $result;
		}

		return json_encode($allResults);
	}

	/**
	 * Delegation method
	 *
	 * Find total type usage
	 *
	 * @return bool|float|int|string
	 */
	public function findTypeUsage() {
		$allData = $this->getMapper()->findTypeUsage();
		$allResults = array();

		foreach ($allData as $currentData) {
			$result = array();
			$result['label'] = $currentData['name'];
			$result['data'] = array(1, $currentData['bytes']);
			$result['bars'] = array('show' => true, 'fill' => true);

			$allResults[] = $result;
		}

		return json_encode($allResults);
	}

	/**
	 * Delegation method
	 *
	 * Find total user usage
	 *
	 * @return bool|float|int|string
	 */
	public function findUserUsage() {
		$allData = $this->getMapper()->findUserUsage();
		$allResults = array();

		foreach ($allData as $currentData) {
			$result = array();
			$result['label'] = $currentData['firstname'] . ' ' . $currentData['lastname'];
			$result['data'] = array(1, $currentData['bytes']);
			$result['bars'] = array('show' => true, 'fill' => true);

			$allResults[] = $result;
		}

		return json_encode($allResults);
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
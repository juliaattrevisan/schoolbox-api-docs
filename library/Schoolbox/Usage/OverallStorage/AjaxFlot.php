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
		$result['label'] = 'Total Usage';

		$result['data'] = array(
			array(
				'label' => 'Used',
				'data' => array(array(1, $currentData['Used'])),
			),
			array(
				'label' => 'Available',
				'data' => array(array(1, $currentData['Available']))
			)
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

		for ($i = 0; $i < count($allData); $i++) {
			$currentData = $allData[$i];

			$result = array();
			$result['label'] = $currentData['name'];
			$result['data'] = array(array($i+1, $currentData['bytes']+0));

			$allResults[] = $result;
		}

		return json_encode($allResults);
	}

	/**
	 * Calculate percentage
	 *
	 * @param $top
	 * @param $bottom
	 * @return float|int
	 */
	private function percentage($top, $bottom) {
		if (!$bottom) {
			return 0;
		}

		return 100*$top/$bottom;
	}

	/**
	 * Delegation method
	 *
	 * Find total type usage and pie it out, which uses a percentage
	 *
	 * @return bool|float|int|string
	 */
	public function findTypeUsage() {
		$allData = $this->getMapper()->findTypeUsage();

		$allResults = array();
		$totalBytes = 0;

		/* Find the total
		 */
		foreach ($allData as $currentData) {
			$totalBytes += $currentData->getBytes();
		}

		/* Find each segment
		 */
		foreach ($allData as $currentData) {
			$result = array();
			$result['label'] = $currentData->getName();
			$result['data'] = $this->percentage($currentData->getBytes(), $totalBytes);

			$allResults[] = $result;
		}

		return json_encode($allResults);
	}

	/**
	 * Delegation method
	 *
	 * Find total user usage
	 *
	 * @param $orderBy
	 * @param $order
	 * @param $index
	 * @param $numPerPage
	 */
	public function findUserUsage($orderBy, $order, $index, $numPerPage) {
		$allData = $this->getMapper()->findUserUsage($orderBy, $order, $index, $numPerPage);

		$page = new \SitePage();
		$page->setFile('user_list', '/adminv2/usage/user.html');
		$page->setBlock('user_list', 'user', 'user_block');

		/* Really? You should be passing the bytes as constructor arg
		 */
		$bytes = new \Alaress_Byte_Format();

		foreach ($allData as $currentData) {
			$page->setVar(array(
				'USER_FIRSTNAME' => $currentData['firstname'],
				'USER_LASTNAME' => $currentData['lastname'],
				'USER_GROUP' => $currentData['name'],
				'USER_BYTES' => $bytes->bytesToString($currentData['bytes']),
			));

			$page->parse('user_block', 'user', true);
		}

		echo $page->template->_varVals['user_block'];
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
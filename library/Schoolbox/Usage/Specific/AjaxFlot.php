<?php
/**
 * Data
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2011, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */
namespace Schoolbox\Usage\Specific;

class AjaxFlot {

	/**
	 * An instance of UsageMapper
	 *
	 * @var null
	 */
	private $mapper = null;

	/**
	 * Constructor
	 *
	 * @param $userId
	 */
	public function __construct($userId) {
		$this->setMapper(new UsageMapper($userId));
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
			$result['data'] = (int)$currentData['bytes'];

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
			$totalBytes += $currentData['bytes'];
		}

		/* Find each segment
		 */
		foreach ($allData as $currentData) {
			$result = array();
			$result['label'] = $currentData['name'];
			$result['data'] = $this->percentage($currentData['bytes'], $totalBytes);

			$allResults[] = $result;
		}

		return json_encode($allResults);
	}

	/**
	 * Delegation method
	 *
	 * Find total file usage
	 *
	 * @param $orderBy
	 * @param $order
	 * @param $index
	 * @param $numPerPage
	 */
	public function findFileUsage($orderBy, $order, $index, $numPerPage) {
		$allData = $this->getMapper()->findFileUsage($orderBy, $order, $index, $numPerPage);

		$page = new \SitePage();
		$page->setFile('file_list', '/adminv2/usage/file.html');
		$page->setBlock('file_list', 'file', 'file_block');

		/* Really? You should be passing the bytes as constructor arg
		 */
		$bytes = new \Alaress_Byte_Format();

		foreach ($allData as $currentData) {
			$page->setVar(array(
				'FILE_FILENAME' => $currentData['name'],
				'FILE_BYTES' => $bytes->bytesToString($currentData['bytes']),
				'FILE_MIMETYPE' => $currentData['mime_type'],
			));

			$page->parse('file_block', 'file', true);
		}

		echo $page->template->_varVals['file_block'];
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
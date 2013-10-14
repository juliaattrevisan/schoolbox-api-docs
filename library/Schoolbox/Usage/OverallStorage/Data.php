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
	 * An instance of dbManager
	 *
	 * @var null
	 */
	private $fileHandler = null;

	/**
	 * Constructor
	 */
	public function __construct() {
		$this->findData();
	}

	/**
	 * Find the total amount of usage by non-deleted and
	 * non-softdeleted files
	 */
	public function findTotalUsage() {
		$mapper = new \Schoolbox\Usage\UsageMapper();
		return $mapper->findTotalUsage();
	}

	public function findData() {
		echo 'Total Storage: ' . $this->findTotalUsage();
	}
}
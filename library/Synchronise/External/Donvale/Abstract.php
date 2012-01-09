<?php
/**
 * Synchronise_External_Donvale_Abstract
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2011, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */
abstract class Synchronise_External_Donvale_Abstract {
	private $filePath = '';
	private $fileName = '';
	private $userHandler = null;
	private $inputResource = null;
	private $columnList = array();
	private $lineCount = 1;
	private $errorCount = 0;
	private $lineEnding = "\n";

	/**
	 * Constructor
	 *
	 * @param  $filePath
	 */
	public function __construct($filePath) {
		$this->setFilePath($filePath);
		$this->setFileName();
		$this->setUserHandler(new User());

		/* Check if the input file is valid
		 * If not, die
		 */
		if (!$this->isValidInput()) {
			die("User file could not be loaded.\n");
		}
		else {
			$this->setInputResource($this->loadFile());
			$this->setColumnList();
		}
	}

	/**
	 * Use break elements to format web interface results
	 *
	 * @return void
	 */
	public function useCliFormatting() {
		$this->lineEnding = "\n";
	}

	/**
	 * Use \n elements to format cli interface results
	 *
	 * @return void
	 */
	public function useWebFormatting() {
		$this->lineEnding = '<br />';
	}

	/**
	 * Determine the difference between array of columns $columns
	 * and the intended input columns in setColumnList()
	 *
	 * @param  $columns
	 * @return bool
	 */
	protected function isMatchingColumns($columns) {
		if (!count(array_diff($columns, $this->getColumnList()))) {
			return true;
		}

		return false;
	}

	/**
	 * Subclasses must define this method
	 *
	 * @abstract
	 * @return void
	 */
	abstract public function process();

	/**
	 * Load input file
	 *
	 * @return resource
	 */
	private function loadFile() {
		return fopen($this->getFilePath(), 'r');
	}

	/**
	 * Determine if input file is valid (openable)
	 *
	 * @return bool
	 */
	private function isValidInput() {
		if (!$this->loadFile()) {
			return false;
		}

		return true;
	}

	/**
	 * Increment line count
	 *
	 * @return void
	 */
	protected function incrementLineCount() {
		$this->lineCount++;
	}

	/**
	 * Increment error count
	 *
	 * @return void
	 */
	protected function incrementErrorCount() {
		$this->errorCount++;
	}

	/**
	 * Check if the expected number of columns is not the
	 * same as the ones read in from the file
	 *
	 * @param  $propertyList
	 * @return bool
	 */
	protected function isInvalidPropertyList($propertyList) {
		if (count($propertyList) != count($this->getColumnList())) {
			print("Incorrect number of fields at line #{$this->getLineCount()} in {$this->getFileName()}{$this->getLineEnding()}");

			$this->incrementErrorCount();

			return true;
		}

		return false;
	}

	/**
	 * Seemed to flatten original var
	 *
	 * @param  $propertyList
	 * @return array
	 */
	protected function generatePropertyList($propertyList) {
		$columnList = $this->getColumnList();
		$data = array();

		foreach ($propertyList as $propertyIndex => $propertyValue ) {
			$propertyName = $columnList[$propertyIndex];

			$data[$propertyName] = $propertyValue;
		}

		return $data;
	}

	/**
	 * Get a row from the input resource
	 *
	 * @return array
	 */
	protected function getRow() {
		return fgetcsv($this->getInputResource());
	}

	/* Setters/Getters
	 */

	/**
	 * Get current value of line count
	 *
	 * @return int
	 */
	protected function getLineCount() {
		return $this->lineCount;
	}

	/**
	 * Get current value of error count
	 *
	 * @return int
	 */
	protected function getErrorCount() {
		return $this->errorCount;
	}

	/**
	 * Set column list
	 *
	 * @return void
	 */
	private function setColumnList() {
		$this->columnList = fgetcsv($this->getInputResource());
	}

	/**
	 * Get column list
	 *
	 * @return array
	 */
	protected function getColumnList() {
		return $this->columnList;
	}

	/**
	 * Set input resource
	 *
	 * @param  $val
	 * @return void
	 */
	private function setInputResource($val) {
		$this->inputResource = $val;
	}

	/**
	 * Get input resource
	 *
	 * @return null
	 */
	protected function getInputResource() {
		return $this->inputResource;
	}

	/**
	 * Set filepath
	 *
	 * @param  $val
	 * @return void
	 */
	private function setFilePath($val) {
		$this->filePath = $val;
	}

	/**
	 * Get filepath
	 * @return string
	 */
	private function getFilePath() {
		return $this->filePath;
	}

	/**
	 * Set filename
	 *
	 * @return void
	 */
	private function setFileName() {
		$this->fileName = basename($this->getFilePath());
	}

	/**
	 * Get filename
	 *
	 * @return string
	 */
	protected function getFileName() {
		return $this->fileName;
	}

	/**
	 * Set user handler instance
	 *
	 * @param User $val
	 * @return void
	 */
	private function setUserHandler($val) {
		$this->userHandler = $val;
	}

	/**
	 * Get user handler instance
	 *
	 * @return User
	 */
	protected function getUserHandler() {
		return $this->userHandler;
	}

	/**
	 * Get line ending
	 *
	 * @return string
	 */
	protected function getLineEnding() {
		return $this->lineEnding;
	}
}

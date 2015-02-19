<?php
/**
 * Alaress_Log_File
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2011, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */
class Alaress_Log_File {

	/**
	 * The place to log data
	 *
	 * @var string
	 */
	private $path = '';

	/**
	 * Prefix for the log entry
	 *
	 * @var string
	 */
	private $prefix = '';

	/**
	 * Constructor
	 *
	 * @param $path
	 * @param $prefix
	 */
	public function __construct($path, $prefix) {
		$this->setPath($path);
		$this->setPrefix($prefix);
	}

	/**
	 * Save $data to $path with a $prefix
	 *
	 * @param $data
	 */
	public function log($data) {
		$filepointer = fopen($this->getPath(), 'a+');

		ob_start();
		print_r($data);
		$x = ob_get_contents();
		fputs($filepointer, "\n---------------------------------------------------------------\n\n", 8192);
		fputs($filepointer, date('y-m-d H:i:s') . '    ' . $this->getPrefiX() . ': ' . $x . "\n\n", 16003840);
		ob_end_clean();
		fclose($filepointer);
	}

	/* Getters/Setters
	 */

	/**
	 * Set path
	 *
	 * @param string $path
	 */
	private function setPath($path) {
		$this->path = $path;
	}

	/**
	 * Get path
	 *
	 * @return string
	 */
	private function getPath() {
		return $this->path;
	}

	/**
	 * Set prefix
	 *
	 * @param string $prefix
	 */
	private function setPrefix($prefix) {
		$this->prefix = $prefix;
	}

	/**
	 * Get prefix
	 *
	 * @return string
	 */
	private function getPrefix() {
		return $this->prefix;
	}
}

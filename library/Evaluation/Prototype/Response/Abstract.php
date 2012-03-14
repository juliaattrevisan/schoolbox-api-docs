<?php
/**
 * Evaluation_Prototype_Response_Abstract
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2011, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */
abstract class Evaluation_Prototype_Response_Abstract extends DeprecatedModel {

	/**
	 * Constructor
	 *
	 * Loads class vars from a array of data, if provided
	 *
	 * @param null $currentResponse
	 */
	public function __construct($currentResponse = null) {
		if ($currentResponse != null) {
			foreach($currentResponse as $field => $value) {
				$this->$field = $value;
			}
		}
	}

	/**
	 * Export current response to csv format
	 *
	 * @abstract
	 * @return void
	 */
	public function exportToCSV() {
		if ($this->response != '') {
			$response = implode(' ', unserialize($this->response));
		}
		else {
			$response = '';
		}

		return "{$this->attempt_id}, $response, {$this->comment}\n";
	}

	/**
	 * Export current response to html legend format
	 *
	 * @return string
	 */
	public function exportToHTML() {
		if ($this->response != '') {
			$response = '<li>' . implode(' ', unserialize($this->response)) . '</li>';
		}
		else {
			$response = '<li></li>';
		}

		return "$response\n";
	}
}


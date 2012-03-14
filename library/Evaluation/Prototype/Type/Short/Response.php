<?php
/**
 * Evaluation_Prototype_Type_Short_Response
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2011, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */
class Evaluation_Prototype_Type_Short_Response extends Evaluation_Prototype_Response_Abstract {

	/**
	 * Key to use in summary
	 *
	 * @return string
	 */
	public function getKey() {
		return array($this->response);
	}

	/**
	 * Export to csv
	 *
	 * @return void
	 */
	public function exportToCSV() {
		if ($this->response != '') {
			$response = unserialize($this->response);
		}
		else {
			$response = '';
		}

		return "{$this->attempt_id}, $response, {$this->comment}\n";
	}

	/**
	 * Export to html legend format
	 *
	 * @return void
	 */
	public function exportToHTML() {
		if ($this->response != '') {
			$response = '<li>' . $this->response . '</li>';
		}
		else {
			$response = '';
		}

		return $response;
	}
}

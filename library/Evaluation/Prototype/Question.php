<?php
/**
 * Evaluation_Prototype_Question
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2011, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */
class Evaluation_Prototype_Question extends DeprecatedModel {
	private $answers = array();
	private $responses = array();
	private $summary = array();

	private $type_id = -1;
	private $numResponses = 0;

	/* Optionally filter by attempt id
	 * Use an attempt_id to filter by it
	 */
	private $attemptIdFilter = false;

	/**
	 * Load answers into tree structure
	 *
	 * @return void
	 */
	public function loadAnswers() {
		$query = "
			SELECT *
			FROM assess_answer
			WHERE question_id = {$this->getQuestionId()}
			ORDER BY sequence
		";

		$accumulated = array();

		$allAnswers = $this->db->getAll($query);

		if (is_array($allAnswers) && count($allAnswers)) {
			foreach($allAnswers as $currentAnswer) {
				$answer = new Evaluation_Prototype_Answer();

				foreach($currentAnswer as $field => $value) {
					$answer->$field = $value;
				}

				$accumulated[] = $answer;
			}
		}

		$this->setAnswers($accumulated);
	}

	/**
	 * Load responses into tree structure
	 *
	 * @return void
	 */
	public function loadResponses(Evaluation_Prototype_Response_Factory $responseFactory) {
		$query = "
			SELECT assess_response.*
			FROM assess_response
			WHERE question_id = {$this->getQuestionId()}
		";

		/* Filter by attempt id
		 */
		if ($this->getAttemptIdFilter() && is_numeric($this->getAttemptIdFilter())) {
			$query .= " AND attempt_id = '{$this->getAttemptIdFilter()}'";
		}

		$allResponses = $this->db->getAll($query);

		$accumulated = array();

		if (is_array($allResponses) && count($allResponses)) {
			foreach($allResponses as $currentResponse) {

				/* Populate new instance with $currentResponse data
				 * to save repeated hitting of db
				 */
				$responseFactory->setPreloadedData($currentResponse);

				/* Spawn object
				 */
				$accumulated[] = $responseFactory->load($this->getTypeId());

				$this->incrementNumResponses();
			}
		}

		$this->setResponses($accumulated);
	}

	/**
	 * Load summary data into tree structure
	 * This is used to make graphs
	 *
	 * Requires responses to have been loaded
	 *
	 * @return void
	 */
	public function loadSummary() {
		$summary = array();

		foreach($this->getResponses() as $currentResponse) {
			if ($currentResponse !== null) {
				if (is_array($currentResponse->getKey())) {
					foreach($currentResponse->getKey() as $currentKey) {
						$summary[$currentKey]++;
					}
				}
			}
		}

		$this->setSummary($summary);
	}

	/* Setters/Getters
	 */

	/**
	 * Set question id
	 *
	 * @param  $val
	 * @return void
	 */
	private function setQuestionId($val) {
		$this->question_id = $val;
	}

	/**
	 * Get question id
	 *
	 * @return
	 */
	private function getQuestionId() {
		return $this->question_id;
	}

	/**
	 * Set answers
	 *
	 * @param  $val
	 * @return void
	 */
	private function setAnswers($val) {
		$this->answers = $val;
	}

	/**
	 * Get answers
	 *
	 * @return array
	 */
	public function getAnswers() {
		return $this->answers;
	}

	/**
	 * Set responses
	 *
	 * @param  $val
	 * @return void
	 */
	private function setResponses($val) {
		$this->responses = $val;
	}

	/**
	 * Get responses
	 *
	 * @return array
	 */
	public function getResponses() {
		return $this->responses;
	}

	/**
	 * Set summary
	 *
	 * @param  $val
	 * @return void
	 */
	private function setSummary($val) {
		$this->summary = $val;
	}

	/**
	 * Get summary
	 *
	 * @return array
	 */
	public function getSummary() {
		return $this->summary;
	}

	/**
	 * Set the type_id
	 *
	 * @param  $val
	 * @return void
	 */
	public function setTypeId($val) {
		$this->type_id = $val;
	}

	/**
	 * Get the type_id for a question
	 *
	 * @return int
	 */
	public function getTypeId() {
		return $this->type_id;
	}

	/**
	 * Set attempt id filter
	 *
	 * @param  $val
	 * @return void
	 */
	public function setAttemptIdFilter($val) {
		$this->attemptIdFilter = $val;
	}

	/**
	 * Get attempt id filter
	 *
	 * @return bool
	 */
	public function getAttemptIdFilter() {
		return $this->attemptIdFilter;
	}

	/**
	 * Increment number of responses to this question
	 *
	 * @return void
	 */
	private function incrementNumResponses() {
		$this->numResponses++;
	}

	/**
	 * Get number of responses to this question
	 *
	 * @return int
	 */
	public function getNumResponses() {
		return $this->numResponses;
	}
}
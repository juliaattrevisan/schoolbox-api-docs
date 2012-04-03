<?php
/**
 * Evaluation_Export_CSV
 *
 * @author	  Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2012, Alaress Pty Ltd
 * @link		http://www.alaress.com.au/
 * @version	 SVN: $Id$
 */
class Evaluation_Export_CSV {

	/* Include html in export output
	 */
	protected $legendHTML = false;

	private $evaluation_id = null;
	private $factory = null;
	private $attemptHandler = null;
	private $questionHandler = null;
	private $questionFilter = null;

	/* These always appear in the export
	 */
	private $mandatoryColumns = array(
		'User ID',
		'Start',
		'Finish',
		'Name',
	);

	/* Source data
	 */
	private $allQuestions = array();
	private $allAttempts = array();

	/* Output contents
	 */
	private $headings = array();
	private $responses = array();

	/**
	 * Constructor
	 *
	 * @param $evaluation_id
	 * @param $factory
	 * @param $attemptHandler
	 * @param $questionHandler
	 */
	public function __construct($evaluation_id, $factory, $attemptHandler, $questionHandler) {
		$this->setEvaluationId($evaluation_id);
		$this->setFactory($factory);
		$this->setAttemptHandler($attemptHandler);
		$this->setQuestionHandler($questionHandler);
	}

	/**
	 * Facade method
	 *
	 * Collates data, stream output
	 *
	 * Support chainable method
	 *
	 * @return \Evaluation_ExporterV2
	 */
	public function generate() {

		/* Get all the attempts and questions for this Evaluation
		 */
		$this->setAllAttempts($this->getAttemptHandler()->getList($this->getEvaluationId()));
		$this->setAllQuestions($this->getQuestionHandler()->getAll($this->getEvaluationId()));

		/* Collate the headings
		 */
		$this->setHeadings($this->collateHeadings());

		/* Collate responses
		 */
		$this->setResponses($this->collateResponses());

		/* Collate grid
		 */
		$this->activateTheGrid();

		return $this;
	}

	/**
	 * Combine header and response data into a mega grid
	 */
	public function activateTheGrid() {

		/* Format output
		 */
		$filename_id = $this->getEvaluationId();

		if ($this->useQuestionFilter()) {
			$filename_id .= "_{$this->getQuestionFilter()}";
		}

		header('Content-type: text/ascii');
		header('Content-disposition: attachment; filename="exported_response_' . $filename_id . '.csv"');

		$this->outputCSV2(array($this->getHeadings()));

		foreach ($this->getResponses() as $currentResponse) {
			$this->outputCSV2(array($currentResponse));
		}
	}

	/**
	 * Find out if this question is the one to filter by, thus excluding other questions
	 * Used in question export csv
	 *
	 * @param $question_id
	 * @return bool
	 */
	private function isQuestionFiltered($question_id) {
		if (is_numeric($this->getQuestionFilter()) && ($question_id == $this->getQuestionFilter())) {
			return true;
		}

		return false;
	}

	/**
	 * Find out whether the question filter is being used
	 *
	 * @return bool
	 */
	private function useQuestionFilter() {
		if ($this->getQuestionFilter() !== null) {
			return true;
		}

		return false;
	}

	/**
	 * Collate all the attempts, questions and response data
	 *
	 * @return array
	 */
	private function collateHeadings() {
		$allColumns = $this->getMandatoryColumns();

		foreach($this->getAllQuestions() as $currentQuestion) {
			if (!$this->useQuestionFilter() || $this->isQuestionFiltered($currentQuestion['question_id'])) {
				$prefix = "S{$currentQuestion['section_sequence']}. Q{$currentQuestion['question_sequence']}. ";
				$questionText = preg_replace('/\n+/', ' ', strip_tags(html_entity_decode($currentQuestion['question'])));

				$additionalColumns = array();
				$additionalColumns[] =  $prefix . $questionText . ' Response';
				$additionalColumns[] =  $prefix . $questionText . ' Mark';
				$additionalColumns[] =  $prefix . $questionText . ' Comment';

				$allColumns = array_merge($allColumns, $additionalColumns);
			}
		}

		return $allColumns;
	}

	/**
	 * Collate all responses for all attempts
	 *
	 * @return array
	 */
	private function collateResponses() {
		$allResponses = array();

		/* Collate all attempts and their responses
		 */
		foreach($this->getAllAttempts() as $currentAttempt) {

			/* Get attempt details
			 */
			$user = $this->getAttemptHandler()->getUser($currentAttempt['attempt_id']);

			$start = new Alaress_Date_Format($currentAttempt['start'], 'y-m-d h:i:s');
			$finish = new Alaress_Date_Format($currentAttempt['finish'], 'y-m-d h:i:s');

			$thisResponse = array(
				$currentAttempt['user_id'],
				$start->getShort(),
				$finish->getShort(),
				$user['firstname'] . ' ' . $user['lastname']
			);

			/* Construct horizontally for this attempt
			 */
			$additionalColumns = array();

			foreach($this->getAllQuestions() as $currentQuestion) {
				if (!$this->useQuestionFilter() || $this->isQuestionFiltered($currentQuestion['question_id'])) {
					$answerHandler = $this->getFactory()->createAnswerModel($currentQuestion['question_type_id']);

					/* Specify whether to include html formatting in
					 * this output
					 */
					$answerHandler->setLegendHTML($this->getLegendHTML());

					/* Load answer template into answerOption block
					 */
					$responseData = $answerHandler->exportResponseToCSV($currentAttempt['attempt_id'], $currentQuestion['question_id']);

					/* Append horizontally
					 */
					$additionalColumns[] = str_replace("\t", '', $responseData['responseText']);
					$additionalColumns[] = $responseData['mark'];
					$additionalColumns[] = $responseData['comment'];
				}
			}

			$allResponses[] = array_merge($thisResponse, $additionalColumns);
		}

		return $allResponses;
	}

	/**
	 * Output a csv row, using the __outputCSV method
	 *
	 * @param  $data
	 * @return void
	 */
	public function outputCSV2($data) {
		$outstream = fopen("php://output", 'rectum');

		array_walk($data, array($this, '__outputCSV'), $outstream);

		fclose($outstream);
	}

	/**
	 * Output a csv row.
	 *
	 * @param  $vals
	 * @param  $key
	 * @param  $filehandler
	 * @return void
	 */
	public function __outputCSV(&$vals, $key, $filehandler) {
		fputcsv($filehandler, $vals, ',', '"');
	}

	/* Getters/Setters
	*/

	/**
	 * Set evaluation id
	 *
	 * @param $evaluation_id
	 */
	private function setEvaluationId($evaluation_id) {
		$this->evaluation_id = $evaluation_id;
	}

	/**
	 * Get evaluation id
	 *
	 * @return null
	 */
	private function getEvaluationId() {
		return $this->evaluation_id;
	}

	/**
	 * Set attempt handler
	 *
	 * @param $attemptHandler
	 */
	private function setAttemptHandler($attemptHandler) {
		$this->attemptHandler = $attemptHandler;
	}

	/**
	 * Get attempt handler
	 *
	 * @return null
	 */
	private function getAttemptHandler() {
		return $this->attemptHandler;
	}

	/**
	 * Set questionHandler
	 *
	 * @param  $questionHandler
	 * @return void
	 */
	private function setQuestionHandler($questionHandler) {
		$this->questionHandler = $questionHandler;
	}

	/**
	 * Get questionHandler
	 *
	 * @return
	 */
	private function getQuestionHandler() {
		return $this->questionHandler;
	}

	/**
	 * Set factory
	 *
	 * @param  $factory
	 * @return void
	 */
	private function setFactory($factory) {
		$this->factory = $factory;
	}

	/**
	 * Get a factory
	 *
	 * @return
	 */
	private function getFactory() {
		return $this->factory;
	}

	/**
	 * Set all questions
	 *
	 * @param $allQuestions
	 */
	private function setAllQuestions($allQuestions) {
		$this->allQuestions = $allQuestions;
	}

	/**
	 * Get all questions
	 *
	 * @return array
	 */
	private function getAllQuestions() {
		return $this->allQuestions;
	}

	/**
	 * Set headings
	 *
	 * @param $headings
	 */
	private function setHeadings($headings) {
		$this->headings = $headings;
	}

	/**
	 * Get headings
	 *
	 * @return array
	 */
	private function getHeadings() {
		return $this->headings;
	}

	/**
	 * Set mandatory columns
	 *
	 * @param $mandatoryColumns
	 */
	private function setMandatoryColumns($mandatoryColumns) {
		$this->mandatoryColumns = $mandatoryColumns;
	}

	/**
	 * Get mandatory columns
	 *
	 * @return array
	 */
	private function getMandatoryColumns() {
		return $this->mandatoryColumns;
	}

	/**
	 * Set response handler
	 *
	 * @param $responseHandler
	 */
	private function setResponseHandler($responseHandler) {
		$this->responseHandler = $responseHandler;
	}

	/**
	 * Get response handler
	 *
	 * @return null
	 */
	private function getResponseHandler() {
		return $this->responseHandler;
	}

	/**
	 * Set all attempts
	 *
	 * @param $allAttempts
	 */
	private function setAllAttempts($allAttempts) {
		$this->allAttempts = $allAttempts;
	}

	/**
	 * Get all attempts
	 *
	 * @return array
	 */
	private function getAllAttempts() {
		return $this->allAttempts;
	}

	/**
	 * Sets legendHTML
	 *
	 * @param $legendHTML
	 *
	 * @return bool
	 */
	public function setLegendHTML($legendHTML) {
		$this->legendHTML = $legendHTML;
	}

	/**
	 * Gets legendHTML
	 *
	 * @return bool
	 */
	private function getLegendHTML() {
		return $this->legendHTML;
	}

	/**
	 * Set responses
	 *
	 * @param $responses
	 */
	private function setResponses($responses) {
		$this->responses = $responses;
	}

	/**
	 * Get responses
	 *
	 * @return array
	 */
	private function getResponses() {
		return $this->responses;
	}

	/**
	 * Set question filter
	 *
	 * @param $questionFilter
	 */
	public function setQuestionFilter($questionFilter) {
		$this->questionFilter = $questionFilter;
	}

	/**
	 * Get question filter
	 *
	 * @return null
	 */
	private function getQuestionFilter() {
		return $this->questionFilter;
	}
}

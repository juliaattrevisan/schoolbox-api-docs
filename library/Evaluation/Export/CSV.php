<?php
/**
 * Evaluation_Export_CSV
 *
 * Protected class variables are intended to be set after
 * instantiation and/or overridden in subclasses
 *
 * @author	  Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link		https://schoolbox.com.au/
 */
abstract class Evaluation_Export_CSV {

	/* Include html in export output
	 */
	protected $legendHTML = false;

	/* Include synergy_id in export output
	 * Use in hook method
	 */
	protected $includeExternalId = true;

	/* These are additional subheadings that appear in the export.

	 * Due to shared functionality contained in Assess and Survey,
	 * sometimes these need to be turned on/off by specifying this
	 * array in subclasses
	 *
	 * These are mapped as Textual Display => data array key
	 * (this is used in collateResponses)
	 */
	protected $additionalColumns = array(
		'Mark' => 'mark',
		'Comment' => 'comment'
	);

	private $evaluation_id = null;
	private $factory = null;
	private $attemptHandler = null;
	private $questionHandler = null;
	private $questionFilter = null;

	/* These always appear in the export
	 */
	private $mandatoryColumns = array(
		'External ID',
		'User ID',
		'Last Name',
		'First Name',
		'Start',
		'Finish',
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

		$this->setup();
	}

	/**
	 * Hook method
	 *
	 * @return mixed
	 */
	abstract protected function setup();

	/**
	 * Facade method
	 *
	 * Collates data, stream output
	 *
	 * Support chainable method
	 *
	 * @return self
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

		/* Optionally include external_id
		 */
		if (!$this->getIncludeExternalId()) {
			array_shift($allColumns);
		}

		if (is_array($this->getAllQuestions()) && count($this->getAllQuestions())) {
			foreach($this->getAllQuestions() as $currentQuestion) {
				if (!$this->useQuestionFilter() || $this->isQuestionFiltered($currentQuestion['question_id'])) {
					$prefix = "S{$currentQuestion['section_sequence']}. Q{$currentQuestion['question_sequence']}. ";
					$questionText = preg_replace('/\n+/', ' ', strip_tags(html_entity_decode($currentQuestion['question'])));

					/* Append Response heading
					 */
					$additionalColumns = array();
					$additionalColumns[] =  $prefix . $questionText . ' Response';

					/* Append additional columns
					 */
					foreach($this->getAdditionalColumns() as $heading => $value) {
						$additionalColumns[] =  $prefix . $questionText . ' ' . $heading;
					}

					$allColumns = array_merge($allColumns, $additionalColumns);
				}
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
		if (is_array($this->getAllAttempts()) && count($this->getAllAttempts())) {
			foreach($this->getAllAttempts() as $currentAttempt) {

				/* Get attempt details
				 */
				$user = $this->getAttemptHandler()->getUser($currentAttempt['attempt_id']);

				$start = new Alaress_Date_Format($currentAttempt['start'], 'Y-m-d H:i:s');

				if ($currentAttempt['finish']) {
					$finish = new Alaress_Date_Format($currentAttempt['finish'], 'Y-m-d H:i:s');
					$finishTime = $finish->getShort();
				}
				else {
					$finishTime = 'Incomplete';
				}

				/* Collect mandatory columns
				 * Optionally include synergy_id
				 */
				$thisResponse = array(
					$currentAttempt['synergy_id'],
					$currentAttempt['user_id'],
					$user['lastname'],
					$user['firstname'],
					$start->getShort(),
					$finishTime,
				);

				if (!$this->getIncludeExternalId()) {
					array_shift($thisResponse);
				}

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

						 * Note that each answerHandler returns a $responseData containing
						 * key => data values for Response, Mark and Comment. This is used
						 * when collecting additional columns.
						 *
						 * If you want extra info beyond these three, then you need to modify
						 * each AnswerHandler accordingly.
						 */
						$responseData = $answerHandler->exportResponseToCSV($currentAttempt['attempt_id'], $currentQuestion['question_id']);

						/* Append horizontally
						 */
						$additionalColumns[] = str_replace("\t", '', $responseData['response']);

						/* Additional columns.
						 *
						 * Look for the exportResponseToCSV method which returns this data array
						 */
						foreach ($this->getAdditionalColumns() as $heading => $value) {
							$additionalColumns[] = $responseData[$value];
						}
					}
				}

				/* Concatenate mandatory columns with additional columns
				 */
				$row = array_merge($thisResponse, $additionalColumns);

				$allResponses[] = $row;
			}
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
	protected function setHeadings($headings) {
		$this->headings = $headings;
	}

	/**
	 * Get headings
	 *
	 * @return array
	 */
	protected function getHeadings() {
		return $this->headings;
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
	protected function setResponses($responses) {
		$this->responses = $responses;
	}

	/**
	 * Get responses
	 *
	 * @return array
	 */
	protected function getResponses() {
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

	/**
	 * Get additional columns
	 *
	 * @return array
	 */
	private function getAdditionalColumns() {
		return $this->additionalColumns;
	}

	/**
	 * Get includeExternalId
	 *
	 * @return bool
	 */
	protected function getIncludeExternalId() {
		return $this->includeExternalId;
	}
}

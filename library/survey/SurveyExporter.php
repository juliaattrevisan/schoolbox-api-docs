<?php

class SurveyExporter {
	private $sectionValues = array();
	private $filterByQuestion = false;

	public function __construct($assess, $factory, $sectionHandler, $attemptHandler, $questionHandler) {
		$this->setAssessID($assess['assess_id']);
		$this->setFactory($factory);
		$this->setSectionHandler($sectionHandler);
		$this->setAttemptHandler($attemptHandler);
		$this->setQuestionHandler($questionHandler);
	}

	/**
	 * Sets assess_id
	 *
	 * @param  $assess_id
	 * @return void
	 */
	private function setAssessID($assess_id) {
		$this->assess_id = $assess_id;
	}

	/**
	 * Gets assess_id
	 *
	 * @return
	 */
	private function getAssessID() {
		return $this->assess_id;
	}

	/**
	 * Sets a factory
	 *
	 * @param  $factory
	 * @return void
	 */
	private function setFactory($factory) {
		$this->factory = $factory;
	}

	/**
	 * Gets a factory
	 *
	 * @return
	 */
	private function getFactory() {
		return $this->factory;
	}

	/**
	 * Sets a sectionHandler
	 *
	 * @param  $sectionHandler
	 * @return void
	 */
	private function setSectionHandler($sectionHandler) {
		$this->sectionHandler = $sectionHandler;
	}

	/**
	 * Gets a sectionHandler
	 *
	 * @return
	 */
	private function getSectionHandler() {
		return $this->sectionHandler;
	}

	/**
	 * Sets a attemptHandler
	 *
	 * @param  $attemptHandler
	 * @return void
	 */
	private function setAttemptHandler($attemptHandler) {
		$this->attemptHandler = $attemptHandler;
	}

	/**
	 * Gets a attemptHandler
	 *
	 * @return
	 */
	private function getAttemptHandler() {
		return $this->attemptHandler;
	}

	/**
	 * Sets a questionHandler
	 *
	 * @param  $questionHandler
	 * @return void
	 */
	private function setQuestionHandler($questionHandler) {
		$this->questionHandler = $questionHandler;
	}

	/**
	 * Gets a questionHandler
	 *
	 * @return
	 */
	private function getQuestionHandler() {
		return $this->questionHandler;
	}

	/**
	 * Get needed fields for a given list of questions. This can be used
	 * to create columns for CSV export, or as headings when graphing.
	 *
	 * @param  $section_id
	 * @return array
	 */
	private function getQuestionData($allQuestions) {
		$allData = array();

		for ($i = 0; $i < count($allQuestions); $i++) {
			$currentQuestion = $allQuestions[$i];

			/* Store some extra details about the question. question_id is used by
			* the templating engine for uniqueness; type_id is for loading an
			* appropriate answerHandler, which contains the graphing method
			*/
			$allData[] = array(
				'question' => strip_tags($currentQuestion['question']),
				'question_id' => $currentQuestion['question_id'],
				'type_id' => $currentQuestion['type_id'],
			);

		}

		return $allData;
	}

	/**
	 * Updates the summary tally of responses for the given question
	 *
	 * @param  $currentQuestion
	 * @return
	 */
	public function updateResponseSummary($currentQuestion, $currentAttempt, $sectionIndex, $questionIndex) {
		if ($currentQuestion['type_id']) {
			$answerHandler = $this->getFactory()->createAnswerModel($currentQuestion['type_id']);

			/* Load answer template into answerOption block
			 */
			$currentResponse = $answerHandler->exportResponseToArray($currentAttempt['attempt_id'], $currentQuestion['question_id']);

			if (is_array($currentResponse)) {
				foreach($currentResponse as $individualResponse) {

					/* Find out if you need to extract the response text for the
					 * options that were available
					 */
					if (!isset($this->sectionValues[$sectionIndex]['questions'][$questionIndex]['summary'][$individualResponse]['text'])) {
						$answerText = $answerHandler->getAnswerText($currentQuestion['question_id'], $individualResponse);

						$this->sectionValues[$sectionIndex]['questions'][$questionIndex]['summary'][$individualResponse]['text'] = $answerText;
					}

					$this->sectionValues[$sectionIndex]['questions'][$questionIndex]['summary'][$individualResponse]['values']++;
				}
			}

			/* Inconcatenatable non-summarisable data (short answer) which only
			 * need to be collated into a giant list
			 */
			else {
				$this->sectionValues[$sectionIndex]['questions'][$questionIndex]['summary'][$currentResponse]['values']++;
			}
		}
	}

	/**
	 * Set a question_id to filter by
	 *
	 * @param  $question_id
	 * @return void
	 */
	public function setFilterByQuestion($question_id) {
		$this->filterByQuestion = $question_id;
	}

	/**
	 * Get a question_id to filter by
	 *
	 * @return
	 */
	public function getFilterByQuestion() {
		return $this->filterByQuestion;
	}

	/**
	 * Gets the questions and summary of results for each section
	 *
	 * @return array
	 */
	public function getSectionValues() {

		/* Determine whether to get all sections, or just the one belonging to
		 * the question by which to filter
		 */
		if ($this->getFilterByQuestion()) {
			$question = $this->getQuestionHandler()->getByID($this->getFilterByQuestion());

			/* Need to be in array format, for iteration
			 */
			$allSections = array($this->getSectionHandler()->getByID($question['section_id']));
		}
		else {
			$allSections = $this->getSectionHandler()->getList($this->getAssessID());
		}

		/* Process
		 */
		for ($k = 0; $k < count($allSections); $k++) {
			$currentSection = $allSections[$k];

			/* Initialise array data
			 */
			$this->sectionValues[$k]['section'] = $currentSection['title'];

			/* Get a list of questions to use as heading data
			*/
			$allQuestions = $this->getSectionHandler()->getContents($this->getAssessID(), $currentSection['section_id'], false, $this->getFilterByQuestion());

			$this->sectionValues[$k]['questions'] = $this->getQuestionData($allQuestions);

			/* Get attempts for the assessment
			 */
			$allAttempts = $this->getAttemptHandler()->getListByAssessAndSection($this->getAssessID(), $currentSection['section_id']);

			if ($allAttempts) {
				for ($j = 0; $j < count($allAttempts); $j++) {
					$currentAttempt = $allAttempts[$j];

					$attemptValues[$j]['name'] = $currentAttempt['firstname'] . ' ' . $currentAttempt['lastname'];

					if ($allQuestions) {
						$responseValues = array();

						for ($i = 0; $i < count($allQuestions); $i++) {

							$this->updateResponseSummary($allQuestions[$i], $allAttempts[$j], $k, $i);

							/* This if() clause allows for sections containing no questions.
							 * because they come out with getContents()
							 */
							if ($allQuestions[$i]['type_id']) {
								$answerHandler = $this->getFactory()->createAnswerModel($allQuestions[$i]['type_id']);

								/* Load answer template into answerOption block
								 */
								$responseValues[$i] = strip_tags(str_replace("\t", '', $answerHandler->exportResponseToCSV($currentAttempt['attempt_id'], $allQuestions[$i]['question_id'])));
							}
						}
					}

					$attemptValues[$j]['responses'] = $responseValues;
				}
			}

			$this->sectionValues[$k]['attempts'] = $attemptValues;
		}

		return $this->sectionValues;
	}

	/**
	 * Output a csv row, using the __outputCSV method
	 *
	 * @param <type> $data
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
}

?>

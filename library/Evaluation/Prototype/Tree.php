<?php
/**
 * Evaluation_Prototype_Tree
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2011, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */
class Evaluation_Prototype_Tree extends DeprecatedModel {

	/* Question Types (must match the lookup table definitions)
	 */
	const SHORT_QUESTION_TYPE_ID = 1;

	/* Class variables
	 */
	private $user_id = null;
	private $evaluation_id = null;
	private $sections = array();
	private $responseFactory = null;
	private $attemptContainer = null;

	/**
	 * Constructor
	 *
	 * @param  $evaluation_id
	 * @param  $treeLimit
	 * @param \Evaluation_Prototype_Response_Factory $responseFactory
	 * @param $user_id
	 */
	public function __construct($evaluation_id, $treeLimit, Evaluation_Prototype_Response_Factory $responseFactory, $user_id) {
		parent::__construct();

		$this->setUserId($user_id);
		$this->setEvaluationId($evaluation_id);
		$this->setResponseFactory($responseFactory);
		$this->setAttemptContainer(new Evaluation_Prototype_Attempt_Container($evaluation_id));
		$this->setParameters(new Evaluation_Prototype_Parameters($treeLimit));

		/* Load details about this Evaluation
		 */
		$this->loadEvaluation();

		/* Set up permissions object
		 */
		$this->permissions = new Evaluation_Prototype_Permission($evaluation_id, $this->getAttemptContainer()->getLatestForUser($this->getUserId()));
	}

	/**
	 * Load details about this evaluation
	 *
	 * @return void
	 */
	private function loadEvaluation() {
		$query = "
			SELECT *
			FROM assess
			WHERE assess_id = {$this->getEvaluationId()}
		";

		$currentAssess = $this->db->getRow($query);

		if ($currentAssess) {
			foreach($currentAssess as $field => $value) {
				$this->$field = $value;
			}
		}
	}

	/**
	 * Get all sections in this evaluation
	 *
	 * @return array|bool
	 */
	private function getAllSections() {
			$query = "
				SELECT *
				FROM assess_section
				WHERE assess_id = {$this->getEvaluationId()}
				ORDER BY sequence
			";

		return $this->db->getAll($query);
	}

	/**
	 * Construct a tree of sections, questions, answers
	 * and submitted responses. Clients can specify the
	 * depth of the tree by using the class consts
	 *
	 * @return void
	 */
	public function growTree() {

		/* Load sections
		 */
		if ($this->getParameters()->includeSections()) {
			$accumulated = array();

			$allSections = $this->getAllSections();

			if (is_array($allSections) && count($allSections)) {
				foreach($allSections as $currentSection) {
					$section = new Evaluation_Prototype_Section();

					/* Filter by a particular question
					 */
					$section->setQuestionIdFilter($this->getParameters()->getQuestionIdFilter());

					foreach($currentSection as $field => $value) {
						$section->$field = $value;
					}

					/* Load questions
					 */
					if ($this->getParameters()->includeQuestions()) {
						$section->loadQuestions();

						/* Load answers and/or responses
						 */
						foreach ($section->getQuestions() as $currentQuestion) {
							$currentQuestion->setAttemptIdFilter($this->getParameters()->getAttemptIdFilter());

							/* Include answer details
							 */
							if ($this->getParameters()->includeAnswers()) {
								$currentQuestion->loadAnswers();
							}

							/* Include response details
							 */
							if ($this->getParameters()->includeResponses()) {
								$currentQuestion->loadResponses($this->getResponseFactory());
							}

							/* Include summary details
							 */
							if ($this->getParameters()->includeSummary()) {
								$currentQuestion->loadSummary();
							}
						}
					}

					$accumulated[] = $section;
				}
			}

			$this->setSections($accumulated);
		}

		/* Load attempt data
		 */
		$this->getAttemptContainer()->setUserAttempts($this->getAttemptContainer()->getNumAttempts($this->getUserId()));
	}

	/**
	 * Temporary method to excrete the tree details
	 *
	 * @return void
	 */
	public function dumpTree() {
		echo "
		<style type='text/css'>
		td.col2 { padding-left: 100px; }
		td.col3 { padding-left: 200px; }
		td.col4 { padding-left: 300px; }
		td.col5 { padding-left: 400px; }
		td.col6 { padding-left: 500px; }
		td.break { border-top: 1px solid #000; }
		</style>";

		echo "<table>";
		echo "<tr><td class='col1'>Assess {$this->title}<br />You've attempted this {$this->getAttemptContainer()->getUserAttempts()} times</td></tr>";

		/* Print sections
		 */
		foreach ($this->getSections() as $currentSection) {
			echo "<tr><td class='col2'>Section {$currentSection->sequence}: {$currentSection->title} ({$currentSection->getNumQuestions()} questions)</td></tr>";

			/* Print questions
			 */
			foreach ($currentSection->getQuestions() as $currentQuestion) {
				echo "<tr><td class='break'>&nbsp;</td></tr>";
				echo "<tr><td class='col3'>Question {$currentQuestion->sequence} ({$currentQuestion->getNumResponses()} responses)</td></tr>";
				echo "<tr><td class='col3'>Type {$currentQuestion->getTypeId()} (" . trim($currentQuestion->description) . "): {$currentQuestion->question}</td></tr>";

				/* Print answers
				 */
				foreach ($currentQuestion->getAnswers() as $currentAnswer) {
					echo "<tr><td class='col4'>Answer<br />";

					echo "<tr><td class='col5'>";

					if ($currentQuestion->getTypeId() == self::SHORT_QUESTION_TYPE_ID) {
						print_r(array($currentAnswer->answer));
					}
					else {
						print_r(unserialize($currentAnswer->answer));
					}

					echo "</td></tr>";
				}

				/* Print responses
				 */
				echo "<tr><td class='col4'>Responses</td></tr>";

				foreach ($currentQuestion->getResponses() as $currentResponse) {
					echo "<tr><td class='col5'>Attempt {$currentResponse->attempt_id}</td></tr>";

					echo "<tr><td class='col6'>";

					if ($currentQuestion->getTypeId() == self::SHORT_QUESTION_TYPE_ID) {
						print_r(array($currentResponse->response));
					}
					else {
						print_r(unserialize($currentResponse->response));
					}

					echo "<br />Export string: ";
					echo $currentResponse->exportToCSV();

					echo "<br />Export html: ";
					echo '<ul>' . $currentResponse->exportToHTML() . '</ul>';
					echo "</td><tr>";
				}

				/* Print summary
				 */
				echo "<tr><td class='col4'>Summary</td></tr>";

				echo "<tr><td class='col5'>";

				if (count($currentQuestion->getSummary())) {
					print_r($currentQuestion->getSummary());
				}

				echo "</td></tr>";
			}
		}

		echo "</table>";
	}

	/* Setters/Getters
	 */

	/**
	 * Set evaluation id
	 *
	 * @param  $val
	 * @return void
	 */
	private function setEvaluationId($val) {
		$this->evaluation_id = $val;
	}

	/**
	 * Get evaluation id
	 *
	 * @return
	 */
	private function getEvaluationId() {
		return $this->evaluation_id;
	}

	/**
	 * Set sections
	 *
	 * @param  $val
	 * @return void
	 */
	private function setSections($val) {
		$this->sections = $val;
	}

	/**
	 * Get sections
	 *
	 * @return array
	 */
	public function getSections() {
		return $this->sections;
	}

	/**
	 * Get parameters object
	 *
	 * @return
	 */
	public function getParameters() {
		return $this->parameters;
	}

	/**
	 * Set parameters object
	 *
	 * @param  $val
	 * @return void
	 */
	private function setParameters($val) {
		$this->parameters = $val;
	}

	/**
	 * Set response factory
	 *
	 * @param  $val
	 * @return void
	 */
	private function setResponseFactory($val) {
		$this->responseFactory = $val;
	}

	/**
	 * Get response factory
	 *
	 * @return
	 */
	private function getResponseFactory() {
		return $this->responseFactory;
	}

	/**
	 * Set attempt container
	 *
	 * @param  $val
	 * @return void
	 */
	private function setAttemptContainer($val) {
		$this->attemptContainer = $val;
	}

	/**
	 * Get attempt container
	 *
	 * @return null
	 */
	private function getAttemptContainer() {
		return $this->attemptContainer;
	}

	/**
	 * Set user id
	 *
	 * @param  $val
	 * @return void
	 */
	private function setUserId($val) {
		$this->user_id = $val;
	}

	/**
	 * Get user id
	 *
	 * @return null
	 */
	private function getUserId() {
		return $this->user_id;
	}
}
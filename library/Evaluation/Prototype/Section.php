<?php
/**
 * Evaluation_Prototype_Section
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2011, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */
class Evaluation_Prototype_Section extends DeprecatedModel {
	private $questions = array();
	private $numQuestions = 0;

	/**
	 * Load questions into tree structure
	 *
	 * @return void
	 */
	public function loadQuestions() {
		$query = "
			SELECT
				assess_question.*,
				assess_luQuestionType.type_id,
				assess_luQuestionType.description
			FROM assess_question
			LEFT JOIN assess_luQuestionType ON assess_question.type_id = assess_luQuestionType.type_id
			WHERE section_id = {$this->getSectionId()}";

		/* Filter by question id
		 */
		if ($this->getQuestionIdFilter() && is_numeric($this->getQuestionIdFilter())) {
			$query .= " AND assess_question.question_id = '{$this->getQuestionIdFilter()}'";
		}

		$query .= "
			ORDER BY sequence
		";

		$accumulated = array();

		$allQuestions = $this->db->getAll($query);

		if (is_array($allQuestions) && count($allQuestions)) {
			foreach($allQuestions as $currentQuestion) {
				$question = new Evaluation_Prototype_Question();

				foreach($currentQuestion as $field => $value) {
					if ($field == 'type_id') {
						$question->setTypeId($value);
					}
					else {
						$question->$field = $value;
					}
				}

				$accumulated[] = $question;

				$this->incrementNumQuestions();
			}
		}

		$this->setQuestions($accumulated);
	}

	/* Setters/Getters
	 */

	/**
	 * Set section id
	 *
	 * @param  $val
	 * @return void
	 */
	private function setSectionId($val) {
		$this->section_id = $val;
	}

	/**
	 * Get section id
	 *
	 * @return
	 */
	private function getSectionId() {
		return $this->section_id;
	}

	/**
	 * Set questions
	 *
	 * @param  $val
	 * @return void
	 */
	private function setQuestions($val) {
		$this->questions = $val;
	}

	/**
	 * Get questions
	 *
	 * @return array
	 */
	public function getQuestions() {
		return $this->questions;
	}


	/**
	 * Increment number of questions in this section
	 *
	 * @return void
	 */
	private function incrementNumQuestions() {
		$this->numQuestions++;
	}

	/**
	 * Get number of questions in this section
	 *
	 * @return int
	 */
	public function getNumQuestions() {
		return $this->numQuestions;
	}

	/**
	 * Set question id filter
	 *
	 * @param  $val
	 * @return void
	 */
	public function setQuestionIdFilter($val) {
		$this->questionIdFilter = $val;
	}

	/**
	 * Get question id filter
	 *
	 * @return bool
	 */
	public function getQuestionIdFilter() {
		return $this->questionIdFilter;
	}
}

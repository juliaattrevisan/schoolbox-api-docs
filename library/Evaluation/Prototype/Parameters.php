<?php
/**
 * Evaluation_Prototype_Parameters
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2011, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */
class Evaluation_Prototype_Parameters {

	/* Consts to determine the depth of the Evaluation tree
	 * i.e. whether to includ sections, questions, answers
	 * and responses.
	 */
	const EVALUATION = 0;
	const SECTIONS = 1;
	const QUESTIONS = 2;
	const ANSWERS = 3;
	const RESPONSES = 4;
	const SUMMARY = 5;

	/* Limit the depth of the tree
	 */
	private $treeLimit = -1;

	/* Optionally filter by attempt id
	 * Use an attempt_id to filter by it
	 */
	private $attemptIdFilter = false;

	/* Optionally filter by question id
	 * Use a question_id to filter by it
	 */
	private $questionIdFilter = false;

	/**
	 * Constructor
	 *
	 * @param  $treeLimit
	 */
	public function __construct($treeLimit) {
		$this->setTreeLimit($treeLimit);

		/* Optionally filter by an attempt id
		 * $this->setAttemptIdFilter(int $attempt_id);
		 */

		/* Optionally filter by an question id
		 * $this->setQuestionIdFilter(int $question_id);
		 */
	}

	/* Setters/Getters
	 */

	/**
	 * Set tree depth limit
	 *
	 * @param  $val
	 * @return void
	 */
	public function setTreeLimit($val) {
		$this->treeLimit = $val;
	}

	/**
	 * Get tree depth limit
	 *
	 * @return int
	 */
	public function getTreeLimit() {
		return $this->treeLimit;
	}

	/**
	 * Set attempt id filter.
	 * Use boolean false to disable filtering.
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
	 * Set question id filter.
	 * Use boolean false to disable filtering.
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

	/**
	 * Whether to include Sections in tree construction
	 *
	 * @return bool
	 */
	public function includeSections() {
		if ($this->getTreeLimit() >= self::SECTIONS) {
			return true;
		}

		return false;
	}

	/**
	 * Whether to include Questions in tree construction
	 *
	 * @return bool
	 */
	public function includeQuestions() {
		if ($this->getTreeLimit() >= self::QUESTIONS) {
			return true;
		}

		return false;
	}

	/**
	 * Whether to include Answers in tree construction
	 *
	 * @return bool
	 */
	public function includeAnswers() {
		if ($this->getTreeLimit() >= self::ANSWERS) {
			return true;
		}

		return false;
	}

	/**
	 * Whether to include Responses in tree construction
	 *
	 * @return bool
	 */
	public function includeResponses() {
		if ($this->getTreeLimit() >= self::RESPONSES) {
			return true;
		}

		return false;
	}

	/**
	 * Whether to include Summary in tree construction
	 *
	 * @return bool
	 */
	public function includeSummary() {
		if ($this->getTreeLimit() >= self::SUMMARY) {
			return true;
		}

		return false;
	}
}

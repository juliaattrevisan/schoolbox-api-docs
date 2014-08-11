<?php
/**
 * Assess_Question_Bank
 *
 * Manages saved questions in the QuestionBank
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2011, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */
class Assess_Question_Bank extends Evaluation_Question_Bank {
    public $_table = 'assess_question_bank';
    public $_index = 'question_bank_id';

	/**
	 * Handler
	 *
	 * @var Assess_Question
	 */
	private $questionHandler = null;

	/**
	 * Handler
	 *
	 * @var null
	 */
	private $answerHandler = null;

	/**
	 * Constructor
	 */
	public function __construct() {
		parent::__construct();

		$this->setup();
	}

	/**
	 * Hook method
	 */
	private function setup() {
		$this->setQuestionHandler(new Assess_Question());
		$this->setAnswerHandler(new Assess_Answer());
		$this->setContext();
	}

	/**
	 * Make the assess_question_bank table behave like assess_question
	 */
	private function setContext() {
		$this->getQuestionHandler()->_table = 'assess_question_bank';
		$this->getQuestionHandler()->_index = 'question_bank_id';

		/* Use context-specific fields
		 */
		$this->getQuestionHandler()->setAllowedFields(array(
			'question_bank_id', 'question_category_id', 'type_id',
			'question', 'answer', 'marks',
			'config', 'deleted_at'
		));
	}

	/* Getters/Setters
	 */

	/**
	 * Set question handler
	 *
	 * @param \Assess_Question|null $questionHandler
	 */
	private function setQuestionHandler($questionHandler) {
		$this->questionHandler = $questionHandler;
	}

	/**
	 * Get question handler
	 *
	 * @return \Assess_Question|null
	 */
	public function getQuestionHandler() {
		return $this->questionHandler;
	}

	/**
	 * Set answer handler
	 *
	 * @param null $answerHandler
	 */
	private function setAnswerHandler($answerHandler) {
		$this->answerHandler = $answerHandler;
	}

	/**
	 * Get answer handler
	 *
	 * @return null
	 */
	public function getAnswerHandler() {
		return $this->answerHandler;
	}
}
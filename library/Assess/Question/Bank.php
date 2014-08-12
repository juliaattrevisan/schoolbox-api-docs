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
	protected function setup() {
		$this->setQuestionHandler(new Assess_Question());
		$this->setAnswerHandler(new Assess_Answer());
		$this->setContext();
	}

	/**
	 * Make the assess_question_bank table behave like assess_question
	 */
	protected function setContext() {

		/* Use context-specific fields
		 */
		$this->getQuestionHandler()->setAllowedFields(array(
			'question_bank_id', 'question_category_id', 'type_id',
			'question', 'answer', 'marks',
			'config', 'deleted_at'
		));
	}
}
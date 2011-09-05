<?php
/**
 * SurveyQuestionBank
 *
 * Manages saved questions in the QuestionBank
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2011, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */
require_once($_SERVER['DOCUMENT_ROOT'] . '/../library/evaluation/EvaluationQuestionBank.php');

class SurveyQuestionBank extends EvaluationQuestionBank {
    public $_table = 'assess_question_bank';
    public $_index = 'question_bank_id';

	public function __construct() {
		parent::__construct();

		$this->questionHandler = new SurveyQuestion();
		$this->answerHandler = new SurveyAnswer();
	}
}

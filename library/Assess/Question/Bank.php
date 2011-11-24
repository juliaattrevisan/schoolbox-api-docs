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

	public function __construct() {
		parent::__construct();

		$this->questionHandler = new Assess_Question();
		$this->answerHandler = new Assess_Answer();
	}
}

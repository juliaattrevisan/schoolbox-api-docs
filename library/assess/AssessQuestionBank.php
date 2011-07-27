<?php
require_once($_SERVER['DOCUMENT_ROOT'] . '/../library/evaluation/EvaluationQuestionBank.php');

class AssessQuestionBank extends EvaluationQuestionBank {
    public $_table = 'assess_question_bank';
    public $_index = 'question_bank_id';

	public function __construct() {
		parent::__construct();

		$this->questionHandler = new AssessQuestion();
		$this->answerHandler = new AssessAnswer();
	}
}
?>

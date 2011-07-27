<?php
require_once($_SERVER['DOCUMENT_ROOT'] . '/../library/evaluation/EvaluationQuestionBank.php');

class AssessQuestionBank extends EvaluationQuestionBank {
    protected $_table = 'assess_question_bank';
    protected $_index = 'question_bank_id';
}
?>

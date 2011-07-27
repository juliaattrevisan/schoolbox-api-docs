<?php
require_once($_SERVER['DOCUMENT_ROOT'] . '/../library/evaluation/EvaluationQuestionBank.php');

class SurveyQuestionBank extends EvaluationQuestionBank {
    public $_table = 'assess_question_bank';
    public $_index = 'question_bank_id';
}
?>


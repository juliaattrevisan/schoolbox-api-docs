<?php
require_once($_SERVER['DOCUMENT_ROOT'] . '/../library/evaluation/EvaluationAnswer.php');

class SurveyAnswer extends EvaluationAnswer {
    public $_table = 'assess_answer';
    public $_index = 'answer_id';

    protected $allowedFields = array('answer_id', 'question_id', 'sequence', 'answer', 'deleted_at');
}

?>

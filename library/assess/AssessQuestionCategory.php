<?php
require_once($_SERVER['DOCUMENT_ROOT'] . '/../library/evaluation/EvaluationQuestionCategory.php');

class AssessQuestionCategory extends EvaluationQuestionCategory {
    protected $_table = 'assess_question_category';
    protected $_index = 'assess_question_category_id';
}
?>

<?php
require_once($_SERVER['DOCUMENT_ROOT'] . '/../library/evaluation/EvaluationQuestionCategory.php');

class SurveyQuestionCategory extends EvaluationQuestionCategory {
    public $_table = 'assess_question_category';
    public $_index = 'assess_question_category_id';
}
?>

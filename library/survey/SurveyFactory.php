<?php
require_once($_SERVER['DOCUMENT_ROOT'] . '/../library/evaluation/EvaluationFactory.php');

class SurveyFactory extends EvaluationFactory {
	protected $factoryName = 'Survey';

    protected $questionSubclassPath = '../library/survey/questionType';
    protected $answerSubclassPath = '../library/survey/answerType';
}

?>

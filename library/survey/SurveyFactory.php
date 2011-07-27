<?php
require_once('../library/assess/AssessFactory.php');

class SurveyFactory extends AssessFactory {
	protected $factoryName = 'Survey';

    protected $questionSubclassPath = '../library/survey/questionType';
    protected $answerSubclassPath = '../library/survey/answerType';
}

?>

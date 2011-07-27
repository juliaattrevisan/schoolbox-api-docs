<?php
require_once('../library/assess/AssessFactory.php');

class SurveyFactory extends AssessFactory {
	protected $factoryName = 'Survey';
    protected $questionLibraryPath = '../library/survey/questionType';
    protected $answerLibraryPath = '../library/survey/answerType';
}

?>

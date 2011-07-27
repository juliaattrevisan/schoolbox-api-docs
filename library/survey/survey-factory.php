<?php
require_once('../library/assess/assess-factory.php');

class SurveyFactory extends AssessFactory {
    protected $questionLibraryPath = '../library/survey/questionType';
    protected $questionPrefix = 'SurveyQuestion';

    protected $answerLibraryPath = '../library/survey/answerType';
    protected $answerPrefix = 'SurveyAnswer';

	protected $filePrefix = 'survey';
}

?>

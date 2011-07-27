<?php
require_once($_SERVER['DOCUMENT_ROOT'] . '/../library/evaluation/EvaluationAnswer.php');
require_once($_SERVER['DOCUMENT_ROOT'] . '/../library/survey/SurveyResponse.php');

class SurveyAnswer extends EvaluationAnswer {
    public $_table = 'assess_answer';
    public $_index = 'answer_id';

    protected $allowedFields = array('answer_id', 'question_id', 'sequence', 'answer', 'deleted_at');

	/**
	 * Get an instance of a responseHandler upon instantiation
	 */
    public function __construct() {
        parent::__construct();

        $this->responseHandler = new SurveyResponse();
    }
}

?>

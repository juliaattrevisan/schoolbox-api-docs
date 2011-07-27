<?php
class SurveyAnswerMulti extends AnswerMulti {

	/**
	 * Get an instance of a responseHandler upon instantiation
	 */
    public function __construct() {
        parent::__construct();

        $this->responseHandler = new SurveyResponse();
    }
}
?>

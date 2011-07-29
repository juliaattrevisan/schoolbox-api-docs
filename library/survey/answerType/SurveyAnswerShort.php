<?php
class SurveyAnswerShort extends AnswerShort {

	/**
	 * Get an instance of a responseHandler upon instantiation
	 */
    public function __construct() {
        parent::__construct();

        $this->responseHandler = new SurveyResponse();
    }

	/**
	 * Get the response for a particular question
	 *
	 * @param <type> $response_id
	 */
	public function exportResponseToArray($attempt_id, $question_id) {
		$response = $this->responseHandler->getByAttemptAndQuestion($attempt_id, $question_id);

		$data = $response['response'];

		return $data;
	}


	/**
	 * Graph the results for this answer type
	 *
	 * @param  $page
	 * @param  $currentQuestion
	 * @return void
	 */
	public function graphResults($page, $currentQuestion) {
		$page->setVar('graph_block', '');
	}
}
?>

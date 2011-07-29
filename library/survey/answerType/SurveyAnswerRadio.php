<?php
class SurveyAnswerRadio extends AnswerRadio {

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

		$data = unserialize($response['response']);

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
		$componentHandle = 'temp' . $currentQuestion['question_id'];

		$page->setFile($componentHandle, 'survey/answer/radio/graph.html');
		$page->setBlock($componentHandle, 'series', 'series_block');
		$page->setBlock('series', 'series_separator', 'series_separator_block');

		/* Render out data set
		 */
		$i = 0;

		foreach($currentQuestion['summary'] as $key => $value) {
			$page->setVar(array(
				'SERIES_LABEL' => $value['text'],
				'SERIES_DATA' => $value['values'],
			));

			if ($i < (count($currentQuestion['summary'])-1)) {
				$page->parse('series_separator_block', 'series_separator');
			}
			else {
				$page->setVar('series_separator_block', '');
			}

			$page->parse('series_block', 'series', true);

			/* This index is used to determine whether or not to parse out a
			 * separator comma between object literals in data set
			 */
			$i++;
		}

		$page->setVar(array(
			'GRAPH_ID' => 'surveyQuestion_' . md5($currentQuestion['question']),
		));

		$page->parse('graph_block', $componentHandle);
	}
}
?>

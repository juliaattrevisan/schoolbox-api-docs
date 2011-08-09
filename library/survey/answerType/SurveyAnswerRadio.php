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

		if (is_array($currentQuestion['summary']) && count($currentQuestion['summary'])) {
			foreach($currentQuestion['summary'] as $key => $value) {

				/* If the option hasn't been responded to
				 */
				if ($value['text'] == '') {
					$value['text'] = 'Unanswered';
				}

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
		}
		else {
			$page->setVar('series_block', '');
		}

		$page->parse('graph_block', $componentHandle);
	}
}
?>

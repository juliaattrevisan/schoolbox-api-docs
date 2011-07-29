<?php
class SurveyAnswerMulti extends AnswerMulti {

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

		$page->setFile($componentHandle, 'survey/answer/multi/graph.html');
		$page->setBlock($componentHandle, 'series', 'series_block');
		$page->setBlock('series', 'series_separator', 'series_separator_block');

		/* Render out data set
		 */
		$i = 0;

		foreach($currentQuestion['summary'] as $key => $value) {
			$page->setVar(array(
				'SERIES_LABEL' => $value['text'],
				'SERIES_DATA' => $value['values'],
				'SERIES_INDEX' => $i
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

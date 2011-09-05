<?php
/**
 * SurveyAnswerShort
 *
 * Handler for Short Answer questions.
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   2010 Alaress Pty Ltd
 * @link        http://www.alaress.com.au
 * @version     SVN: $Id$
 */
class SurveyAnswerShort extends AnswerShort {

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

		$page->setFile($componentHandle, 'survey/answer/short/graph.html');

		$page->parse('graph_block', $componentHandle);
	}
}

<?php
/**
 * Survey_Answer_Multi
 *
 * Handler for Multiple Choice w/ Other questions.
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   2010 Alaress Pty Ltd
 * @link        http://www.alaress.com.au
 * @version     SVN: $Id$
 */
class Survey_Answer_MultiOther extends Evaluation_Answer_MultiOther {
	protected $graphTemplateFile = 'survey/answer/multi/graph.html';

	/* Use this to toggle between letters and actual answers
	 */
	protected $useLongAnswers = true;

	/**
	 * Get an instance of a responseHandler upon instantiation
	 */
    public function __construct() {
        parent::__construct();

        $this->responseHandler = new Survey_Response();
    }

	/**
	 * Graph the results for this answer type
	 *
	 * @param  $page
	 * @param  $currentQuestion
	 * @return void
	 */
	public function graphResults($page, $currentQuestion) {
		$graph = new Survey_Graph_Render($page, $currentQuestion, $this->getGraphTemplateFile());
		$graph->graph();
	}

	/**
	 * Get the css class to use for incorrect responses
	 * Always use the correctClass for Surveys
	 *
	 * @return string
	 */
	protected function getIncorrectClass() {
		return $this->correctClass;
	}
}

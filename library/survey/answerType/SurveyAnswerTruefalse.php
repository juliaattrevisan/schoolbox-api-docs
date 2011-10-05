<?php
/**
 * SurveyAnswerTruefalse
 *
 * Handler for rating questions.
 *
 * @author      James Leckie <jleckie@alaress.com.au>
 * @copyright   2010 Alaress Pty Ltd
 * @link        http://www.alaress.com.au
 * @version     SVN: $Id$
 */
class SurveyAnswerTruefalse extends AnswerTruefalse {
	protected $graphTemplateFile = 'survey/answer/truefalse/graph.html';

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
		require_once($_SERVER['DOCUMENT_ROOT'] . '/../library/survey/Graph/Render.php');

		$graph = new Survey_Graph_Render($page, $currentQuestion, $this->getGraphTemplateFile());
		$graph->graph();
	}
}

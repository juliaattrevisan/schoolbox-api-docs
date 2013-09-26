<?php
/**
 * Survey_Answer_TrueFalse
 *
 * Handler for rating questions.
 *
 * @author      James Leckie <jleckie@alaress.com.au>
 * @copyright   2010 Alaress Pty Ltd
 * @link        http://www.alaress.com.au
 * @version     SVN: $Id$
 */
class Survey_Answer_TrueFalse extends Evaluation_Answer_TrueFalse {


	/* Always use Correct css class
	 */
	protected $incorrectClass = 'multiCorrect';

	protected $graphTemplateFile = 'survey/answer/truefalse/graph.html';

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
}

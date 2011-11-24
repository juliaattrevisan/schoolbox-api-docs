<?php
/**
 * Survey_Answer_Rating_Important
 *
 * Handler for rating questions.
 *
 * @author      James Leckie <jleckie@alaress.com.au>
 * @copyright   2010 Alaress Pty Ltd
 * @link        http://www.alaress.com.au
 * @version     SVN: $Id$
 */
class Survey_Answer_Rating_Important extends Evaluation_Answer_Rating_Important {
	protected $graphTemplateFile = 'survey/answer/ratingImportant/graph.html';

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

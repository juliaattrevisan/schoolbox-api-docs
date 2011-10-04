<?php
/**
 * SurveyAnswerYesno
 *
 * Handler for rating questions.
 *
 * @author      James Leckie <jleckie@alaress.com.au>
 * @copyright   2010 Alaress Pty Ltd
 * @link        http://www.alaress.com.au
 * @version     SVN: $Id$
 */
class SurveyAnswerYesno extends AnswerYesno {
	protected $templateFile = 'survey/answer/multi/graph.html';

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
		require_once($_SERVER['DOCUMENT_ROOT'] . '/../library/survey/Graph/Bar.php');

		$graph = new Survey_Graph_Barchart($page, $currentQuestion, $this->getTemplateFile());
		$graph->graph();
	}

	/**
	 * Get template file
	 *
	 * @return string
	 */
	protected function getTemplateFile() {
		return $this->templateFile;
	}

	/**
	 * Set template file
	 *
	 * @param  $val
	 * @return void
	 */
	protected function setTemplateFile($val) {
		$this->templateFile = $val;
	}
}

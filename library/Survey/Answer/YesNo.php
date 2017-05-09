<?php
/**
 * Survey_Answer_YesNo
 *
 * Handler for rating questions.
 *
 * @author      James Leckie <jleckie@alaress.com.au>
 * @copyright   2010 Alaress Pty Ltd
 * @link        http://www.alaress.com.au
 * @version     SVN: $Id$
 */
class Survey_Answer_YesNo extends Evaluation_Answer_YesNo {

	/* Always use Correct css class
	 */
	protected $incorrectClass = 'multiCorrect';

	protected $graphTemplateFile = 'survey/answer/yesno/graph.html';

	/* Use this to toggle between letters and actual answers
	 */
	protected $useLongAnswers = true;

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
	 * @inheritdoc
	 */
	public function configure(SitePage $page, $config) {
		$options = json_decode($config);

		$page->setVar(array(
			'CONFIGURATION_MANDATORY_CHECKED' => $options->mandatory ? 'checked="checked"' : ''
		));
	}

	/**
	 * Configure mandatoryness and other settings for this response
	 *
	 * @param $page
	 * @param $question_id
	 */
	protected function configureResponse($page, $question_id) {
		$question = $this->questionHandler->getByID($question_id);
		$config = json_decode($question['config']);

		$page->setVar(array(
			'CONFIGURATION_MANDATORY' => $config->mandatory ? 'required' : '',
		));
	}
}

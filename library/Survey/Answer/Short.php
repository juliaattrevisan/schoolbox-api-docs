<?php
/**
 * Survey_Answer_Short
 *
 * Handler for Short Answer questions.
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   2010 Alaress Pty Ltd
 * @link        http://www.alaress.com.au
 * @version     SVN: $Id$
 */
class Survey_Answer_Short extends Evaluation_Answer_Short
{
    protected $graphTemplateFile = 'survey/answer/short/graph.html';

    /* Use this to toggle between letters and actual answers
      */
    protected $useLongAnswers = true;

	/**
	 * Setup
	 */
	protected function setup() {
		$this->setResponseHandler(new Survey_Response());
		$this->setQuestionHandler(new Survey_Question());
	}

    /**
     * Graph the results for this answer type
     *
     * @param  $page
     * @param  $currentQuestion
     * @return void
     */
    public function graphResults($page, $currentQuestion)
    {
        $componentHandle = 'temp' . $currentQuestion['question_id'];

        $page->setFile($componentHandle, $this->getGraphTemplateFile());

        $page->parse('graph_block', $componentHandle);
    }

    /**
     * Show the response someone submitted and also show the correct answer.
     * Provide an option for the teacher to review the mark and adjust.
     *
     * @param  $page
     * @param  $question_id
     * @return void
     */
    public function reviewResponse($page, $question_id)
    {
        $existing = $this->getResponseHandler()->isResponseExists($_SESSION['attempt_id'], $question_id);

        $currentAnswer = $this->getByQuestionID($question_id);

        /* Removed this check because it was wrongly matching a blank saved
           * response with no response at all, preventing reviewed marks from
           * displaying properly.
           *
           * if ($existing['response'] != null) { ... } else { ... }
           */
        $page->setVar(array(
            'ANSWER_ID' => $currentAnswer['answer_id'],
            'ANSWER' => Scrub::text($existing['response']),
            'RESPONSE_ID' => $existing['response_id'],
        ));
    }

	/**
	 * Configure question/configure answer
	 *
	 * @param $page
	 * @param $config
	 */
	public function configure($page, $config) {
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

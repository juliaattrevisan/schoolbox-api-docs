<?php
/**
 * Survey_Answer_Radio
 *
 * Handler for Multiple Choice questions.
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   2010 Alaress Pty Ltd
 * @link        http://www.alaress.com.au
 * @version     SVN: $Id$
 */
class Survey_Answer_Radio extends Evaluation_Answer_Radio
{

    /* Always use Correct css class
      */
    protected $incorrectClass = 'multiCorrect';

    protected $graphTemplateFile = 'survey/answer/radio/graph.html';

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
        $graph = new Survey_Graph_Render($page, $currentQuestion, $this->getGraphTemplateFile());
        $graph->graph();
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

        if ($existing) {
            $existingAnswer = unserialize($existing['response']);
        }

        $answerGroup = $this->getByQuestionID($question_id);

        if ($answerGroup) {
            $allAnswers = unserialize($answerGroup['answer']);

            $page->setVar(array(
                'ANSWER_ID' => $answerGroup['answer_id'],
                'RESPONSE_ID' => $existing['response_id'],
                'REVIEWED_COMMENT' => Scrub::text($existing['comment']),
                'REVIEWED_MARKS' => $existing['mark'],
            ));

            foreach ($allAnswers as $currentAnswer) {
                if ($currentAnswer->correct) {
                    $page->setVar(array(
                        'ACTUAL_CORRECT_VALUE' => 'correct-answer'
                    ));
                } else {
                    $page->setVar(array(
                        'ACTUAL_CORRECT_VALUE' => ''
                    ));
                }

                $page->setVar(array(
                    'ANSWER_VALUE' => $currentAnswer->value,
                    'ANSWER_DESCRIPTION' => Scrub::text($currentAnswer->description),
                ));

                if ($existing['response']) {
                    if (is_array($existingAnswer)
                        && in_array($currentAnswer->value, $existingAnswer)
                    ) {
                        $page->setVar(array(
                            'ANSWER_CHECKED' => 'evaluation-selected-answer-option',
                        ));

                        /* Was this a correctly-selected response? If so apply a
                         * special class to it. The three cases are:
                         *
                         * Correct response, selected
                         * Incorrect response, selected
                         * Unselected
                         */
                        if ($currentAnswer->correct && in_array($currentAnswer->value, $existingAnswer)) {
                            $page->setVar(array(
                                'CORRECT' => $this->getCorrectClass(),
                            ));
                        } else if (in_array($currentAnswer->value, $existingAnswer)) {
                            $page->setVar(array(
                                'CORRECT' => $this->getIncorrectClass(),
                            ));
                        } else {
                            $page->setVar(array(
                                'CORRECT' => ''
                            ));
                        }
                    } else {
                        $page->setVar(array(
                            'ANSWER_CHECKED' => '',
                            'CORRECT' => ''
                        ));
                    }
                } else {
                    $page->setVar(array(
                        'CORRECT' => ''
                    ));
                }

                $page->parse('answerOption_block', 'answerOption', true);
            }
        } else {
            $page->setVar('answerOption_block', '');
        }
    }

	/**
	 * Configure question/configure answer during creation/modification
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

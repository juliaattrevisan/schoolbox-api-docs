<?php

/**
 * Survey_Answer_Radio
 *
 * Handler for Multiple Choice questions.
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */
class Survey_Answer_Radio extends Evaluation_Answer_Radio
{

    /**
     * Always use Correct css class
     *
     * @var string
     */
    protected $incorrectClass = 'multiCorrect';


    /**
     * @var string
     */
    protected $graphTemplateFile = 'survey/answer/radio/graph.html';


    /**
     * Use this to toggle between letters and actual answers
     *
     * @var bool
     */
    protected $useLongAnswers = true;


    /**
     * Graph the results for this answer type
     *
     * @param SitePage $page
     * @param array $currentQuestion
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
     * @param SitePage $page
     * @param int $attemptId
     * @param int $question_id
     * @return void
     */
    public function reviewResponse($page, $attemptId, $question_id)
    {
        $existing = $this->getResponseHandler()->isResponseExists($attemptId, $question_id);

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

                if (
                    $existing['response']
                    && is_array($existingAnswer)
                    && in_array($currentAnswer->value, $existingAnswer)
                ) {
                    $page->setVar(array(
                        'ANSWER_OPTION_STATUS' => 'evaluation-selected-answer-option',
                        'ANSWER_CHECKED' => 'checked',
                    ));
                } else {
                    $page->setVar(array(
                        'ANSWER_OPTION_STATUS' => '',
                        'ANSWER_CHECKED' => '',
                    ));
                }

                $page->parse('answerOption_block', 'answerOption', true);
            }
        } else {
            $page->setVar('answerOption_block', '');
        }
    }


    /**
     * @inheritdoc
     */
    public function configure(SitePage $page, $config)
    {
        $options = json_decode($config);

        $page->setVar(array(
            'CONFIGURATION_MANDATORY_CHECKED' => $options->mandatory ? 'checked="checked"' : ''
        ));
    }


    /**
     * Configure mandatoryness and other settings for this response
     *
     * @param SitePage $page
     * @param int $question_id
     */
    protected function configureResponse($page, $question_id)
    {
        $question = $this->questionHandler->getByID($question_id);
        $config = json_decode($question['config']);

        $page->setVar(array(
            'CONFIGURATION_MANDATORY' => $config->mandatory ? 'required' : '',
        ));
    }
}

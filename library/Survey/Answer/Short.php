<?php

/**
 * Survey_Answer_Short
 *
 * Handler for Short Answer questions.
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */
class Survey_Answer_Short extends Evaluation_Answer_Short
{

    /**
     * @var string
     */
    protected $graphTemplateFile = 'survey/answer/short/graph.html';


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
     * @param int $currentQuestion
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
     * @param SitePage $page
     * @param int $attemptId
     * @param int $question_id
     * @return void
     */
    public function reviewResponse($page, $attemptId, $question_id)
    {
        $existing = $this->getResponseHandler()->isResponseExists($attemptId, $question_id);

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

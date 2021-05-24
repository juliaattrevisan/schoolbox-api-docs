<?php

/**
 * Survey_Answer_Multi
 *
 * Handler for Multiple Choice w/ Other questions.
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */
class Survey_Answer_MultiOther extends Evaluation_Answer_MultiOther
{

    /**
     * @var string
     */
    protected $graphTemplateFile = 'survey/answer/multi/graph.html';


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

        /* The last element in the stack corresponds to "Other"
         * Its 'text' key is empty, and the Survey_Graph_Render class
         * interprets empty string as 'Unanswered'
         *
         * Put in a unique customisation to fill that text field
         * so it reads as "Other"
         */
        $index = count($currentQuestion['summary']);

        if (!strlen($currentQuestion['summary'][$index]['text'])) {
            $currentQuestion['summary'][$index]['text'] = 'Other';
        }

        $graph = new Survey_Graph_Render($page, $currentQuestion, $this->getGraphTemplateFile());
        $graph->graph();
    }


    /**
     * Get the css class to use for incorrect responses
     * Always use the correctClass for Surveys
     *
     * @return string
     */
    protected function getIncorrectClass()
    {
        return $this->correctClass;
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

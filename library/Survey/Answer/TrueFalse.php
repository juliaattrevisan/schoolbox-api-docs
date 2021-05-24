<?php

/**
 * Survey_Answer_TrueFalse
 *
 * Handler for rating questions.
 *
 * @author      James Leckie <jleckie@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */
class Survey_Answer_TrueFalse extends Evaluation_Answer_TrueFalse
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
    protected $graphTemplateFile = 'survey/answer/truefalse/graph.html';


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

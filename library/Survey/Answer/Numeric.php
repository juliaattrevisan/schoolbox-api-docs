<?php
/**
 * Survey_Answer_Numeric
 *
 * Handler for Short Answer questions.
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   2010 Alaress Pty Ltd
 * @link        http://www.alaress.com.au
 * @version     SVN: $Id$
 */
class Survey_Answer_Numeric extends Evaluation_Answer_Numeric
{
    protected $graphTemplateFile = 'survey/answer/numeric/graph.html';

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
    public function reviewResponse($page, $question_id) {
        $existing = $this->responseHandler->isResponseExists($_SESSION['attempt_id'], $question_id);

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
}

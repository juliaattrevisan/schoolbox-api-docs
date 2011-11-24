<?php
/**
 * AssessAnswerShort
 *
 * Handler for Short Answer questions.
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   2010 Alaress Pty Ltd
 * @link        http://www.alaress.com.au
 * @version     SVN: $Id$
 */
class AssessAnswerShort extends AnswerShort {

	/**
	 * Get an instance of a responseHandler upon instantiation
	 */
    public function __construct() {
        parent::__construct();

        $this->responseHandler = new AssessResponse();
    }

    /**
     * Automatically marks a response. Customised to be applicable to the way in
     * which the response is actually stored.
     */
    public function mark() {
    }

    /**
     * Show the response someone submitted and also show the correct answer.
     * Provide an option for the teacher to review the mark and adjust.
     *
     * @param <type> $page
     * @param <type> $question_id
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
            'ANSWER' => $existing['response'],
            'RESPONSE_ID' => $existing['response_id'],
            'REVIEWED_COMMENT' => $existing['comment'],
            'REVIEWED_MARKS' => $existing['mark'],
        ));

        /* Show correct answer
         */
        $page->setVar(array(
            'CORRECT_ANSWER' => $currentAnswer['answer']
        ));

        $page->parse('answerOption_block', 'answerOption', true);
    }
}

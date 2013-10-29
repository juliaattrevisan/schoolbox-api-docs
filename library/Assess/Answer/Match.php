<?php
/**
 * Assess_Answer_Match
 *
 * Handler for Matching Pairs questions.
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   2010 Alaress Pty Ltd
 * @link        http://www.alaress.com.au
 * @version     SVN: $Id$
 */
class Assess_Answer_Match extends Evaluation_Answer_Match
{

    /**
     * Get an instance of a responseHandler upon instantiation
     */
    public function __construct()
    {
        parent::__construct();

        $this->responseHandler = new Assess_Response();
    }

    /**
     * Automatically marks a response. Customised to be applicable to the way in
     * which the response is actually stored.
     *
     * @param  $currentResponse
     * @return void
     */
    public function mark($currentResponse)
    {
        $response = unserialize($currentResponse['response']);
        $answer = unserialize($currentResponse['answer']);

        $marksPerAnswer = $currentResponse['marks'] / count($answer);

        /* Find out how much each correct answer is worth
           */
        for ($i = 0, $numCorrect = 0, $totalMarks = 0; $i < count($answer); $i++) {
            if ($answer[$i]->answerRight == $response[$i]) {
                $totalMarks += $marksPerAnswer;
                $numCorrect++;
            }
        }

        /* Total marks for this question
           */
        $marks = array(
            'response_id' => $currentResponse['response_id'],
            'mark' => $totalMarks,
        );

        $this->responseHandler->update($marks);
    }
}

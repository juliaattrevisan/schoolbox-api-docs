<?php
/**
 * Assess_Answer_Fillgap
 *
 * Handler for Fill Gap questions.
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   2010 Alaress Pty Ltd
 * @link        http://www.alaress.com.au
 * @version     SVN: $Id$
 */
class Assess_Answer_Fillgap extends Evaluation_Answer_Fillgap
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
        $answer = $currentResponse['answer'];

        /* Pattern match spans to find answers
           */
        $matches = array();
        $pattern = '#<span class="selected-word">([^<]+)</span>#ms';

        preg_match_all($pattern, $answer, $matches);

        $actualAnswers = $matches[1];

        /* Find out how much each phrase is worth
           * If the number of Fillgap answers is 0 (ie. no blank for students to fill), skip it.
           * TODO: It should be noted that a teacher should be prevented from creating a cloze question that doesn't have any blanks in it...
           */
        if (count($actualAnswers) != 0) {
            $marksPerAnswer = $currentResponse['marks'] / count($actualAnswers);

            for ($i = 0, $totalMarks = 0; $i < count($actualAnswers); $i++) {
                if (strtolower(trim($actualAnswers[$i])) == strtolower(trim($response[$i]))) {
                    $totalMarks += $marksPerAnswer;
                }
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

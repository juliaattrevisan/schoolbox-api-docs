<?php

use Assess\IAutomarkable;

/**
 * Assess_Answer_Match
 *
 * Handler for Matching Pairs questions.
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */
class Assess_Answer_Match extends Evaluation_Answer_Match implements IAutomarkable
{
    /**
     * Automatically marks a response. Customised to be applicable to the way in
     * which the response is actually stored.
     *
     * @param  array $currentResponse
     * @return void
     */
    public function mark($currentResponse): void
    {
        $response = unserialize($currentResponse['response']);
        $answer = unserialize($currentResponse['answer']);

        $marksPerAnswer = $currentResponse['marks'] / count($answer);

        /* Find out how much each correct answer is worth
           */
        for ($i = 0, $numCorrect = 0, $totalMarks = 0; $i < count($answer); $i++) {
            if (!$response || !isset($response[$i])) {
                continue;
            }
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

        $this->getResponseHandler()->update($marks);
    }
}

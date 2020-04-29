<?php

use Assess\IAutomarkable;

/**
 * Assess_Answer_Exact
 *
 * Handler for Exact questions.
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */
class Assess_Answer_Exact extends Evaluation_Answer_Exact implements IAutomarkable
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
        // Normalize both response and answer for comparison
        if ($this->compareStrings($currentResponse['response'], $currentResponse['answer'])) {
            $totalMarks = $currentResponse['marks'];
        } else {
            $totalMarks = 0;
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

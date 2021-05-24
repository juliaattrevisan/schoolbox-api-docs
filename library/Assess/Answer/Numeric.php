<?php

use Assess\IAutomarkable;

/**
 * Assess_Answer_Numeric
 *
 * Handler for Numeric questions.
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */
class Assess_Answer_Numeric extends Evaluation_Answer_Numeric implements IAutomarkable
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
        if ((float)$currentResponse['answer'] == (float)$currentResponse['response']) {
            $total = $currentResponse['marks'];
        } else {
            $total = 0;
        }

        /* Total marks for this question
         */
        $marks = array(
            'response_id' => $currentResponse['response_id'],
            'mark' => $total,
        );

        $this->getResponseHandler()->update($marks);
    }
}

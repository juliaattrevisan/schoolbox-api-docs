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
        $total = 0;

        /* Special case: someone didn't provide an answer at all
         * They don't get the marks for this question
         *
         * Someone can only get the marks if they provided a response and
         * the response was numerically equivalent to the expected answer
         */
        if ((float)$currentResponse['answer'] === (float)$currentResponse['response'] && ('' !== trim((string)$currentResponse['response']))) {
            $total = $currentResponse['marks'];
        }

        //fix mark to not overflow
        if (9999999999999.99 <= $total) {
            $total = '9999999999999.99';
        }

        /* Total marks for this question
         */
        $marks = [
            'response_id' => $currentResponse['response_id'],
            'mark' => $total,
        ];

        $this->getResponseHandler()->update($marks);
    }
}

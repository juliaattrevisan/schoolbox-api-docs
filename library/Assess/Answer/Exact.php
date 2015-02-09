<?php
/**
 * Assess_Answer_Exact
 *
 * Handler for Exact questions.
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   2010 Alaress Pty Ltd
 * @link        http://www.alaress.com.au
 * @version     SVN: $Id$
 */
class Assess_Answer_Exact extends Evaluation_Answer_Exact
{

    /**
    * Setup
    */
    protected function setup()
    {
        $this->setResponseHandler(new Assess_Response());
    }

    /**
    * Normalizes response and answer for comparison - replaces all spaces,
    * and converts to lower case.
    *
    * @param string $response
    *
    * @return string
    */
    protected function normalizeForComparison($response)
    {
        return mb_strtolower(trim(preg_replace('/\\s+/', ' ', $response)), 'UTF-8');
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
        // Normalize both response and answer for comparison
        if (
            $this->normalizeForComparison($currentResponse['response'])
            === $this->normalizeForComparison($currentResponse['answer'])
        ) {
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

<?php
/**
 * Assess_Answer_Radio
 *
 * Handler for Multiple Choice questions with only a single answer.
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   2010 Alaress Pty Ltd
 * @link        http://www.alaress.com.au
 * @version     SVN: $Id$
 */
class Assess_Answer_Radio extends Assess_Answer_Multi
{
    protected $plugin = 'evaluationRadio';

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

        /* Find out how much each correct answer is worth
         *
         * i: the actual number of correct responses for the question
         * numCorrect: the number of correct responses submitted
         */
        $i = 0;
        $numCorrect = 0;

        if (is_array($answer)) {
            foreach ($answer as $currentAnswer) {
                if ($currentAnswer->correct) {
                    /* If the user responded with a correct answer, accumulate
                     */
                    if ($currentAnswer->value == $response) {
                        $numCorrect++;
                    }

                    $i++;
                }
            }
        }

        /* If the student selects exactly the correct responses then give them
         * the marks, otherwise penalise them. The teacher can review and
         * adjust the mark accordingly
         */
        if ($numCorrect == count($response)) {
            if ($i) {
                $marksPerAnswer = $currentResponse['marks'] / $i;
                $total = $marksPerAnswer * $numCorrect;
            }
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

    /**
     * Overridden as $existingResponse is a single answer, instead of an array
     * of answers.
     *
     * @param string $answerValue
     * @param string $existingResponse The response that the student gave for the question
     *
     * @return bool
     */
    protected function isAnswerSelected($answerValue, $existingResponse)
    {
        return $answerValue == $existingResponse;
    }

    /**
     * Overrides base implementation - answer is stored as a single value, not
     * an array, so wrap it in an array.
     *
     * @param  string $answer
     * @return array
     */
    protected function getAnswersFromResponse($answer)
    {
        return [unserialize($answer)];
    }
}

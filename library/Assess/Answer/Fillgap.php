<?php

use Assess\IAutomarkable;

/**
 * Assess_Answer_Fillgap
 *
 * Handler for Fill Gap questions.
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */
class Assess_Answer_Fillgap extends Evaluation_Answer_Fillgap implements IAutomarkable
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
        $answer = html_entity_decode($currentResponse['answer'], ENT_IGNORE | ENT_QUOTES);

        /* Pattern match spans to find answers
         */
        $matches = [];
        $pattern = self::FILLGAP_TOKEN_REGEX;

        preg_match_all($pattern, $answer, $matches);

        $actualAnswers = $matches[1];

        /* Find out how much each phrase is worth
         * If the number of Fillgap answers is 0 (ie. no blank for students to fill), skip it.
         * TODO: It should be noted that a teacher should be prevented from creating a cloze question that doesn't have any blanks in it...
         */
        $actualAnswerCount = count($actualAnswers);
        $totalMarks        = null;
        if ($actualAnswerCount > 0) {
            $totalMarks     = 0;
            $marksPerAnswer = $currentResponse['marks'] / $actualAnswerCount;
            foreach ($actualAnswers as $i => $actualAnswer) {
                $givenAnswer = $response[$i] ?? '';

                // Collate and compare
                if ($this->compareStrings($actualAnswer, $givenAnswer)) {
                    $totalMarks += $marksPerAnswer;
                }
            }
        }

        //fix mark to not overflow
        if (9999999999999.99 <= $totalMarks) {
            $totalMarks = '9999999999999.99';
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

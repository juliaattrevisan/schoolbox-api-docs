<?php

/**
 * @copyright   Copyright 2023, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Learning\Learnosity\Importer\Strategy;

/**
 * Converts a Schoolbox "Exact Answer" question into its Learnosity equivalent
 * https://reference.learnosity.com/questions-api/questiontypes#shorttext
 *
 * Circumstantially almost identical to Short, main difference being the strictness
 * of its marking
 */
class Exact implements IStrategy
{
    /**
     * Corresponds to a row in our assess_luQuestionType lookup
     */
    public const QUESTION_TYPE_ID = 15;


    /**
     * @inheritDoc
     */
    public function getLearnosityEquivalent(): string
    {
        return 'shorttext';
    }


    /**
     * @inheritDoc
     */
    public function convert(array $question): array
    {
        /* FIXME This should always exist but what happens if it somehow doesn't?
         *  Is that even a possibility in the fleet atm
         */
        $answer = $question['_answers'][0]['answer'];

        return [
            'case_sensitive' => false,
            'instant_feedback' => false,
            'max_length' => 250,
            'stimulus' => $question['question'],
            'type' => $this->getLearnosityEquivalent(),
            'validation' => [
                'automarkable' => true,
                'scoring_type' => 'exactMatch',
                'valid_response' => [
                    'value' =>  $answer,
                    'score' => (float)$question['marks']
                ]
            ]
        ];
    }
}

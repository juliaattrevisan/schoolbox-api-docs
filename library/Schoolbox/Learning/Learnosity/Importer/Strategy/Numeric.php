<?php

/**
 * @copyright   Copyright 2023, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Learning\Learnosity\Importer\Strategy;

/**
 * Converts a Schoolbox "Numeric" question into its Learnosity equivalent
 * https://reference.learnosity.com/questions-api/questiontypes#shorttext
 *
 * There isn't really a good allegory in Learnosity afaict for what we
 * previously allowed. It was only circumstantially limited to a certain
 * set of characters and built on Short Answer. If you were to do it in
 * Learnosity, you'd use one of the Math Formula question types, not a
 * weird variant of Short Answer.
 */
class Numeric implements IStrategy
{
    /**
     * Corresponds to a row in our assess_luQuestionType lookup
     */
    public const QUESTION_TYPE_ID = 13;


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
        $answer = trim($question['_answers'][0]['answer']);

        return [
            'case_sensitive' => false,
            'instant_feedback' => false,
            'max_length' => 250,
            'stimulus' => trim($question['question']),
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

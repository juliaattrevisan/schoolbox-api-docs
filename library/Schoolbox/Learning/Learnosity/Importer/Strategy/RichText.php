<?php

/**
 * @copyright   Copyright 2023, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Learning\Learnosity\Importer\Strategy;

/**
 * Converts a Schoolbox "Rich Text" question into its Learnosity equivalent
 * https://reference.learnosity.com/questions-api/questiontypes#longtextV2
 */
class RichText implements IStrategy
{
    /**
     * Corresponds to a row in our assess_luQuestionType lookup
     */
    public const QUESTION_TYPE_ID = 14;


    /**
     * @inheritDoc
     */
    public function getLearnosityEquivalent(): string
    {
        return 'longtextV2';
    }


    /**
     * @inheritDoc
     */
    public function convert(array $question): array
    {
        return [
            'max_length' => 99999,
            'stimulus' => $question['question'],
            'type' => $this->getLearnosityEquivalent(),
            'validation' => [
                'max_score' => (float)$question['marks']
            ]
        ];
    }
}

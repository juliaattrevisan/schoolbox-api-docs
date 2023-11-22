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
class RichText extends Base implements IStrategy
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
            /* Apparently the upper limit is 100,000 chars which
             * somehow equates to 10,000 words
             *
             * https://reference.learnosity.com/questions-api/questiontypes#longtextV2
             * "Maximum number of words that can be entered in the field. Maximum: 100,000 chars ~ 10,000 words"
             */
            'max_length' => 10000,
            'stimulus' => $this->produceQuestionText($question['question']),
            'type' => $this->getLearnosityEquivalent(),
            'validation' => [
                'max_score' => (float)$question['marks']
            ]
        ];
    }
}

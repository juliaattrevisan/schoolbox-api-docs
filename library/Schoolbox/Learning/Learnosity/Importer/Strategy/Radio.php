<?php

/**
 * @copyright   Copyright 2023, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Learning\Learnosity\Importer\Strategy;

/**
 * Converts a Schoolbox "Multiple Choice (Single Answer)" question into its Learnosity equivalent
 * https://reference.learnosity.com/questions-api/questiontypes#mcq
 */
class Radio implements IStrategy
{
    /**
     * Corresponds to a row in our assess_luQuestionType lookup
     */
    public const QUESTION_TYPE_ID = 5;


    /**
     * @inheritDoc
     */
    public function getLearnosityEquivalent(): string
    {
        return 'mcq';
    }


    /**
     * @inheritDoc
     */
    public function convert(array $question): array
    {
        /* FIXME This should always exist but what happens if it somehow doesn't?
         *  Is that even a possibility in the fleet atm
         */
        $possibleAnswers = $question['_answers'][0]['answer'];

        $options = [];
        $correct = [];

        /* Note that $possibleAnswers deserialized into stdClass instances
         */
        foreach ($possibleAnswers as $possibleAnswer) {
            $options[] = [
                'label' => trim($possibleAnswer->description),
                'value' => trim($possibleAnswer->value),
            ];

            /* Remember the correct answer(s)
             */
            if ($possibleAnswer->correct) {
                $correct[] = trim($possibleAnswer->value);
            }
        }

        /* Randomise option order
         * This was a configuration setting in legacy quiz
         */
        $shuffleOptions = (bool)($question['config']['randomiseOptionOrder'] ?? false);

        return [
            'options' => $options,
            'shuffle_options' => $shuffleOptions,
            'stimulus' => trim($question['question']),
            'type' => $this->getLearnosityEquivalent(),
            'ui_style' => [
                /* An undocumented setting as per https://help.learnosity.com/hc/en-us/requests/23242
                 * [number, upper-alpha, lower-alpha] are possibilities
                 */
                'choice_label' => 'lower-alpha',
                'type' => 'block'
            ],
            'validation' => [
                'automarkable' => true,
                'scoring_type' => 'exactMatch',
                'valid_response' => [
                    'value' => $correct
                ]
            ]
        ];
    }
}

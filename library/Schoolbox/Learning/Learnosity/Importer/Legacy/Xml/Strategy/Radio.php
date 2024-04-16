<?php

/**
 * @copyright   Copyright 2024, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Learning\Learnosity\Importer\Legacy\Xml\Strategy;

/**
 * Converts a Schoolbox "Multiple Choice (Single Answer)" question into its Learnosity equivalent
 * https://reference.learnosity.com/questions-api/questiontypes#mcq
 */
class Radio extends Base implements IStrategy
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
        $possibleAnswers = $question['_answers'] ?? [];

        $options = [];
        $correct = [];

        /* Note that $possibleAnswers deserialized into stdClass instances
         */
        foreach ($possibleAnswers as $possibleAnswer) {
            $description = $possibleAnswer['label'];
            $value = $possibleAnswer['value'];

            /* Skip descriptions/values that are meaningless
             * These weren't intended, but somehow exist in prod data
             * They don't convey anything to users
             *
             * But what happens when this brokenness is the only option available?
             */
            if (('' === $description) || ('' === $value)) {
                continue;
            }

            $options[] = [
                'label' => $description,
                'value' => $value,
            ];

            /* Remember the correct answer(s)
             */
            if ($possibleAnswer['correct'] ?? false) {
                $correct[] = $value;
            }
        }

        /* Randomise option order
         * This was a configuration setting in legacy quiz
         */
        $shuffleOptions = (bool)($question['config']['randomiseOptionOrder'] ?? false);

        return [
            'options' => $options,
            'shuffle_options' => $shuffleOptions,
            'stimulus' => $this->produceQuestionText($question['question']),
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


    /**
     * Decompiles $answer's xml value
     *
     * @param \SimpleXMLElement $answers
     * @return array
     * @throws \RuntimeException
     */
    public function decompileAnswer(\SimpleXMLElement $answers): array
    {
        $options = [];

        foreach ($answers[0]->xpath('answer') as $answer) {
            $description = trim((string)$answer->xpath('text')[0]);
            $value = trim((string)$answer->xpath('value')[0]);

            $options[] = [
                'label' => $description,
                'value' => $value,
                /* 100 denotes a correct answer; it'll be 0 for wrong answers
                 */
                'correct' => 100 === (int)trim((string)$answer->xpath('@fraction')[0])
            ];
        }

        /* Randomise option order
         * This was a configuration setting in legacy quiz
         */
        return [
            'answers' => $options,
            /* Sometimes config options are found here, and need to be move dup
             */
            'config' => [
                'randomiseOptionOrder' => (int)($answers->xpath('shuffleanswers')[0])
            ]
        ];
    }
}

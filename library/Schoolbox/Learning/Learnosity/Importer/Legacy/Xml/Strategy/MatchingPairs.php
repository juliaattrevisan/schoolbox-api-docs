<?php

/**
 * @copyright   Copyright 2024, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Learning\Learnosity\Importer\Legacy\Xml\Strategy;

/**
 * Converts a Schoolbox "Matching Pairs" question into its Learnosity equivalent
 * https://reference.learnosity.com/questions-api/questiontypes#association
 */
class MatchingPairs extends Base implements IStrategy
{
    /**
     * Corresponds to a row in our assess_luQuestionType lookup
     */
    public const QUESTION_TYPE_ID = 4;


    /**
     * @inheritDoc
     */
    public function getLearnosityEquivalent(): string
    {
        return 'association';
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

        /* Compile into a learnosity-friendly struct
         */
        $stimulusList = [];
        $correctAnswers = [];

        foreach ($possibleAnswers as $possibleAnswer) {
            $left = $possibleAnswer['label'] ?? '';
            $right = $possibleAnswer['value'] ?? '';

            /* Skip left/right combinations that are meaningless
             * These weren't intended, but somehow exist in prod data
             * They don't convey anything to users
             *
             * But what happens when this brokenness is the only option available?
             */
            if (('' === $left) || ('' === $right)) {
                continue;
            }

            $stimulusList[] = $left;
            $correctAnswers[] = $right;
        }

        /* Randomise whatever this was
         */
        $possibleAnswers = $correctAnswers;
        shuffle($possibleAnswers);

        return [
            'instant_feedback' => false,
            'possible_responses' => $possibleAnswers,
            'stimulus' => $this->produceQuestionText($question['question']),
            'stimulus_list' => $stimulusList,
            'type' => $this->getLearnosityEquivalent(),
            'validation' => [
                'automarkable' => true,
                'scoring_type' => 'exactMatch',
                'valid_response' => [
                    'value' =>  $correctAnswers,
                    'score' => (float)$question['marks']
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

        foreach ($answers[0]->xpath('subquestion') as $answer) {
            $description = trim((string)$answer->xpath('text')[0]);
            $value = trim((string)$answer->xpath('answer')[0]);

            $options[] = [
                'label' => $description,
                'value' => $value,
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

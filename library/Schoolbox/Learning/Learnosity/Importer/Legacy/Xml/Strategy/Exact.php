<?php

/**
 * @copyright   Copyright 2024, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Learning\Learnosity\Importer\Legacy\Xml\Strategy;

/**
 * Converts a Schoolbox "Exact Answer" question into its Learnosity equivalent
 * https://reference.learnosity.com/questions-api/questiontypes#shorttext
 *
 * Circumstantially almost identical to Short, main difference being the strictness
 * of its marking
 */
class Exact extends Base implements IStrategy
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
        $answer = trim($question['_answers']);

        return [
            'case_sensitive' => false,
            'instant_feedback' => false,
            'max_length' => 250,
            'stimulus' => $this->produceQuestionText($question['question']),
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


    /**
     * Decompiles $answer's xml value
     *
     * @param \SimpleXMLElement $answers
     * @return array
     * @throws \RuntimeException
     */
    public function decompileAnswer(\SimpleXMLElement $answers): array
    {
        return [
            'answers' => trim((string)$answers[0]->xpath('answer')[0]->xpath('text')[0]),
            'config' => []
        ];
    }
}

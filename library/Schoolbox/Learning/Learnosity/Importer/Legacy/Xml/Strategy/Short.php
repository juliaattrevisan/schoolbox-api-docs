<?php

/**
 * @copyright   Copyright 2024, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Learning\Learnosity\Importer\Legacy\Xml\Strategy;

/**
 * Converts a Schoolbox "Short Answer" question into its Learnosity equivalent
 * https://reference.learnosity.com/questions-api/questiontypes#shorttext
 */
class Short extends Base implements IStrategy
{
    /**
     * Corresponds to a row in our assess_luQuestionType lookup
     */
    public const QUESTION_TYPE_ID = 1;


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
        /* the question can exist, but the answer can be deleted...
         * as found on baqags (prod), so just cast to empty string and still import the question
         */
        $answer = trim($question['_answers'][0]['answer'] ?? '');

        return [
            'case_sensitive' => false,
            'instant_feedback' => false,
            'max_length' => 250,
            /* Correct Answer doesn't show in the ui unless automarkable:true
             * You can perhaps put it in here as an alternative
             */
            'metadata' => [
                'sample_answer' => $answer
            ],
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
     * @param array $answers
     * @return array
     * @throws \RuntimeException
     */
    public function decompileAnswer(array $answers): array
    {

    }
}

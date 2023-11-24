<?php

/**
 * @copyright   Copyright 2023, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Learning\Learnosity\Importer\Strategy;

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
        $possibleAnswers = $question['_answers'][0]['answer'];

        /* Compile into a learnosity-friendly struct
         */
        $stimulusList = [];
        $correctAnswers = [];

        foreach ($possibleAnswers as $possibleAnswer) {
            $stimulusList[] = trim($possibleAnswer->answerLeft);
            $correctAnswers[] = trim($possibleAnswer->answerRight);
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
     * @inheritDoc
     * @param array $answer
     * @return mixed
     * @throws \RuntimeException
     */
    public function decompileAnswer(array $answer): mixed
    {
        return $this->decompileSerializedAnswer($answer);
    }
}

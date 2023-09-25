<?php

/**
 * @copyright   Copyright 2023, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Learning\Learnosity\Importer\Strategy;

/**
 * Converts a Schoolbox "Fillgap (Cloze Exercise)" question into its Learnosity equivalent
 * https://reference.learnosity.com/questions-api/questiontypes#clozetext
 */
class Fillgap implements IStrategy
{
    /**
     * Corresponds to a row in our assess_luQuestionType lookup
     */
    public const QUESTION_TYPE_ID = 3;


    /**
     * @inheritDoc
     */
    public function getLearnosityEquivalent(): string
    {
        return 'clozetext';
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

        /* We used <span class=selected-word></span> elements to denote our correct
         * answers, whereas Learnosity used {{response}}. Our implementation was almost
         * identical to theirs, but Learnosity relied on sequence.
         */
        preg_match_all('/<span\ class="selected-word">(.*)<\/span>/mi', $answer, $matches);

        /* Convert each $matches[0] to {{response}} for learnosity compatibility
         */
        foreach ($matches[0] as $oldPlaceholder) {
            $answer = str_replace($oldPlaceholder, '{{response}}', $answer);
        }

        /* Stack up the options
         */
        $correct = $matches[1];

        return [
            'case_sensitive' => false,
            'instant_feedback' => false,
            'max_length' => 250,
            'stimulus' => trim($question['question']),
            'template' => $answer,
            'type' => $this->getLearnosityEquivalent(),
            'validation' => [
                'automarkable' => true,
                /* FIXME Maybe should be "partialMatch" depending on leniency (controversial?)
                 */
                'scoring_type' => 'exactMatch',
                'valid_response' => [
                    'value' =>  $correct,
                    'score' => (float)$question['marks']
                ]
            ]
        ];
    }
}

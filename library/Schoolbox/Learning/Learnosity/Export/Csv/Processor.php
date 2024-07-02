<?php

/**
 * @copyright   Copyright 2024, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Learning\Learnosity\Export\Csv;

use DebugBar\DebugBarException;
use LearnositySdk\Exceptions\ValidationException;
use Schoolbox\Learning\Assessment\Quiz\Model as QuizDomainObject;
use Schoolbox\Learning\Learnosity\Api;
use Schoolbox\Learning\Learnosity\ApiException;
use Schoolbox\Learning\Learnosity\QuizFetcher;
use Schoolbox\Learning\Quiz\Attempt\Mapper;

class Processor
{
    /**
     * These always appear in the csv
     */
    private const MANDATORY_COLUMNS = [
        'External ID',
        'User ID',
        'Last Name',
        'First Name',
        'Start',
        'Finish',
    ];


    /**
     * Constructor.
     *
     * @param Api $api
     * @param QuizFetcher $quizFetcher
     * @param Mapper $attemptMapper
     */
    public function __construct(
        private Api $api,
        private QuizFetcher $quizFetcher,
        private \Schoolbox\Learning\Quiz\Attempt\Mapper $attemptMapper
    ) {
    }


    /**
     * @param QuizDomainObject $assessment
     * @return array
     * @throws DebugBarException
     * @throws \JsonException
     * @throws ValidationException
     * @throws \RuntimeException
     * @throws ApiException
     */
    public function export(QuizDomainObject $assessment): array
    {
        /* Known questions in the current incarnation of $assessment
         *
         * Note we don't support variations between attempts: we use the current quiz questions
         * as a structure around which to build the csv
         */
        $structure = $this->buildStructure($assessment);

        /* Find human-readable meanings for options. Multiple choice questions will have numeric values
         * to represent student responses, but we want to show the human-readable equivalent.
         */
        $structure = $this->buildMeanings($structure);

        /* Get a list of all the attempts that were made
         */
        $attempts = $this->getAttempts($assessment);

        /* Get the learnosity equivalent of our attempts
         */
        $learnositySessions = $this->getLearnositySessions($attempts);

        /* Translate meanings and make more succinct each learnosity session,
         * using $structure as a lookup where needed for mcq etc.
         */
        $sessions = $this->compileHumanReadable($learnositySessions, $structure);

        /* Start building rows
         */
        foreach ($sessions as $sessionReference => $responseData) {
            $row = [
                'external id',
                'user id',
                'lastname',
                'firstname',
                'start time',
                'finish time'
            ];

            foreach ($structure as $itemReference => $data) {
//                $session[$itemReference
            }
        }
        new \Alaress_Probe($structure, $sessions);
    }


    /**
     * Build an ordered list of items in the current incarnation of $assessment,
     * a learnosity quiz
     *
     * @param QuizDomainObject $assessment
     * @return array
     * @throws \JsonException
     * @throws \LearnositySdk\Exceptions\ValidationException
     * @throws \Schoolbox\Learning\Learnosity\ApiException
     */
    private function buildStructure(QuizDomainObject $assessment): array
    {
        /* Discover the current quiz question structure and build around that
         */
        $itemReferences = $this->api->dataGetRequest('/itembank/activities', [
            'references' => [
                $assessment->getLearnosityReference()
            ]
        ])['data'][0]['data']['items'] ?? [];

        $structure = [];

        /* Item references are in the reverse chronological order...wth
         */
        foreach ($itemReferences as $itemReference) {
            /* Due to learnosity behaviour https://help.learnosity.com/hc/en-us/requests/23258
             * we have to accommodate $itemReference being a string (lol) as well as an array (lol)
             */
            if (!is_array($itemReference)) {
                $structure[$itemReference] = null;
            } else {
                $structure[$itemReference['reference']] = null;
            }
        }

        return $structure;
    }


    /**
     * Cross reference values to human-readable equivalents
     * Only applies to some question types
     *
     * @param array $structure
     * @return array
     * @throws ApiException
     * @throws ValidationException
     * @throws \JsonException
     */
    private function buildMeanings(array $structure): array
    {
        /* Maintain same ordering
         */
        $meanings = array_fill_keys(array_keys($structure), null);

        /* Find some info about each item
         */
        $questions = $this->api->dataGetRequest('/itembank/questions', [
            'item_references' => array_keys($structure)
        ])['data'] ?? [];

        /* Augment the structure so we know what each response means.
         *
         * This has got to be the most roundabout way to find the human-readable equivalents
         * of what students responded with.
         */
        foreach ($questions as $itemReference => $data) {
            $meanings[$itemReference] = [
                'type' => $data[0]['data']['type'],
                'data' => null
            ];

            /* TODO Factory/Strategy this, as needed
             */
            if ('mcq' === $data[0]['data']['type']) {
                $lookup = [];

                foreach ($data[0]['data']['options'] as $option) {
                    $lookup[$option['value']] = $option['label'];
                }

                $meanings[$itemReference]['data'] = $lookup;
            }
        }

        return $meanings;
    }


    /**
     * Get a list of attempts for $assessment
     *
     * @param QuizDomainObject $assessment
     * @return \Schoolbox\GenericCollection
     * @throws \RuntimeException
     */
    private function getAttempts(QuizDomainObject $assessment): \Schoolbox\GenericCollection
    {
        /* Get the quiz equivalent
         */
        $quizId = $this->quizFetcher->findByAssessmentId($assessment->getId())->getId();

        /* Get a list of all the attempts that were made
         */
        return $this->attemptMapper->findByColumn('assess_id', $quizId)->indexBy('learnosityReference');
    }


    /**
     * Get the learnosity equivalent of our Attempts
     *
     * @param \Schoolbox\GenericCollection $attempts
     * @return array
     * @throws \JsonException
     * @throws \LearnositySdk\Exceptions\ValidationException
     * @throws \Schoolbox\Learning\Learnosity\ApiException
     */
    private function getLearnositySessions(\Schoolbox\GenericCollection $attempts): array
    {
        $learnosityReferences = $attempts->keys();

        /* Ask learnosity about the questions in this quiz
         */
        $results = $this->api->dataGetRequest('/sessions/responses', [
            'session_id' => $learnosityReferences
        ])['data'] ?? [];

        /* Index by learnosity reference, for a quick correlation
         */
        $learnositySessions = array_fill_keys($learnosityReferences, null);

        foreach ($results as $result) {
            $learnositySessions[$result['session_id']] = $result;
        }

        return $learnositySessions;
    }


    /**
     * Swap learnosity identifiers for human-readable equivalents
     * This approach seems like complete insanity to me, but whatever
     *
     * @param array $learnositySessions
     * @param array $structure
     * @return array
     */
    private function compileHumanReadable(array $learnositySessions, array $structure): array
    {
        $sessions = [];

        foreach ($learnositySessions as $sessionReference => $learnositySession) {
            foreach ($learnositySession['responses'] as $response) {
                /* TODO Factory/Strategy this, as needed
                 */
                if ('mcq' === $response['question_type']) {
                    $vals = [];

                    foreach ($response['response']['value'] as $val) {
                        $vals[] = $structure[$response['item_reference']]['data'][$val];
                    }

                    $sessions[$sessionReference][$response['item_reference']] = implode(', ', $vals);
                } elseif ('shorttext' === $response['question_type']) {
                    $sessions[$sessionReference][$response['item_reference']] = $response['response']['value'];
                }
            }
        }

        return $sessions;
    }
}

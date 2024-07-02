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

        /* Get a list of all the attempts that were made
         */
        $attempts = $this->getAttempts($assessment);

        /* Get the learnosity equivalent of our attempts
         */
        $learnositySessions = $this->getLearnositySessions($attempts);

        new \Alaress_Probe($structure, $learnositySessions);
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
            $structure[$itemReference] = [
                'type' => $data[0]['data']['type'],
                'data' => $data[0]['data']['options']
            ];
        }

        return $structure;
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
}

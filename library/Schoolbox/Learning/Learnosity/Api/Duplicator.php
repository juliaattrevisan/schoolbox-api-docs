<?php

/**
 * @copyright Copyright 2024, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Learning\Learnosity\Api;

use Alaress\Database\Connector\IConnector;
use LearnositySdk\Exceptions\ValidationException;
use Ramsey\Uuid\Uuid;
use Schoolbox\Learning\Assessment\Quiz\Model as QuizDomainModel;
use Schoolbox\Learning\Learnosity\Api;
use Schoolbox\Learning\Quiz\Model;

/**
 * Specialised command for duplicating quiz contents from one quiz to another,
 * using the Learnosity API.
 */
class Duplicator
{
    /**
     * Constructor
     *
     * @param Api        $api      Learnosity API
     * @param IConnector $database Database in which to write
     */
    public function __construct(private Api $api, private IConnector $database)
    {
    }

    /**
     * Copies Sections, Questions and Solutions from source Quiz to destination Quiz
     *
     * For now, this call will throw an exception if the destination Quiz already has Sections.
     *
     * @param QuizDomainModel $source
     * @param QuizDomainModel $destination
     * @return string|null The Learnosity job ID for duplicating activities
     * @throws \DomainException
     * @throws ValidationException
     * @throws \RuntimeException
     */
    public function copyQuizConstituents(QuizDomainModel $source, QuizDomainModel $destination): ?string
    {
        /* Clone a Learnosity Reference in Learnosity land
         */
        $oldLearnosityReference = $source->getLearnosityReference();

        /* See if there are any items in it;
         * Learnosity crashes when you try to clone an itemless quiz
         */
        $activity = $this->api->dataGetRequest(
            '/itembank/activities',
            [
                'references' => [$oldLearnosityReference]
            ],
        );

        /* Only try to clone activities with items;
         * skip duplicating activities without items for now
         * (You can still add items after this though)
         */
        if (\count($activity['data'])) {
            /* Get the new learnosity reference and return that
             *
             * TODO We could either Poll Learnosity to find out when it's cloned,
             *  or assign a random uuidv4() arbitrarily, and use that instead...
             *  ...and hope that Learnosity is okay with it
             */
            $newLearnosityReference = (string)Uuid::uuid4();

            /* Make a massive assumption that this _just worked_
             * You could lean on the polling system to find out when this cloning has finished,
             * or just blindly assume the uuidv4() was unique
             *
             * TODO make this safer
             */
            $response = $this->api->dataSetRequest(
                '/itembank/activities/duplicate',
                [
                    'activities' => [
                        [
                            'reference' => $oldLearnosityReference,
                            'new_reference' => $newLearnosityReference
                        ]
                    ]
                ],
            );
            $learnosityJobReference = $response['data']['job_reference'] ?? null;

            // Assign and save Learnosity reference
            /* FIXME projects need some special treatment of some kind,
             *  getBaseAssessable() might return a Schoolbox\Learning\Assessment\Project
             *  which won't have a Learnosity Reference field...weird behaviours
             *  But Why?
             */
            $destination->setLearnosityReference($newLearnosityReference);
            $destination->setLearnosityJobReference($learnosityJobReference);
            if ($destination->getId()) {
                $this->database->prepareQuery(
                    "
                        UPDATE assess
                        SET learnosity_reference = ?,
                            learnosity_job_reference = ?,
                            learnosity_job_status = ?,
                            learnosity_source_assessment_id = ?
                        WHERE assessment_id = ?
                    ",
                    [
                        $newLearnosityReference,
                        $learnosityJobReference,
                        Model::LEARNOSITY_JOB_STATUS_PROCESSING,
                        $source->getId(),
                        $destination->getId(),
                    ],
                )->execute();
            }

            // Return whatever the job ID from Learnosity was
            return $learnosityJobReference;
        }

        return null;
    }
}

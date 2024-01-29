<?php

/**
 * @copyright Copyright 2024, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Learning\Learnosity\Api;

use Alaress\Database\Connector\IConnector;
use Aura\Router\Generator;
use LearnositySdk\Exceptions\ValidationException;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\UriInterface;
use Ramsey\Uuid\Uuid;
use Schoolbox\Learning\Assessment\Quiz\Model as QuizDomainModel;
use Schoolbox\Learning\Learnosity\Api;
use Schoolbox\Learning\Quiz\Model;
use Schoolbox\TaskDaemon\Producer;
use Schoolbox\TaskDaemon\Request\HttpRequest\MessageFactory;
use Schoolbox\User;

/**
 * Specialised command for duplicating quiz contents from one quiz to another,
 * using the Learnosity API.
 */
class Duplicator
{
    /**
     * Constructor
     *
     * @param Api                     $api             Learnosity API connection.
     * @param IConnector              $database        Database in which to write the status of the duplication job.
     * @param UriInterface            $baseUrl         This Schoolbox instance's base URL: required for sending a
     *                                                 polling task to the task daemon.
     * @param RequestFactoryInterface $httpFactory     A HTTP request factory: required for creating requests to send to
     *                                                 the task daemon.
     * @param Generator               $routeGenerator  A route generator: used to create a URL for the polling request.
     * @param MessageFactory          $messageFactory  A message factory: used to create the message to send to the task
     *                                                 daemon.
     * @param Producer                $messageProducer Used to send messages to the task daemon once built.
     */
    public function __construct(
        private Api $api,
        private IConnector $database,
        private UriInterface $baseUrl,
        private RequestFactoryInterface $httpFactory,
        private Generator $routeGenerator,
        private MessageFactory $messageFactory,
        private Producer $messageProducer,
    ) {
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

    /**
     * Start polling for the status of the quiz's duplication job.
     *
     * This will set up a task daemon job, which will call `pollQuizStatus`
     * (via a controller) repeatedly until that method reports it's done.
     *
     * @param User            $user
     * @param QuizDomainModel $destination
     * @return void
     * @throws \Aura\Router\Exception\RouteNotFound
     * @throws \Interop\Queue\Exception
     * @throws \Interop\Queue\Exception\InvalidDestinationException
     * @throws \Interop\Queue\Exception\InvalidMessageException
     */
    public function initiatePollingForQuizStatus(User $user, QuizDomainModel $destination): void
    {
        $message = $this->messageFactory
            ->setUser($user)
            ->setRequest(
                $this->httpFactory
                    ->createRequest(
                        'POST',
                        $this->baseUrl->withPath(
                            $this->routeGenerator->generate(
                                'learnosity.postPollQuizStatus',
                                ['assessmentId' => $destination->getId()]
                            )
                        )
                    )
                    ->withHeader('accept', 'application/json')
            )
            ->build();
        $this->messageProducer->send($message, delay: 5, maxRequeues: 10);
    }
}

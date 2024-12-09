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
use LearnositySdk\Utils\Uuid;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\UriInterface;
use Psr\Log\LoggerInterface;
use Schoolbox\Learning\Assessment\Quiz\Model as QuizDomainModel;
use Schoolbox\Learning\Learnosity\Api;
use Schoolbox\Learning\Learnosity\Helper;
use Schoolbox\Learning\Learnosity\Importer\Cli\CopyMissingQuizException;
use Schoolbox\Learning\Learnosity\Importer\Cli\ImporterTool;
use Schoolbox\Learning\Learnosity\Importer\Cli\SourceAssociationException;
use Schoolbox\Learning\Learnosity\TagFactory;
use Schoolbox\TaskDaemon\Producer;
use Schoolbox\TaskDaemon\Request\HttpRequest\MessageFactory;
use Schoolbox\User;
use Throwable;

/**
 * Specialised command for duplicating quiz contents from one quiz to another,
 * using the Learnosity API.
 */
class Duplicator
{
    /**
     * The maximum number of times a job for the duplication of a quiz may fail
     * before being marked as unprocessable.
     */
    private const FAILURE_THRESHOLD = 5;

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
     * @param TagFactory              $tagFactory      Used to build a set of tags for the quiz in Learnosity.
     * @param LoggerInterface         $logger          Somewhere to log messages to
     */
    public function __construct(
        private Api $api,
        private IConnector $database,
        private UriInterface $baseUrl,
        private RequestFactoryInterface $httpFactory,
        private Generator $routeGenerator,
        private MessageFactory $messageFactory,
        private Producer $messageProducer,
        private TagFactory $tagFactory,
        private LoggerInterface $logger,
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
     * @throws SourceAssociationException if `$source` has no Learnosity reference: an empty source can't be copied to
     *                                    a destination.
     * @throws CopyMissingQuizException   if `$source`'s Learnosity reference doesn't match anything in Learnosity
     */
    public function copyQuizConstituents(QuizDomainModel $source, QuizDomainModel $destination): ?string
    {
        // Return early if this is not a pending job
        if ($destination->getLearnosityJobStatus() !== Helper::LEARNOSITY_PROCESS_STATUS_PENDING) {
            return $destination->getLearnosityJobReference();
        }

        /* Clone a Learnosity Reference in Learnosity land
         */
        $oldLearnosityReference = $source->getLearnosityReference();

        // Does this not have a Learnosity reference at all? That's an error.
        // I've seen empty but not null Learnosity references: not sure why they
        // happen, though.
        if (!isset($oldLearnosityReference)) {
            throw new SourceAssociationException('Source assessment has no Learnosity reference');
        }
        if (!Uuid::isValid($oldLearnosityReference)) {
            throw new SourceAssociationException('Source assessment Learnosity reference is not a valid UUID');
        }

        /* See if there are any items in it;
         * Learnosity crashes when you try to clone an itemless quiz
         */
        $activity = $this->api->dataGetRequest(
            '/itembank/activities',
            [
                'references' => [$oldLearnosityReference]
            ],
        );

        // No data found in Learnosity: raise an error
        if (!count($activity['data'])) {
            throw new CopyMissingQuizException(
                sprintf(
                    'Source assessment Learnosity reference %s does not exist in Learnosity: cannot copy',
                    $oldLearnosityReference,
                ),
            );
        }

        /* Get the new learnosity reference and return that
         *
         * TODO We could either Poll Learnosity to find out when it's cloned,
         *  or assign a random uuidv4() arbitrarily, and use that instead...
         *  ...and hope that Learnosity is okay with it
         */
        $newLearnosityReference = Uuid::generate();

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
                        learnosity_job_attempt_count = COALESCE(learnosity_job_attempt_count, 0) + 1,
                        learnosity_source_assessment_id = ?
                    WHERE assessment_id = ?
                ",
                [
                    $newLearnosityReference,
                    $learnosityJobReference,
                    Helper::LEARNOSITY_PROCESS_STATUS_PROCESSING,
                    $source->getId(),
                    $destination->getId(),
                ],
            )->execute();
        }

        // Return whatever the job ID from Learnosity was
        return $learnosityJobReference;
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
     * @throws \Interop\Queue\Exception
     * @throws \Interop\Queue\Exception\InvalidDestinationException
     * @throws \Interop\Queue\Exception\InvalidMessageException
     */
    public function initiatePollingForQuizStatus(User $user, QuizDomainModel $destination): void
    {
        /** @noinspection PhpUnhandledExceptionInspection - this route exists */
        $route = $this->routeGenerator->generate(
            'learnosity.postPollQuizStatus',
            ['assessmentId' => $destination->getId()]
        );
        $message = $this->messageFactory
            ->setUser($user)
            ->setRequest(
                $this->httpFactory
                    ->createRequest(
                        'POST',
                        $this->baseUrl->withPath($route),
                    )
                    ->withHeader('accept', 'application/json')
            )
            ->build();
        $this->messageProducer->send($message, delay: 5, maxRequeues: 10);
    }


    /**
     * Poll Learnosity for the status of this quiz
     *
     * @param QuizDomainModel $quiz
     * @return void
     */
    public function pollQuizStatus(QuizDomainModel $quiz): void
    {
        // Already complete or failed: don't bother re-checking
        $jobStatus = $quiz->getLearnosityJobStatus();
        if ($jobStatus === Helper::LEARNOSITY_PROCESS_STATUS_PROCESSING) {
            // Check the API for this job's status
            $learnosityJobResponse = $this->api->dataGetRequest(
                '/jobs',
                ["references" => [$quiz->getLearnosityJobReference()],],
            );

            // Possible statuses: queued, running, halted, completed
            // https://reference.learnosity.com/data-api/endpoints/jobs_endpoints
            $learnosityJobStatus = $learnosityJobResponse['data'][0]['status'] ?? null;
            $setTags             = false;
            $updateStatus        = false;
            if ($learnosityJobStatus === Api::JOB_STATUS_COMPLETED) {
                $setTags      = true;
                $updateStatus = true;
                $jobStatus    = Helper::LEARNOSITY_PROCESS_STATUS_COMPLETED;
            } elseif ($learnosityJobStatus === Api::JOB_STATUS_HALTED) {
                // TODO determine if this can be set to FAILED or UNPROCESSABLE
                // There may be some status code which marks the job as
                // containing bad data (and thus will never complete), as
                // opposed to a transient error (which may complete later).
                // Just mark as unprocessable after enough failures.
                $updateStatus = true;
                $jobStatus    = $this->determineFailureStatus($quiz);
            }

            // If complete: set tags
            if ($setTags) {
                $newTags = $this->tagFactory->produceFromAssessment($quiz);
                if ($quiz->getContext()->isImplementationContext()) {
                    $newTags[] = $this->tagFactory->produceImplFlag();
                }

                // If any setting of tags fails:
                // count this as a fail of the job itself.
                try {
                    $this->assignTags($quiz, $newTags);
                } catch (Throwable $e) {
                    // Log this somewhere I guess?
                    $updateStatus = true;
                    $jobStatus    = $this->determineFailureStatus($quiz);
                    $this->logger->warning(
                        sprintf(
                            'Job completed, but tag assignation failed (%s, message: %s): marking job as failed',
                            get_class($e),
                            $e->getMessage(),
                        )
                    );
                }
            }

            // If complete or failed: set status
            if ($updateStatus) {
                $this->updateStatus($quiz, $jobStatus);
            }
        }
    }

    /**
     * Assign `$newTags` to `$quiz` in Learnosity: this means assigning those
     * tags to the activity and its items.
     *
     * @param QuizDomainModel $quiz
     * @param array           $newTags
     * @return void
     * @throws ValidationException
     * @throws \JsonException
     */
    private function assignTags(QuizDomainModel $quiz, array $newTags): void
    {
        /* Fetch this activity from Learnosity; we need to assign tags to each of its items
         * because they appear in the global Item Bank. Unless we assign the classCode tag
         * here, we'll never know if items are for a spec or an impl when users search
         */
        $itemReferences = $this->api->dataGetRequest('/itembank/activities', [
            'references' => [
                $quiz->getLearnosityReference()
            ]
        ])['data'][0]['data']['items'] ?? null;

        if (null !== $itemReferences) {
            /* Due to learnosity behaviour https://help.learnosity.com/hc/en-us/requests/23258
             * we have to accommodate $itemReference being a string (lol) as well as an array (lol)
             */
            if (is_array($itemReferences[0])) {
                /* Forcibly mutate this, because learnosity insanity
                 */
                $itemReferenceList = array_column($itemReferences, 'id');
            } else {
                /* Presume it was a list of reference strings already
                 */
                $itemReferenceList = $itemReferences;
            }

            /* Initialise payload container
             */
            $emptyPayload = [
                'items' => []
            ];

            $payload = $emptyPayload;

            /* Assign tags to each item
             */
            foreach ($itemReferenceList as $item) {
                /* Maxed out? Send it.
                 */
                if (\count($payload['items']) === ImporterTool::LEARNOSITY_MAX_PAYLOAD_SIZE) {
                    $this->api->dataSetRequest('/itembank/items/tags', $payload);

                    /* Reset this for the next cycle
                     */
                    $payload = $emptyPayload;
                }

                /* Keep accumulating until we max out
                 */
                $payload['items'][] = [
                    'reference' => $item,
                    'tags'      => $newTags
                ];
            }

            /* Send the last payload if anything is in it
             */
            if (\count($payload['items'])) {
                $this->api->dataSetRequest('/itembank/items/tags', $payload);
            }
        }

        /* Assign tags to the activity itself
         */
        $this->api->dataSetRequest(
            '/itembank/activities/tags',
            [
                'activities' => [
                    [
                        'reference' => $quiz->getLearnosityReference(),
                        'tags'      => $newTags,
                    ],
                ],
            ],
        );
    }

    /**
     * Update the quiz's job status in `$quiz`, and its database field.
     *
     * @param QuizDomainModel $quiz
     * @param string|null     $jobStatus
     * @return void
     */
    private function updateStatus(QuizDomainModel $quiz, ?string $jobStatus): void
    {
        $query = $this->database->prepareQuery(
            "UPDATE assess SET learnosity_job_status = ? WHERE assessment_id = ?",
            [$jobStatus, $quiz->getId()],
        );
        $quiz->setLearnosityJobStatus($jobStatus);
        $query->execute();
    }

    /**
     * Which Learnosity failure processing status should this have?
     *
     * If it's failed `self::FAILURE_THRESHOLD` or more times, it's
     * unprocessable: otherwise it's just failed (and may be restarted).
     *
     * @param QuizDomainModel $quiz
     * @return string
     */
    private function determineFailureStatus(QuizDomainModel $quiz): string
    {
        return $quiz->getLearnosityJobAttemptCount() >= self::FAILURE_THRESHOLD
            ? Helper::LEARNOSITY_PROCESS_STATUS_UNPROCESSABLE
            : Helper::LEARNOSITY_PROCESS_STATUS_FAILED;
    }
}

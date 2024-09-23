<?php

/**
 * @copyright Copyright 2022, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

namespace Schoolbox\Learning\Assessment;

use Alaress\Lumberjack\Logger;
use Aura\Router\Generator;
use Http\Discovery\Psr17Factory;
use LearnositySdk\Exceptions\ValidationException;
use Schoolbox\Assessment\Rubric\DataModel\DataSource as RubricDataSource;
use Schoolbox\Learning\Assessment\DataModel\MapperProxy as AssessmentMapperProxy;
use Schoolbox\Learning\Assessment\DueWork\Model as DueWork;
use Schoolbox\Learning\Assessment\Quiz\Model as Quiz;
use Schoolbox\Learning\Learnosity\Api;
use Schoolbox\Learning\Learnosity\Helper;
use Schoolbox\Learning\Quiz\Mapper as QuizMapper;
use Schoolbox\Mapper\Exception as MapperException;
use Schoolbox\TaskDaemon\Producer;
use Schoolbox\TaskDaemon\Request\HttpRequest\MessageFactory;
use Schoolbox\User;

/**
 * Helper class for overwriting one assessment with the content of another.
 *
 * TODO merge this with Replicator: there's a lot of shared code
 */
class Overwriter
{
    /**
     * Constructor
     *
     * @param AssessmentMapperProxy $assessmentMapperProxy for querying assessments
     * @param QuizMapper            $quizMapper            for performing actions on quizzes
     * @param RubricDataSource      $rubricMapper          for performing actions on rubrics
     * @param Psr17Factory          $httpFactory           for building HTTP requests to send in taskd messages
     * @param MessageFactory        $messageFactory        for building messages to send to taskd
     * @param Producer              $messageProducer       for sending messages to taskd
     * @param Generator             $routeGenerator        for building URLs for HTTP requests to send to taskd
     */
    public function __construct(
        private AssessmentMapperProxy $assessmentMapperProxy,
        private QuizMapper $quizMapper,
        private RubricDataSource $rubricMapper,
        private Psr17Factory $httpFactory,
        private MessageFactory $messageFactory,
        private Producer $messageProducer,
        private Generator $routeGenerator,
    ) {
    }

    /**
     * Overwrite `$destination` with appropriate fields from `$source`.
     *
     * @param IAssessable $source
     * @param IAssessable $destination
     * @param User        $newOwner
     * @return IAssessable
     */
    public function overwrite(
        IAssessable $source,
        IAssessable $destination,
        User $newOwner,
    ): IAssessable {
        // Must assert that source and destination are the same sort of thing;
        // otherwise who knows what disaster could happen
        if (!($source::getFamilyName() === $destination::getFamilyName())) {
            throw new \RuntimeException(
                sprintf(
                    '$source (%s) and $destination (%s) must be the same family of assessable',
                    $source::getFamilyName(),
                    $destination::getFamilyName(),
                )
            );
        }

        // Restore destination before overwriting it?
        if ($destination->isDeleted()) {
            $this->assessmentMapperProxy->restoreSoftDeleted($destination);
        }

        // Overwrite destination fields with values from source
        $updatedDestination = $destination->overwrite($source)
            ->setDeletedAt(null);

        // If copying the assessment into a spec: clear its dates
        if ($destination->isSpecification()) {
            $updatedDestination = $updatedDestination
                ->setPublishAt(null)
                ->setOpenAt(null)
                ->setDueAt(null)
                ->setCloseAt(null);
        }

        $this->preprocessQuiz($source, $destination);

        // Delete quiz constituents and rubric
        $this->deleteDueWorkRubric($updatedDestination);

        /* Remove this quiz from Learnosity entirely,
         * and orphan it intentionally into an archived state
         */
        $this->deleteQuizConstituents($updatedDestination);

        // Save
        $this->assessmentMapperProxy->save($updatedDestination, $newOwner);

        // Copy DueWork Rubrics
        $this->replicateDueWorkRubric($source, $updatedDestination);

        // Copy Quiz Sections/Questions/Solutions
        $this->replicateQuizConstituents($source, $updatedDestination, $newOwner);

        return $updatedDestination;
    }


    /**
     * Preprocess `$destination` if it's a quiz: setting its:
     * * Learnosity references to null
     * * its job status to Pending
     * * its source assessment as `$source`
     *
     * @param IAssessable $source
     * @param IAssessable $destination
     * @return void
     */
    private function preprocessQuiz(IAssessable $source, IAssessable $destination): void
    {
        if (!$destination instanceof Quiz) {
            return;
        }
        $destination
            ->setLearnosityReference(null)
            ->setLearnosityJobReference(null)
            ->setLearnosityJobStatus(Helper::LEARNOSITY_PROCESS_STATUS_PENDING)
            ->setLearnositySourceAssessmentId($source->getId())
        ;
    }


    /**
     * Copy any quiz sections, questions and solutions to `$destination` from
     * `$source`.
     *
     * Uses `$creator` as the authenticated user for the copy quiz constituents
     * request.
     *
     * @param IAssessable $source
     * @param IAssessable $destination
     * @param User        $creator
     * @return void
     * @throws \InvalidArgumentException If Destination Quiz already has Sections
     */
    private function replicateQuizConstituents(IAssessable $source, IAssessable $destination, User $creator): void
    {
        if (!($source instanceof Quiz && $destination instanceof Quiz)) {
            return;
        }

        // Callback to URL which replicates constituents:
        // only trigger once the transaction closes
        $this->quizMapper->getDatabase()->onPersist(
            function () use ($source, $destination, $creator): void {
                $baseUrl = (new \Schoolbox\Url\Factory($this->quizMapper->getConfigLoader()))->buildFullyQualified();
                $message = $this->messageFactory
                    ->setUser($creator)
                    ->setRequest(
                        $this->httpFactory
                            ->createRequest(
                                'POST',
                                $baseUrl->withPath(
                                    $this->routeGenerator->generate(
                                        'learnosity.postCopyQuizConstituents',
                                        [
                                            'sourceId'      => $source->getId(),
                                            'destinationId' => $destination->getId(),
                                        ],
                                    )
                                )
                            )
                            ->withHeader('accept', 'application/json')
                    )
                    ->build();
                $this->messageProducer->send($message, maxRequeues: 10);
            }
        );
    }

    /**
     * Delete any quiz sections (and thus questions and solutions) from
     * `$assessment`, in preparation for a new set to be copied into it.
     *
     * @param IAssessable $assessment
     * @return void
     * @throws \DomainException
     * @throws ValidationException
     * @throws \RuntimeException
     */
    private function deleteQuizConstituents(IAssessable $assessment): void
    {
        if (!$assessment instanceof Quiz) {
            return;
        }

        /* $destination (spec) gets overwritten by $source (impl)
         * so we need to orphan its existing quiz thing
         */
        $api = new Api(
            (new \Schoolbox\Url\Factory($this->quizMapper->getConfigLoader()))->buildFullyQualified()->getHost(),
            $this->quizMapper->getConfigLoader()->get('learnosity_version'),
            $this->quizMapper->getConfigLoader()->get('learnosity_consumer_key'),
            $this->quizMapper->getConfigLoader()->get('learnosity_consumer_secret'),
            new Logger(STDOUT, STDOUT, \Monolog\Logger::DEBUG, [], true, 'learnosity')
        );

        /* FIXME Temporarily disabled due to https://help.learnosity.com/hc/en-us/requests/23138
         *  Uncomment whent hat bug has been resolved. This was intended to archive the old version
         *  of the quiz and then use the new version of the quiz. Disabling this means you have an
         *  orphaned quiz that's published and available for people to use, which isn't ideal
         */
        // (new Helper($api))->archive($assessment->getLearnosityReference());

        //        $this->quizMapper->deleteQuizConstituents($assessment->getId());
    }

    /**
     * Copy the rubric from `$source` into `$destination`.
     *
     * @param IAssessable $source
     * @param IAssessable $destination
     * @return void
     * @throws MapperException
     */
    private function replicateDueWorkRubric(IAssessable $source, IAssessable $destination): void
    {
        if (!($source instanceof DueWork && $destination instanceof DueWork)) {
            return;
        }
        $this->rubricMapper->copyRubric($source->getId(), $source->getContext(), $destination->getId());
    }

    /**
     * Delete the rubric from `$assessment`, in preparation for a new rubric to
     * be copied into it.
     *
     * @param IAssessable $assessment
     * @return void
     */
    private function deleteDueWorkRubric(IAssessable $assessment): void
    {
        if (!$assessment instanceof DueWork) {
            return;
        }
        $this->rubricMapper->deleteRubric($assessment->getId());
    }
}

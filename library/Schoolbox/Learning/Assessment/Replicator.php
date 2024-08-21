<?php

/**
 * @author      Andy Ryan <andy.ryan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Schoolbox\Learning\Assessment;

use Aura\Router\Generator;
use Http\Discovery\Psr17Factory;
use Schoolbox\Assessment\Rubric\DataModel\DataSource as RubricDataSource;
use Schoolbox\Learning\Assessment\DataModel\MapperProxy as AssessmentMapperProxy;
use Schoolbox\Learning\Assessment\DueWork\Model as DueWork;
use Schoolbox\Learning\Assessment\Quiz\Model as Quiz;
use Schoolbox\Learning\AssessmentContext;
use Schoolbox\Learning\Learnosity\Helper;
use Schoolbox\Learning\Quiz\Mapper as QuizMapper;
use Schoolbox\TaskDaemon\Producer;
use Schoolbox\TaskDaemon\Request\HttpRequest\MessageFactory;
use Schoolbox\User;

/**
 * Responsible for replicating Assessments in a new Context
 */
class Replicator
{
    /**
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
     * Deep-clone and re-create the given assessments in a new context
     *
     * To aid processing, the caller should populate dates on source Assessments, even
     * if the source Assessments are Specifications. This avoids needing to pass the dates in
     * a custom DTO.
     *
     * @param IAssessable       $source             the assessment to replicate
     * @param AssessmentContext $destinationContext the location into which to replicate the assessment
     * @param User              $newOwner           who should own the newly created assessment
     * @param null|int          $sequence           enable explicit sequence setting
     * @return IAssessable if any part of the replication fails
     * @throws \InvalidArgumentException If Destination Quiz already has Sections
     * @throws \UnexpectedValueException
     */
    public function replicateInNewContext(
        IAssessable $source,
        AssessmentContext $destinationContext,
        User $newOwner,
        int $sequence = null
    ): IAssessable {
        $newAssessment = $source->cloneForCopy();
        $newAssessment->setContext($destinationContext);

        // If copying the assessment into a spec: clear its dates
        if ($destinationContext->isSpecificationContext()) {
            $newAssessment = $newAssessment
                ->setPublishAt(null)
                ->setOpenAt(null)
                ->setDueAt(null)
                ->setCloseAt(null);
        }

        $destinationContext->getAssessables()->add($newAssessment);
        $newAssessment->setCreator($newOwner);
        if (null !== $sequence) {
            $newAssessment->setSequence($sequence);
        }

        $this->preprocessQuiz($source, $newAssessment);

        $this->assessmentMapperProxy->save($newAssessment, $newOwner);
        $destinationContext->getAssessables()->add($newAssessment);

        // Copy DueWork Rubrics
        $this->replicateDueWorkRubric($source, $newAssessment);

        // Copy Quiz Sections/Questions/Solutions
        $this->replicateQuizConstituents($source, $newAssessment, $newOwner);

        return $newAssessment;
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
     * @return void
     * @throws \DomainException
     */
    public function replicateQuizConstituents(IAssessable $source, IAssessable $destination, User $creator): void
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
     * Copy the rubric from `$source` into `$destination`.
     *
     * @param IAssessable $source
     * @param IAssessable $destination
     * @return void
     */
    private function replicateDueWorkRubric(IAssessable $source, IAssessable $destination): void
    {
        if (!($source instanceof DueWork && $destination instanceof DueWork)) {
            return;
        }
        $this->rubricMapper->copyRubric($source->getId(), $destination->getId());
    }
}

<?php

/**
 * @author      Justin Clarke <justin.clarke@schoolbox.education>
 * @copyright   Copyright 2025, Schoolbox Pty Ltd
 * @link        https://schoolbox.education/
 */

declare(strict_types=1);

namespace Schoolbox\Assessment\Course\Status;

use Alaress_Date_Format;
use PDOException;
use Schoolbox\Assessment\Component\SubmissionBox\ReturnedWorkMapper;
use Schoolbox\Assessment\Component\SubmissionBox\Submission\SingleFile\Model as SubmissionFile;
use Schoolbox\Error\Mapper as ErrorMapper;
use Schoolbox\Filter\IFactoryFactory;
use Schoolbox\Filter\Mapper;
use Schoolbox\Learning\Assessment\DataModel\MapperProxy as AssessmentDataSource;
use Schoolbox\Learning\Assessment\DataModel\MapperProxy as AssessmentMapperProxy;
use Schoolbox\Learning\Assessment\DueWork;
use Schoolbox\Learning\Assessment\DueWork\Response\Model as Submission;
use Schoolbox\Learning\Assessment\Participant;
use Schoolbox\Learning\Assessment\Project;
use Schoolbox\Learning\Assessment\Response\DataSource as ResponseDataSource;
use Schoolbox\Learning\InlineGrader\Kami\Connector;
use Schoolbox\Learning\Quiz\Mapper as QuizMapper;
use Schoolbox\Learning\Quiz\Model as Quiz;
use Alaress\Web\Controller as WebController;
use Schoolbox\Filter;
use Schoolbox\Filter\Codec;
use Schoolbox\Learning\Assessment\IAssessable;
use Schoolbox\Learning\Course\DataModel\Implementation as CourseImpl;
use Schoolbox\Learning\Course\DataModel\Implementation\Unit as UnitImpl;
use Schoolbox\Learning\Course\DataModel\Specification as CourseSpec;
use Schoolbox\Learning\Course\DataModel\Specification\Model;
use Schoolbox\Mapper\Exception;
use Schoolbox\Mapper\Exception\Deleted;

use function array_combine;

class Controller extends WebController\Base
{
    /**
     * @param ErrorMapper $errorMapper
     * @param AssessmentMapperProxy $assessmentMapperProxy
     * @param QuizMapper $quizMapper
     * @param IFactoryFactory $filterFactoryFactory
     * @param Mapper $filterMapper
     * @param Filter\Selection\Mapper $filterSelectionMapper
     * @param CourseImpl\Mapper $courseImplMapper
     * @param UnitImpl\Mapper $unitImplMapper
     * @param AssessmentMapperProxy $assessmentDataSource
     * @param ResponseDataSource $responseDataSource
     * @param ReturnedWorkMapper $returnedWorkMapper
     * @param Project\ResultCache\Mapper $projectResultCacheMapper
     * @param Connector $kamiConnector
     */
    public function __construct(
        private ErrorMapper $errorMapper,
        private AssessmentMapperProxy $assessmentMapperProxy,
        private QuizMapper $quizMapper,
        private Filter\IFactoryFactory $filterFactoryFactory,
        private Filter\Mapper $filterMapper,
        private Filter\Selection\Mapper $filterSelectionMapper,
        private CourseImpl\Mapper $courseImplMapper,
        private UnitImpl\Mapper $unitImplMapper,
        private AssessmentDataSource $assessmentDataSource,
        private ResponseDataSource $responseDataSource,
        private ReturnedWorkMapper $returnedWorkMapper,
        private Project\ResultCache\Mapper $projectResultCacheMapper,
        private Connector $kamiConnector,
    ) {
    }

    /**
     * publish feedback for all assessments imported from a course level assessment
     *
     * @param Model $courseSpec
     * @param IAssessable $assessmentSpec
     * @return void
     * @throws Exception
     * @throws Deleted
     * @api POST /learning/courseMarkbook/{courseSpecId:\d+}/assessment/{assessmentId:\d+}/publish
     */
    public function postPublishFeedback(CourseSpec\Model $courseSpec, IAssessable $assessmentSpec): void
    {
        // This report may take a while
        $this->extendExecutionTime();

        /** @var int[] $draftFeedbackIds */
        $draftFeedbackIds = $this->requestPost->get('drafts', []);
        $now              = Alaress_Date_Format::now();
        $results = [];
        $projectCache = (new Project\ResultCache\ResultCache($this->projectResultCacheMapper));

        // Fetch assessments
        $assessments = $this->assessmentMapperProxy->findAllImplementationsBySpecificationId($assessmentSpec->getId())->indexBy('id');

        // hydrate participants
        $this->assessmentMapperProxy->loadParticipantsIntoAssessmentCollection($assessments, null, true);

        foreach ($draftFeedbackIds as $draftFeedbackId) {
            // Verify that this return is for this assessment
            $submissionReturn = $this->returnedWorkMapper->findById($draftFeedbackId);
            $publishSuccess = false;

            // grab the actual assessment
            /** @var \Schoolbox\Learning\Assessment\DueWork\Model $assessment */
            $assessment = $assessments->getBy('id', $submissionReturn->getSubmissionBox()->getAssessmentModel()->getId());

            if (!$assessment) {
                $results[$draftFeedbackId] = ['publishSuccess' => false, 'error' => 'Assessment not found'];
                continue;
            }

            $submissionReturn->setPublishedAt($now);

            try {
                $this->returnedWorkMapper->save($submissionReturn);
                $publishSuccess = true;

                // Find the Kami worksheet (if it exists)
                if ($submissionReturn->getSubmission()) {
                    $responses = $this->responseDataSource->findForAssessment($assessment, $submissionReturn->getSubmission()->getOwner());
                    if (0 !== $responses->count()) {
                        $response = null;
                        foreach ($responses as $responseCurrent) {
                            if ($responseCurrent->getId() == $submissionReturn->getSubmission()->getId()) {
                                $response = $responseCurrent;
                                break;
                            }
                        }

                        // attempt to clean as we go
                        unset($responses);

                        /** @var ?DueWork\Response\Model $response */
                        $kamiModel = $response?->getAllFiles()->filter(function (SubmissionFile $file) {
                            return $file->getIsWorksheet();
                        })->first()?->getKami();
                        if ($kamiModel) {
                            // Save the kami doc to the feedback object
                            $this->kamiConnector->initiatePollingForKamiDownload(
                                $kamiModel,
                                'feedback',
                                $this->getUser(),
                            );
                        }
                    }
                }

                /* Update project result cache when Publish All is used
                 */
                $projectCache->refresh($this->assessmentDataSource, $assessment, $submissionReturn->getOwner());
            } catch (PDOException | \DomainException | \InvalidArgumentException | \LogicException | \UnexpectedValueException $e) {
                $publishSuccess = false;
            }

            $results[$draftFeedbackId] = ['publishSuccess' => $publishSuccess];
        }

        $this->response = $this->responseAsJson($results);
    }

    /**
     * Determine if there are any submission files or feedback available for download on the assessment
     *
     * Direct clone from \Schoolbox\Learning\Assessment\DueWork\Controller::hasDownloadableSubmissionFiles
     * @see \Schoolbox\Learning\Assessment\DueWork\Controller::hasDownloadableSubmissionFiles
     * @param IAssessable $assessment
     * @return bool
     */
    private function hasDownloadableSubmissionFiles(IAssessable $assessment): bool
    {
        // Grab all participants in the assessment

        /** @var Participant $participant */
        foreach ($assessment->getAllParticipants() as $participant) {
            // Iterate all feedbacks
            foreach ($participant->getAllFeedbacks() as $feedback) {
                if ($feedback instanceof \Schoolbox\Learning\Assessment\DueWork\Feedback\Model && $feedback->isPublished()) {
                    return true;
                }
            }

            // Iterate all their responses
            /** @var Submission $response */
            foreach ($participant->getAllResponses() as $response) {
                // If the response is a submission...

                if ($response instanceof Submission && !$response->isDraft()) {
                    if ($response->getAllFiles()->count() > 0) {
                        return true;
                    }
                }
            }
        }

        return false;
    }


    /**
     * Converts an assessment_id to a Quiz
     *
     * @see \Schoolbox\Learning\Assessment\Quiz\Controller::getQuizForAssessmentId
     * @param int $assessmentId
     * @return Quiz
     * @throws \Schoolbox\Application\Exception\Exception
     */
    protected function getQuizForAssessmentId($assessmentId): Quiz
    {
        /** @var Quiz $quiz */
        $quiz = $this->quizMapper->findByColumn('assessment_id', $assessmentId, ['deleted' => true])->first();

        if (Quiz::AVAILABILITY_ASSESS != $quiz->getAvailability()) {
            throw $this->errorMapper->generateException(ErrorMapper::ERROR_PAGE_NOT_FOUND);
        }
        return $quiz;
    }


    /**
     * @see \Schoolbox\Assessment\Course\Markbook\Controller::convertRequestToFilters
     * @param CourseSpec\Model $courseSpec
     * @param array            $request  Indexed by filter.id, option.key
     * @param bool             $saveToDB Should we persist these selections to the DB ?
     * @return array
     * @throws \Alaress\Database\Predicate\InvalidColumnException
     * @throws \DomainException If this class has not defined what entity it is filtering
     * @throws \InvalidArgumentException If unable to find the filter mapper class
     * @throws \Schoolbox\Mapper\DefinitionException If join $name isn't on this mapper
     * @throws \Schoolbox\Mapper\Exception If an alias is incorrect
     * @throws \UnderflowException
     * @throws \UnexpectedValueException
     */
    private function convertRequestToFilters(CourseSpec\Model $courseSpec, array $request, bool $saveToDB): array
    {
        $page            = Filter\Page\Model::PAGE_COURSE_MARK_BOOK;
        $codec           = new Codec($this->filterMapper, $this->filterSelectionMapper);

        // Convert Request to Selection models
        $selectionsPerFilter = $codec->convertRequestToSelections($this->getUser(), $page, $request);
        if ($saveToDB) {
            $this->filterSelectionMapper->updateSelections($this->getUser(), $page, $selectionsPerFilter);
        }

        $filters = $this->filterFactoryFactory->convertSelectionsToFilters($selectionsPerFilter, $this->getUser(), $page);

        // Ensure that assessments are:
        // * not Assessment Specs
        // * Are inside unitImpls that belong to courseImpls that were imported from the courseSpec
        $courseImpls = $this->courseImplMapper->findAllByCourseSpecificationId($courseSpec->getId());
        if ($courseImpls->count()) {
            $pb          = $this->unitImplMapper->getPredicateBuilder()->in('course_implementation_id', $courseImpls->getPropertyValues('id'));
            $unitImplIds = $this->unitImplMapper->findIdsByPredicates($pb);
        } else {
            $unitImplIds = [];
        }
        $filters = array_merge($filters, [
            Filter\Filters\Assessment\NullableContext\UnitSpec\Definition::class => [],
            Filter\Filters\Assessment\NullableContext\UnitImpl\Definition::class => array_combine($unitImplIds, $unitImplIds),
        ]);

        return [$filters, $codec, $selectionsPerFilter];
    }
}

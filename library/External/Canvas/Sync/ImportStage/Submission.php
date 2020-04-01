<?php
/**
 *
 * @copyright   Copyright 2020, Alaress Pty Ltd
 * @link        https://schoolbox.com.au/
 */
declare(strict_types=1);

namespace External\Canvas\Sync\ImportStage;

use Alaress\Database\Connector\IConnector;
use Alaress_Date_Format;
use Psr\Log\LoggerInterface;
use Schoolbox\Assessment\Component\SubmissionBoxMapper;
use Schoolbox\Assessment\Component\SubmissionBox\ReturnedWorkMapper;
use Schoolbox\GenericCollection;
use Schoolbox\Learning\Assessment\IAssessable;
use Schoolbox\User;
use Schoolbox\UserMapper;
use Alaress\Database;
use Alaress\Database\Predicate\Builder;
use External\Canvas\Sync\Bridge;
use External\Canvas\Sync\ImportStage\Traits\ModelSaveTrait;
use External_ClassInterface;
use Schoolbox\Assessment\Component\SubmissionBox;
use Schoolbox\Learning\Assessment\DataModel as Assessment;
use Schoolbox\Assessment\Participant;
use Schoolbox\Learning\Course\DataModel\Implementation\Unit as UnitImpl;
use Schoolbox\Learning\Course\DataModel\Specification\Unit as UnitSpec;
use Schoolbox\Mapper;
use function array_key_exists;
use function implode;

/**
 * Responsible for importing Submissions.
 */
class Submission extends SectionAssignment
{
    use ModelSaveTrait;

    /** @var ReturnedWorkMapper */
    private $returnedWorkMapper;

    /** @var Assessment\MapperProxy */
    private $assessmentDataSource;

    /** @var int */
    private $syncMarkCount;

    /**
     * @param IConnector                $canvasDb
     * @param IConnector                $schoolboxDb
     * @param External_ClassInterface   $externalClass
     * @param string|null               $currentFileYear
     * @param UnitImpl\Mapper           $unitImplMapper
     * @param UnitSpec\Mapper           $unitSpecMapper
     * @param SubmissionBoxMapper       $submissionBoxMapper
     * @param Assessment\Mapper         $assessmentMapper
     * @param Participant\Plan\Mapper   $planMapper
     * @param Participant\Mapper        $participantMapper
     * @param UserMapper                $userMapper
     * @param LoggerInterface           $log
     * @param Bridge\AssignmentDates    $assignmentDatesBridge
     * @param Bridge\Course             $courseBridge
     * @param Bridge\CourseSection      $courseSectionBridge
     * @param Bridge\AssignmentOverride $assignmentOverrideBridge
     * @param ReturnedWorkMapper        $returnedWorkMapper
     * @param Assessment\MapperProxy    $assessmentDataSource
     */
    public function __construct(
        IConnector $canvasDb,
        IConnector $schoolboxDb,
        External_ClassInterface $externalClass,
        ?string $currentFileYear,
        UnitImpl\Mapper $unitImplMapper,
        UnitSpec\Mapper $unitSpecMapper,
        SubmissionBoxMapper $submissionBoxMapper,
        Assessment\Mapper $assessmentMapper,
        Participant\Plan\Mapper $planMapper,
        Participant\Mapper $participantMapper,
        UserMapper $userMapper,
        LoggerInterface $log,
        Bridge\AssignmentDates $assignmentDatesBridge,
        Bridge\Course $courseBridge,
        Bridge\CourseSection $courseSectionBridge,
        Bridge\AssignmentOverride $assignmentOverrideBridge,
        ReturnedWorkMapper $returnedWorkMapper,
        Assessment\MapperProxy $assessmentDataSource
    ) {
        parent::__construct(
            $canvasDb,
            $schoolboxDb,
            $externalClass,
            $currentFileYear,
            $unitImplMapper,
            $unitSpecMapper,
            $submissionBoxMapper,
            $assessmentMapper,
            $planMapper,
            $participantMapper,
            $userMapper,
            $log,
            $assignmentDatesBridge,
            $courseBridge,
            $courseSectionBridge,
            $assignmentOverrideBridge
        );

        $this->returnedWorkMapper   = $returnedWorkMapper;
        $this->assessmentDataSource = $assessmentDataSource;
    }


    /**
     * @throws Mapper\Exception
     * @throws Mapper\Exception\Deleted
     */
    public function synchronise(): void
    {
        $this->log->notice("Synchronising Canvas Submissions");

        $this->resetCounts();

        $sectionSisSourceIds   = $this->courseSectionBridge->getMappedSisSourceIds();
        $coursesSectionData    = $this->loadCanvasSectionStudentData($sectionSisSourceIds);
        $coursesAssessmentData = $this->loadCanvasAssessmentAndOverrideData($sectionSisSourceIds);

        foreach ($coursesSectionData as $courseCanvasId => $courseSectionsData) {
            $this->log->info("Processing Canvas Course '{$courseSectionsData['name']}' (CanvasId: $courseCanvasId)");
            if (!array_key_exists($courseCanvasId, $coursesAssessmentData)) {
                continue;
            }
            foreach ($coursesAssessmentData[$courseCanvasId] as $assessmentCanvasId => $canvasAssessment) {
                $hasMarkToSync       = false;
                $sbSubmissionBoxSpec = $this->submissionBoxMapper->findOneByColumn('external_id', $assessmentCanvasId);

                $assessmentImpls   = $this->findAllImplementationsBySpecificationId($sbSubmissionBoxSpec->getAssessmentModel()->getId());
                $canvasSubmissions = $this->loadCanvasSubmissions($assessmentCanvasId);
                $this->log->info("Processing submissions for Canvas Assignment '{$canvasAssessment['title']}' (CanvasId: $assessmentCanvasId)");

                /** @var IAssessable $assessmentImpl */
                foreach ($assessmentImpls as $assessmentImpl) {
                    // load participants
                    $assessmentCollection = $this->assessmentDataSource->createCollection()->add($assessmentImpl);
                    try {
                        $result = $this->assessmentDataSource->loadParticipantsIntoAssessmentCollection($assessmentCollection);
                    } catch (\Exception $e) {
                        $this->log->warning('Failed to load participant: ' . $e->getMessage() . ' ' . $e->getTraceAsString());
                        continue;
                    }
                    foreach ($result as $assessmentWithParticipants) {
                        // should be only one
                        $assessmentImpl = $assessmentWithParticipants;
                    }
                    // get existing marks for this assessment
                    $submissionBox     = $this->submissionBoxMapper->findOneByColumn('assessment_id', $assessmentImpl->getId());
                    $existingFeedbacks = $this->returnedWorkMapper->findByColumnList([
                        'box_id' => $submissionBox->getId(),
                    ]);
                    $syncedFeedbackIds = [];

                    foreach ($assessmentImpl->getPlans() as $plan) {
                        /** @var Participant\Plan\Model $plan */
                        foreach ($plan->getParticipants() as $participant) {
                            foreach ($canvasSubmissions as $canvasSubmission) {
                                if ($canvasSubmission['reviewee_id'] !== $participant->getUser()->getExternalId()) {
                                    // current canvas submission is not for this participant
                                    continue;
                                }
                                try {
                                    $reviewee = $this->userMapper->findByExternalId($canvasSubmission['reviewee_id']);
                                } catch (Mapper\Exception $e) {
                                    // If the reviewee (i.e. student) does not exist, skip this canvas submission
                                    $this->log->debug("Reviewee (ExternalId: {$canvasSubmission['reviewee_id']}) does not exist in Schoolbox");
                                    continue;
                                }

                                try {
                                    $reviewer = $this->userMapper->findByExternalId($canvasSubmission['reviewer_id']);
                                } catch (Mapper\Exception $e) {
                                    $reviewer = $this->userMapper->findById($this->getSchoolboxAdminUserId());
                                }
                                $this->log->debug("Synchronising Canvas mark for {$reviewee->getFullName()} [{$reviewee->getId()}]");

                                /** @var SubmissionBox\ReturnedWork $feedback */
                                $feedback            = $this->syncFeedback(
                                    $submissionBox, $reviewer, $reviewee, $canvasSubmission, $existingFeedbacks
                                );
                                $syncedFeedbackIds[] = $feedback->getId();
                                $this->syncMarkCount++;
                                $hasMarkToSync = true;
                            }
                        }
                    }
                    $this->deleteUnSynchronisedFeedbacks($existingFeedbacks, $syncedFeedbackIds);
                }
                if (!$hasMarkToSync) {
                    $this->log->debug("This Canvas assignment has no marks to sync");
                }
            }
        }

        $this->log->debug("{$this->syncMarkCount} marks processed.");
    }

    /**
     * Finds or creates Schoolbox Feedback corresponding to the given canvas submission
     * @param SubmissionBox     $submissionBox
     * @param User              $reviewer
     * @param User              $reviewee
     * @param array             $canvasSubmission
     * @param GenericCollection $existingFeedbacks
     * @return SubmissionBox\ReturnedWork
     * @throws Mapper\Exception
     */
    private function syncFeedback(SubmissionBox $submissionBox, User $reviewer, User $reviewee, array $canvasSubmission, GenericCollection $existingFeedbacks): SubmissionBox\ReturnedWork
    {
        /** @var SubmissionBox\ReturnedWork $existingFeedback */
        foreach ($existingFeedbacks as $existingFeedback) {
            // check whether the rest are the same
            if ($existingFeedback->getOwner()->getId() === $reviewee->getId()
                && $existingFeedback->getMarker()->getId() === $reviewer->getId()
                && $existingFeedback->getComment() == $canvasSubmission['comment']
                && $existingFeedback->getHumanReadableMark() == $canvasSubmission['mark']
                && $existingFeedback->getNormalizedMark() == $canvasSubmission['norm_mark']
            ) {
                return $existingFeedback;
            }
        }

        // Couldn't find. Creating a new feedback
        $feedback = $this->returnedWorkMapper->createModel();
        $feedback->setSubmissionBox($submissionBox)
                 ->setMarker($reviewer)
                 ->setOwner($reviewee)
                 ->setReviewRoute(SubmissionBox\ReturnedWork::REVIEW_ROUTE_INSTRUCTOR)
                 ->setComment($canvasSubmission['comment'])
                 ->setHumanReadableMark($canvasSubmission['mark'])
                 ->setNormalizedMark($canvasSubmission['norm_mark'])
                 ->setCreatedAt(Alaress_Date_Format::newDatabaseDate($canvasSubmission['graded_at']))
                 ->setPublishedAt(Alaress_Date_Format::newDatabaseDate($canvasSubmission['graded_at']))
                 ->setUpdatedAt(Alaress_Date_Format::newDatabaseDate($canvasSubmission['graded_at']));

        /* Cannot use mappers here to save because:
         * 1. We want to preserve Canvas create/update timestamps (alt. method would be to save via mapper, then raw query to override)
         * 2. Too many inappropriate pre/post save callbacks on mappers which cannot be altered in a master feature.
         */
        return $this->saveModel($this->returnedWorkMapper, $feedback, false, false);
    }


    /**
     * Deletes existing feedbacks which did not get synced
     * @param GenericCollection $existingFeedbacks
     * @param array             $syncedFeedbackIds
     */
    private function deleteUnSynchronisedFeedbacks(GenericCollection $existingFeedbacks, array $syncedFeedbackIds)
    {
        $deleteIds = array_diff($existingFeedbacks->getPropertyValues('id'), $syncedFeedbackIds);
        $this->returnedWorkMapper->deleteByColumn('id', $deleteIds);
    }

    /**
     * @inheritDoc
     */
    protected function resetCounts(): void
    {
        $this->syncMarkCount = 0;
    }

    /**
     * Finds assessment impl from assessment spec
     * @param int $assessmentSpecId
     * @return GenericCollection|IAssessable[]
     */
    private function findAllImplementationsBySpecificationId(int $assessmentSpecId)
    {
        $query     = '
                SELECT implementation_assessment_id
                FROM assessment_specification_implementation
                WHERE specification_assessment_id = :specId';
        $statement = $this->schoolboxDb->executeQuery($query, ['specId' => $assessmentSpecId]);
        $ids       = $statement->fetchAll(\PDO::FETCH_COLUMN);

        return $this->assessmentDataSource->findAllByIds($ids);
    }

    /**
     * Fetch canvas submissions (include marks) related to the given assignment
     * @param int $assignmentCanvasId
     * @return array
     */
    private function loadCanvasSubmissions(int $assignmentCanvasId): array
    {
        $mySqlQuoter = new Database\Quoter\Mysql\Quoter();
        $pb          = (new Builder($mySqlQuoter, ''))->equals('assignment_dim.canvas_id', $assignmentCanvasId);
        $pb->notNull('submission_dim.published_grade');
        $whereClause = implode(' AND ', $pb->getWhere());

        $sql = "
            SELECT
                assignment_dim.id,
                assignment_dim.canvas_id,
                submission_dim.id as submission_id,
                submission_dim.published_grade as mark,
                submission_fact.published_score as norm_mark,
                reviewer.sis_user_id as reviewer_id,
                reviewee.sis_user_id as reviewee_id,
                submission_comment_dim.comment as comment,
                submission_dim.graded_at as graded_at
            FROM assignment_dim
            LEFT JOIN submission_dim ON assignment_dim.id = submission_dim.assignment_id
            LEFT JOIN submission_fact ON submission_fact.submission_id = submission_dim.id
            LEFT JOIN submission_comment_dim ON submission_comment_dim.submission_id = submission_dim.id
            LEFT JOIN pseudonym_dim as reviewer ON reviewer.user_id = submission_dim.grader_id AND reviewer.workflow_state = 'active'
            LEFT JOIN pseudonym_dim as reviewee ON reviewee.user_id = submission_dim.user_id AND reviewee.workflow_state = 'active'

            WHERE {$whereClause}
            ORDER BY submission_dim.id
        ";

        $statement = $this->canvasDb->executeQuery($sql, $pb->getParams());
        return $statement->fetchAll();
    }

    /**
     * Returns the Schoolbox Admin user's id
     *
     * @return int
     * @throws Database\Exception
     */
    private function getSchoolboxAdminUserId(): int
    {
        $sql = "SELECT id FROM user WHERE username = :username AND deleted_at IS NULL";

        return (int)$this->schoolboxDb->executeQuery($sql, ['username' => User::USERNAME_SCHOOLBOX_ADMIN])->fetchColumn();
    }
}

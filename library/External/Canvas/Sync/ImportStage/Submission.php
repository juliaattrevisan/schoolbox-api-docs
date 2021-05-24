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
use Schoolbox\Learning\MarkScheme\IMarkScheme;
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
use Schoolbox\Learning\MarkScheme\Factory as MarkSchemeFactory;

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
     * canvasUserId => SB User
     * @var array
     */
    private $canvasUserIdUserMap;

    /** @var User */
    private $schoolboxAdmin;

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
        if (!$this->courseSectionBridge->syncCanvasMarks()) {
            $this->log->info('Skipping syncing Canvas Submissions');
            return;
        }

        $this->log->notice("Synchronising Canvas Submissions");

        $this->resetCounts();
        $this->canvasUserIdUserMap = [];

        $sectionSisSourceIds   = $this->courseSectionBridge->getMappedSisSourceIds();
        $coursesSectionData    = $this->loadCanvasSectionStudentData($sectionSisSourceIds);
        $coursesAssessmentData = $this->loadCanvasAssessmentAndOverrideData($sectionSisSourceIds);
        $markSchemaFactory = new MarkSchemeFactory();

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

                    $markScheme = $markSchemaFactory->spawnMarkSchemeFor($assessmentImpl);

                    foreach ($assessmentImpl->getPlans() as $plan) {
                        foreach ($plan->getParticipants() as $participant) {
                            foreach ($canvasSubmissions as $canvasSubmission) {
                                if ($canvasSubmission['reviewee_id'] !== $participant->getUser()->getExternalId()) {
                                    // current canvas submission is not for this participant
                                    continue;
                                }
                                // $participant should be a school box user with this reviewee_id as their external_id
                                $reviewee = $participant->getUser();
                                if (
                                    !empty($canvasSubmission['comment']
                                    && $canvasSubmission['comment_created_at'] > $canvasSubmission['graded_at'])
                                ) {
                                    $reviewer     = $this->findUserByCanvasUserId($canvasSubmission['commenter_id']);
                                    $feedbackTime = $canvasSubmission['comment_created_at'];
                                } else {
                                    $reviewer     = $this->findUserByCanvasUserId($canvasSubmission['grader_user_id']);
                                    $feedbackTime = $canvasSubmission['graded_at'];
                                }

                                $feedback            = $this->syncFeedback(
                                    $submissionBox,
                                    $reviewer,
                                    $reviewee,
                                    $canvasSubmission,
                                    $existingFeedbacks,
                                    $markScheme,
                                    $feedbackTime
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
     * @param IMarkScheme       $markScheme
     * @param string            $feedbackTime
     * @return SubmissionBox\ReturnedWork
     * @throws Mapper\Exception
     */
    private function syncFeedback(
        SubmissionBox $submissionBox,
        User $reviewer,
        User $reviewee,
        array $canvasSubmission,
        GenericCollection $existingFeedbacks,
        IMarkScheme $markScheme,
        string $feedbackTime
    ): SubmissionBox\ReturnedWork {
        $normMark = $canvasSubmission['norm_mark'];
        if ($submissionBox->getMaxMark()) {
            $normMark = ($normMark / $submissionBox->getMaxMark()) * 100;
        }
        $readableMark = $canvasSubmission['mark'];
        if ($submissionBox->getMarkType()->getSlug() !== SubmissionBox\MarkType::SLUG_NONE) {
            $readableMark = $markScheme->getHumanReadableMark($normMark);
        }
        /** @var SubmissionBox\ReturnedWork $existingFeedback */
        foreach ($existingFeedbacks as $existingFeedback) {
            // check whether the rest are the same
            if (
                $existingFeedback->getOwner()->getId() === $reviewee->getId()
                && $existingFeedback->getMarker()->getId() === $reviewer->getId()
                && $existingFeedback->getComment() == $canvasSubmission['comment']
                && $existingFeedback->getHumanReadableMark() == $readableMark
                && $existingFeedback->getNormalizedMark() == $normMark
            ) {
                return $existingFeedback;
            }
        }

        // time string from Canvas is coming in UTC
        $feedbackTime = date_create("{$feedbackTime} UTC");

        // Couldn't find. Creating a new feedback
        $feedback = $this->returnedWorkMapper->createModel();
        $feedback->setSubmissionBox($submissionBox)
                 ->setMarker($reviewer)
                 ->setOwner($reviewee)
                 ->setReviewRoute(SubmissionBox\ReturnedWork::REVIEW_ROUTE_INSTRUCTOR)
                 ->setComment($canvasSubmission['comment'])
                 ->setHumanReadableMark($readableMark)
                 ->setNormalizedMark($normMark)
                 ->setCreatedAt(Alaress_Date_Format::newFromTimestamp($feedbackTime->getTimestamp()))
                 ->setPublishedAt(Alaress_Date_Format::newFromTimestamp($feedbackTime->getTimestamp()))
                 ->setUpdatedAt(Alaress_Date_Format::newFromTimestamp($feedbackTime->getTimestamp()));

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
                reviewee.sis_user_id as reviewee_id,
                submission_dim.grader_id as grader_user_id,
                submission_dim.graded_at as graded_at,
                submission_comment_dim.comment as comment,
                submission_comment_dim.author_id as commenter_id,
                submission_comment_dim.created_at as comment_created_at,
                submission_comment_dim.updated_at as comment_updated_at
            FROM assignment_dim
            LEFT JOIN submission_dim ON assignment_dim.id = submission_dim.assignment_id
            LEFT JOIN submission_fact ON submission_fact.submission_id = submission_dim.id
            LEFT JOIN submission_comment_dim ON submission_comment_dim.submission_id = submission_dim.id
                                             AND submission_comment_dim.author_id <> submission_dim.user_id
            LEFT JOIN pseudonym_dim as reviewee ON reviewee.user_id = submission_dim.user_id AND reviewee.workflow_state = 'active'

            WHERE {$whereClause}
            ORDER BY submission_dim.id
        ";

        $statement = $this->canvasDb->executeQuery($sql, $pb->getParams());
        return $statement->fetchAll();
    }

    /**
     * Find a SB user who matches Canvas user with given canvas user id
     * 1. attempt to match using Canvas sis_user_id and SB synergy_id
     * 2. if 1 failed, try matching Canvas unique_name and SB email address
     * 3. if 2 failed, return schoolbox admin
     * @param string|null $canvasUserId
     * @return User|null
     * @throws Mapper\Exception
     */
    private function findUserByCanvasUserId(?string $canvasUserId): ?User
    {
        if (key_exists($canvasUserId, $this->canvasUserIdUserMap)) {
            return $this->canvasUserIdUserMap[$canvasUserId];
        }

        $sql = "SELECT sis_user_id, unique_name FROM pseudonym_dim
                 WHERE workflow_state = 'active'
                   AND user_id = :userId";

        $statement = $this->canvasDb->executeQuery($sql, ['userId' => $canvasUserId]);

        if ($canvasUser = $statement->fetch()) {
            // Try sis user id
            try {
                $this->canvasUserIdUserMap[$canvasUserId] = $this->userMapper->findByExternalId($canvasUser['sis_user_id']);
                return $this->canvasUserIdUserMap[$canvasUserId];
            } catch (Mapper\Exception $e) {
            }

            // Try email address
            try {
                $emailUsers = $this->userMapper->findByEmail($canvasUser['unique_name']);
                foreach ($emailUsers as $user) {
                    $this->canvasUserIdUserMap[$canvasUserId] = $user;
                    return $this->canvasUserIdUserMap[$canvasUserId];
                }
            } catch (Mapper\Exception $e) {
            }
        }

        // If failed to find, return admin
        if (empty($this->schoolboxAdmin)) {
            $this->schoolboxAdmin = $this->userMapper->findById($this->getSchoolboxAdminUserId());
        }
        $this->canvasUserIdUserMap[$canvasUserId] = $this->schoolboxAdmin;
        return $this->canvasUserIdUserMap[$canvasUserId];
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

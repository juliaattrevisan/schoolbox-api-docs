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
use External\Canvas\Sync\Bridge\CourseSection;
use Psr\Log\LoggerInterface;
use Schoolbox\Assessment\Component\SubmissionBoxMapper;
use Schoolbox\Assessment\Component\SubmissionBox\ReturnedWorkMapper;
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
 *
 */
class Submission extends SectionAssignment
{
    use ModelSaveTrait;

    /** @var LoggerInterface */
    private $log;

    /** @var CourseSection */
    private $courseSectionBridge;

    /** @var IConnector */
    private $canvasDb;

    /** @var IConnector */
    private $schoolboxDb;

    /** @var SubmissionBoxMapper */
    private $submissionBoxMapper;

    /** @var ReturnedWorkMapper */
    private $returnedWorkMapper;

    /** @var Assessment\MapperProxy */
    private $assessmentDataSource;

    /** @var UserMapper */
    private $userMapper;

    /** @var array */
    private $dueWorkPlanCache;

    /** @var int */
    private $syncMarkCount;

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

        $this->canvasDb             = $canvasDb;
        $this->schoolboxDb          = $schoolboxDb;
        $this->submissionBoxMapper  = $submissionBoxMapper;
        $this->returnedWorkMapper   = $returnedWorkMapper;
        $this->assessmentDataSource = $assessmentDataSource;
        $this->userMapper           = $userMapper;
        $this->log                  = $log;
        $this->courseSectionBridge  = $courseSectionBridge;
    }


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
                $sbSubmissionBoxSpec = $this->submissionBoxMapper->findOneByColumn('external_id', $assessmentCanvasId);

                $assessments       = $this->assessmentDataSource->findAllImplementationsBySpecificationId($sbSubmissionBoxSpec->getAssessmentModel()->getId());
                $canvasSubmissions = $this->loadCanvasSubmissions($assessmentCanvasId);
                $this->log->info("Processing " . count($canvasSubmissions) . " submissions for Canvas Assignment '{$canvasAssessment['title']}' (CanvasId: $assessmentCanvasId)");

                // reset plan cache
                $this->dueWorkPlanCache = [];
                foreach ($canvasSubmissions as $canvasSubmission) {
                    $sbAssessments = $this->findCorrespondingAssessments($canvasSubmission, $assessments);
                    foreach ($sbAssessments as $sbAssessment) {

                        try {
                            $reviewee = $this->userMapper->findByExternalId($canvasSubmission['reviewee_id']);
                        } catch (Mapper\Exception $e) {
                            // If the reviewee (i.e. student) does not exist, skip this canvas submission
                            $this->log->debug("Reviewee (ExternalId: {$canvasSubmission['reviewee_id']}) of canvas submission {$canvasSubmission['submission_id']} does not exist in Schoolbox");
                            continue;
                        }

                        try {
                            $reviewer = $this->userMapper->findByExternalId($canvasSubmission['reviewer_id']);
                        } catch (Mapper\Exception $e) {
                            $reviewer = $this->userMapper->findById($this->getSchoolboxAdminUserId());
                        }
                        $this->log->debug("Synchronising Canvas mark for {$reviewee->getFullName()}[{$reviewee->getId()}] for {$sbAssessment->getTitle()}[{$sbAssessment->getId()}]");

                        $submissionBox = $this->submissionBoxMapper->findOneByColumn('assessment_id', $sbAssessment->getId());

                        // ReturnWork version
                        /** @var SubmissionBox\ReturnedWork $feedback */
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
                        $this->saveModel($this->returnedWorkMapper, $feedback, false, false);
                        $this->syncMarkCount++;
                    }
                }
            }
        }

        $this->log->debug("{$this->syncMarkCount} marks processed.");
    }

    /**
     * @inheritDoc
     */
    protected function resetCounts(): void
    {
        $this->syncMarkCount = 0;
    }

    /**
     * Fetch canvas submissions (include marks) related to the given assignment
     * @param $assignmentCanvasId
     * @return array
     */
    private function loadCanvasSubmissions($assignmentCanvasId): array
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
            LEFT JOIN pseudonym_dim as reviewer ON submission_dim.grader_id = reviewer.user_id = submission_dim.grader_id AND reviewer.workflow_state = 'active'
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

    /**
     * Finds schoolbox objects to associate the canvas submission with
     * @param $canvasSubmission
     * @param $dueWorks
     * @return IAssessable[]
     */
    private function findCorrespondingAssessments($canvasSubmission, $dueWorks): array
    {
        /** @var IAssessable[] $assessments */
        $assessments = [];
        foreach ($dueWorks as $dueWork) {
            /** @var IAssessable $dueWork */
            if (!key_exists($dueWork->getId(), $this->dueWorkPlanCache)) {
                $dueWorkCollection = $this->assessmentDataSource->createCollection()->add($dueWork);
                $result            = $this->assessmentDataSource->loadParticipantsIntoAssessmentCollection($dueWorkCollection);
                foreach ($result as $dueWorkWithParticipants) {
                    // should be only one
                    $dueWork = $dueWorkWithParticipants;
                    $this->dueWorkPlanCache[$dueWork->getId()] = $dueWorkWithParticipants->getPlans();
                }
            }
            foreach ($this->dueWorkPlanCache[$dueWork->getId()] as $plan) {
                /** @var Participant\Plan\Model $plan */
                foreach ($plan->getParticipants() as $participant) {
                    if ($canvasSubmission['reviewee_id'] === $participant->getUser()->getExternalId()) {
                        $assessments[] = $dueWork;
                    }
                }
            }
        }
        return $assessments;
    }
}

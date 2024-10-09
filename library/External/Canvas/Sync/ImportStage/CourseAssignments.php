<?php

/**
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace External\Canvas\Sync\ImportStage;

use Alaress\Database;
use Alaress\Database\Connector\IConnector;
use Alaress\Database\Predicate\Builder;
use External\Canvas\Importer\CanvasDb;
use External\Canvas\Sync\Bridge;
use External\Canvas\Sync\Helpers\CourseTeachers;
use External\Canvas\Sync\ImportStage\Traits\ModelSaveTrait;
use InvalidArgumentException;
use PDOException;
use Psr\Log\LoggerInterface;
use Schoolbox\Assessment\Component\SubmissionBox;
use Schoolbox\Assessment\Component\SubmissionBoxMapper;
use Schoolbox\IConfigLoader;
use Schoolbox\Learning\Assessment\DataModel as Assessment;
use Schoolbox\Learning\Assessment\WorkType;
use Schoolbox\Learning\Course\DataModel\Specification\Unit as UnitSpec;
use Schoolbox\Mapper;
use Schoolbox\Resource\Folder;
use Schoolbox\User;
use UnderflowException;
use UnexpectedValueException;

use function array_key_exists;
use function array_keys;
use function count;

/**
 * Responsible for importing Canvas Assignments into Schoolbox Course SubmissionBox Specs
 */
class CourseAssignments extends CourseAssessmentAbstract
{
    use ModelSaveTrait;

    /** @var Bridge\Account */
    private Bridge\Account $accountBridge;

    // Assignments => SubmissionBox
    private int $countAssignmentsSynchronised;
    private int $countSubmissionBoxCreated;
    private int $countSubmissionBoxUpdated;
    private int $countSubmissionBoxMoved;
    private int $countSubmissionBoxDeleted;


    /**
     * @param CanvasDb            $canvasDb
     * @param IConnector          $schoolboxDb
     * @param SubmissionBoxMapper $submissionBoxMapper
     * @param Assessment\Mapper   $assessmentMapper
     * @param LoggerInterface     $log
     * @param IConfigLoader       $config
     * @param Bridge\Assignment   $assignmentBridge
     * @param Bridge\Course       $courseBridge
     * @param Folder\Mapper       $folderMapper
     * @param string              $schoolboxTimezone
     */
    public function __construct(
        CanvasDb $canvasDb,
        IConnector $schoolboxDb,
        private SubmissionBoxMapper $submissionBoxMapper,
        Assessment\Mapper $assessmentMapper,
        LoggerInterface $log,
        IConfigLoader $config,
        private Bridge\Assignment $assignmentBridge,
        Bridge\Course $courseBridge,
        private Folder\Mapper $folderMapper,
        string $schoolboxTimezone
    ) {
        parent::__construct(
            $canvasDb,
            $schoolboxDb,
            $assessmentMapper,
            $log,
            $config,
            $courseBridge,
            $schoolboxTimezone
        );
    }


    /**
     * @throws Database\Exception
     * @throws Database\Predicate\InvalidColumnException
     * @throws InvalidArgumentException
     * @throws Mapper\Exception
     * @throws UnderflowException
     * @throws UnexpectedValueException
     * @throws PDOException
     */
    public function synchronise(): void
    {
        $this->log->notice("Synchronising Canvas Assignments => Schoolbox SubmissionBoxSpec...");
        $this->resetCounts();

        $canvasCourseIds   = $this->getCanvasCourseIdsToImport();
        $courseFilterPb    = (new Builder($this->canvasDb->getConnector()->getQuoter(), ''))->in('course.id', $canvasCourseIds);
        $courseFilterWhere = $courseFilterPb->getWhere()[0];

        $sql               = "
        SELECT
               course.id                   'course.canvas_id',
               course.name                 'course.name',
               assignment.id               'assignment.canvas_id',
               assignment.title            'assignment.title',
               assignment.description      'assignment.description',
               assignment.points_possible  'assignment.points_possible',
               assignment.grading_type     'assignment.grading_type',
               assignment.submission_types 'assignment.submission_types',
               assignment.created_at,
               CONVERT_TZ(assignment.created_at, 'UTC', :timezone) 'assignment.created_at',
               CONVERT_TZ(assignment.updated_at, 'UTC', :timezone) 'assignment.updated_at',
               null                        'assignment.muted',
               assignment_group.name       'assignment_group.name'
        FROM courses course
        JOIN assignments assignment ON assignment.context_id = course.id AND assignment.workflow_state = 'published' AND assignment.context_type = 'Course'
        LEFT JOIN assignment_groups assignment_group on assignment.assignment_group_id = assignment_group.id
        LEFT JOIN quizzes quiz ON quiz.assignment_id = assignment.id
        WHERE {$courseFilterWhere} AND quiz.id IS NULL
        ORDER BY course.course_code, course.id, assignment_group.position, assignment.position
        ";

        if (!$this->canvasDb->isCanvasData2) {
            $courseFilterPb    = (new Builder($this->canvasDb->getConnector()->getQuoter(), ''))->in('course.canvas_id', $canvasCourseIds);
            $courseFilterWhere = $courseFilterPb->getWhere()[0];
            $sql               = "
        SELECT
               course.canvas_id            'course.canvas_id',
               course.name                 'course.name',
               assignment.canvas_id        'assignment.canvas_id',
               assignment.title            'assignment.title',
               assignment.description      'assignment.description',
               assignment.points_possible  'assignment.points_possible',
               assignment.grading_type     'assignment.grading_type',
               assignment.submission_types 'assignment.submission_types',
               CONVERT_TZ(assignment.created_at, 'UTC', :timezone) 'assignment.created_at',
               CONVERT_TZ(assignment.updated_at, 'UTC', :timezone) 'assignment.updated_at',
               assignment.muted            'assignment.muted',
               assignment_group.name       'assignment_group.name'
        FROM course_dim course
        JOIN assignment_dim assignment ON assignment.course_id = course.id AND assignment.workflow_state = 'published'
        LEFT JOIN assignment_group_dim assignment_group on assignment.assignment_group_id = assignment_group.id
        LEFT JOIN quiz_dim quiz ON quiz.assignment_id = assignment.id
        WHERE {$courseFilterWhere} AND quiz.id IS NULL
        ORDER BY course.code, course.canvas_id, assignment_group.position, assignment.position
        ";
        }

        $statement         = $this->canvasDb->getConnector()->executeQuery($sql, array_merge($courseFilterPb->getParams(), ['timezone' => $this->schoolboxTimezone]));

        // Parse results into hierarchical structure
        $coursesData        = [];
        $assessmentSequence = null;
        while ($row = $statement->fetch()) {
            $courseCanvasId     = (int)$row['course.canvas_id'];
            $assignmentCanvasId = (int)$row['assignment.canvas_id'];

            // First time we've seen course?
            if (!array_key_exists($courseCanvasId, $coursesData)) {
                $coursesData[$courseCanvasId] = [
                    'name'        => $row['course.name'],
                    'assignments' => [],
                ];
                $assessmentSequence           = 1; // TODO remove hard-coding in develop branch
            }

            // First time we've seen this assignment?
            if (!array_key_exists($assignmentCanvasId, $coursesData[$courseCanvasId]['assignments'])) {
                $coursesData[$courseCanvasId]['assignments'][$assignmentCanvasId] = [
                    'title'            => $row['assignment.title'],
                    'description'      => $row['assignment.description'],
                    'points_possible'  => $row['assignment.points_possible'] === null ? null : (float)$row['assignment.points_possible'],
                    'grading_type'     => $row['assignment.grading_type'],
                    'submission_types' => $row['assignment.submission_types'],
                    'created_at'       => $row['assignment.created_at'],
                    'updated_at'       => $row['assignment.updated_at'],
                    'muted'            => $row['assignment.muted'] === 'true',
                    // TODO this has been deprecated by Canvas, will always be NULL
                    'position'         => $assessmentSequence++,
                    'assignment_group' => $row['assignment_group.name']
                ];
            }
        }

        // Attempt to find a teacher for each course who we can set as the course's assessment owner
        // User must exist in Schoolbox, have roleType of 'staff', and have 'teacher-enrollment' to assignment's course
        $courseTeachers = new CourseTeachers($this->schoolboxDb, $this->canvasDb);
        foreach ($courseTeachers->getCourseTeacherMap(array_keys($coursesData)) as $courseCanvasId => $teacherUserId) {
            $coursesData[$courseCanvasId]['teacherSchoolboxId'] = $teacherUserId;
        }

        // Keep track of all entities synchronized.
        // We will later delete all entities in Schoolbox that appear to have been imported from Canvas
        // but appear to no longer exist.
        $synchronizedSubmissionBoxIds = [];

        // Fall back to setting SubmissionBoxSpec.owner to Schoolbox Admin if no course teacher found
        $schoolboxAdminUserId = $this->getSchoolboxAdminUserId();

        foreach ($coursesData as $courseCanvasId => $courseData) {
            $pb             = $this->courseBridge->equalsCanvasCourse($courseCanvasId);
            $unitSpecFolder = $this->folderMapper->findOneByPredicates($pb);
            $this->log->debug(sprintf("Importing Course Assignments into Unit: '%s'", $unitSpecFolder->getTitle()));

            foreach ($courseData['assignments'] as $assignmentCanvasId => $assignmentData) {
                if ($assignmentData['points_possible'] > 999) {
                    $this->log->warning("Max mark for $assignmentCanvasId is truncated from {$assignmentData['points_possible']} to 999");
                }
                $this->countAssignmentsSynchronised++;
                $submissionBox                  = $this->syncWithSubmissionBox(
                    $unitSpecFolder,
                    $assignmentCanvasId,
                    $courseData['teacherSchoolboxId'] ?? $schoolboxAdminUserId,
                    $assignmentData['title'],
                    $assignmentData['description'] ? $this->htmlPurifier->purify($assignmentData['description']) : '',
                    $assignmentData['points_possible'] > 999 ? 999 : $assignmentData['points_possible'],
                    $assignmentData['grading_type'],
                    $assignmentData['submission_types'],
                    $assignmentData['created_at'],
                    $assignmentData['updated_at'],
                    $assignmentData['muted'],
                    $assignmentData['position'],
                    $assignmentData['assignment_group']
                );
                $synchronizedSubmissionBoxIds[] = $submissionBox->getId();
            }
        }

        $this->deleteUnSynchronizedSubmissionBoxes($synchronizedSubmissionBoxIds);

        $this->printCounts();
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
     * Synchronises a Canvas Assignment to a Schoolbox SubmissionBox
     *
     * @param Folder\Model $unitSpecFolder
     * @param int          $canvasId
     * @param int          $courseTeacherSchoolboxUserId
     * @param string       $title
     * @param string|null  $description
     * @param float|null   $pointsPossible
     * @param string       $gradingType
     * @param string       $submissionTypes
     * @param string       $createdAt
     * @param string       $updatedAt
     * @param bool         $muted
     * @param int          $position
     * @param string|null  $assignmentGroup
     * @return SubmissionBox
     * @throws Mapper\Exception
     */
    private function syncWithSubmissionBox(
        Folder\Model $unitSpecFolder,
        int $canvasId,
        int $courseTeacherSchoolboxUserId,
        string $title,
        ?string $description,
        ?float $pointsPossible,
        string $gradingType,
        string $submissionTypes,
        string $createdAt,
        string $updatedAt,
        bool $muted,
        int $position,
        ?string $assignmentGroup
    ): SubmissionBox {
        if ($this->canvasDb->isCanvasData2) {
            // The format of submission types is changed in Data 2
            $submissionTypes = json_decode($submissionTypes);
        }
        // Translate Canvas attributes to SB attributes if not a 1:1 between systems
        $maxMark           = $this->assignmentBridge->getMaxMark($pointsPossible);
        $markType          = $this->assignmentBridge->getMarkType($gradingType, $pointsPossible);
        $gradeGroup        = $this->assignmentBridge->getGradeGroup($gradingType);
        $acceptOnline      = $this->assignmentBridge->getAcceptOnline($submissionTypes);
        $acceptMultiple    = $this->assignmentBridge->getAcceptMultiple($submissionTypes);
        $markVisibility    = $this->assignmentBridge->getMarkVisibility($muted);
        $selfReviewability = $this->assignmentBridge->getSelfReviewability();
        $workType          = $assignmentGroup ? $this->assignmentBridge->getSubmissionBoxWorkType($assignmentGroup) : null;
        $weight            = $this->assignmentBridge->getSubmissionBoxWeight();
        $submissionBox     = $this->findSubmissionBoxSpec($canvasId);

        // Not found in SB?
        if (!$submissionBox) {
            // Create and return it.
            $this->log->debug("Created new SubmissionBox Spec for Canvas Assignment '$title' (canvasId: $canvasId)");
            $this->countSubmissionBoxCreated++;
            return $this->createSubmissionBoxSpec(
                $unitSpecFolder,
                $canvasId,
                $courseTeacherSchoolboxUserId,
                $title,
                $description,
                $maxMark,
                $markType,
                $gradeGroup,
                $acceptOnline,
                $acceptMultiple,
                $weight,
                $createdAt,
                $updatedAt,
                $markVisibility,
                $position,
                $selfReviewability,
                $workType
            );
        }

        $this->log->debug("Synchronising Canvas Assignment(canvasId: $canvasId) with SubmissionBoxSpec (id: {$submissionBox->getId()})");

        // Already exists in SB. Do we need to update it?
        if ($this->checkAssessmentBelongsToUnitSpec($submissionBox->getAssessmentModel(), $unitSpecFolder->getUnitSpecification())) {
            $this->countSubmissionBoxMoved++;
        }

        $isAssessmentUpdated = $this->checkAssessmentForChanges(
            $submissionBox->getAssessmentModel(),
            $title,
            $description,
            $position,
            $workType,
            $weight,
            $createdAt,
            $updatedAt
        );

        $isBoxUpdated = false;
        if (
            // Does any attribute no longer align?
            (float)$submissionBox->getAssessmentModel()->getWeight() !== $weight ||
            $submissionBox->getMaxMark() !== $maxMark ||
            $submissionBox->getAcceptOnline() !== $acceptOnline ||
            $submissionBox->getAcceptMultiple() !== $acceptMultiple ||
            $submissionBox->getMarkVisibility() !== $markVisibility ||
            $submissionBox->getSelfReviewability() !== $selfReviewability ||
            $submissionBox->getMarkType()->getId() !== $markType->getId() ||
            $submissionBox->getGradeGroup()?->getId() !== $gradeGroup?->getId()
        ) {
            // Update all attributes and update
            $submissionBox
                ->setMaxMark($maxMark)
                ->setMarkType($markType)
                ->setGradeGroup($gradeGroup)
                ->setAcceptOnline($acceptOnline)
                ->setAcceptMultiple($acceptMultiple)
                ->setMarkVisibility($markVisibility)
                ->setSelfReviewability($selfReviewability);
            $this->submissionBoxMapper->save($submissionBox);
            $isBoxUpdated = true;
        }
        if ($isAssessmentUpdated || $isBoxUpdated) {
            $this->countSubmissionBoxUpdated++;
            $this->log->debug("SubmissionBox '$title' (canvasId: $canvasId) updated");
        }
        return $submissionBox;
    }


    /**
     * Find a SubmissionBox Spec for specified Canvas assignment.canvas_id
     *
     * @param int $canvasId
     * @return SubmissionBox|null
     * @throws Database\Exception
     * @throws UnexpectedValueException
     * @throws Mapper\Exception
     */
    private function findSubmissionBoxSpec(int $canvasId): ?SubmissionBox
    {
        // Hydrate SubmissionBox + Assessment + link to UnitSpec (so we can check if moved)
        $sql = "
            SELECT
                {$this->submissionBoxMapper->getColumnString('sb')},
                {$this->assessmentMapper->getColumnString('a')},
                acus.course_unit_specification_id,
                cus.folder_id
            FROM submission_box sb
            JOIN assessment a ON a.id = sb.assessment_id
            JOIN assessment_course_unit_specification acus ON a.id = acus.assessment_id
            JOIN course_unit_specification cus ON acus.course_unit_specification_id = cus.id
            WHERE a.deleted_at IS NULL AND sb.external_id = :externalId
        ";

        $rows = $this->schoolboxDb->executeQuery($sql, ['externalId' => $canvasId])->fetchAll();
        if (!count($rows)) {
            return null;
        }
        if (count($rows) > 1) {
            throw new UnexpectedValueException("Unable to uniquely identify a SubmissionBox spec for canvas id '$canvasId'");
        }

        $row = $rows[0];
        $assessment = $this->assessmentMapper->hydrate($row, 'a');
        $assessment->setContextFolder((new Folder\Model())->setId($row['folder_id']));
        $submissionBox = $this->submissionBoxMapper->hydrate($row, 'sb');
        $submissionBox->setAssessmentModel($assessment);
        $assessment->setSubObject($submissionBox);
        $assessment->setContextUnitSpecification((new UnitSpec\Model())->setId((int)$row['course_unit_specification_id']));

        return $submissionBox;
    }


    /**
     * Creates and returns a SubmissionBox spec.
     *
     * @param Folder\Model                              $unitSpecFolder
     * @param int                                       $canvasId
     * @param int                                       $boxOwnerId
     * @param string                                    $title
     * @param string|null                               $description
     * @param float|null                                $maxMark
     * @param SubmissionBox\MarkType                    $markType
     * @param SubmissionBox\GradeLevel\Group\Model|null $gradeGroup
     * @param bool                                      $acceptOnline
     * @param bool                                      $acceptMultiple
     * @param float                                     $weight
     * @param string                                    $createdAt
     * @param string                                    $updatedAt
     * @param int                                       $markVisibility
     * @param int                                       $position
     * @param string                                    $selfReviewability
     * @param WorkType\Model|null                       $workType
     * @return SubmissionBox
     * @throws Mapper\Exception
     */
    private function createSubmissionBoxSpec(
        Folder\Model $unitSpecFolder,
        int $canvasId,
        int $boxOwnerId,
        string $title,
        ?string $description,
        ?float $maxMark,
        SubmissionBox\MarkType $markType,
        ?SubmissionBox\GradeLevel\Group\Model $gradeGroup,
        bool $acceptOnline,
        bool $acceptMultiple,
        float $weight,
        string $createdAt,
        string $updatedAt,
        int $markVisibility,
        int $position,
        string $selfReviewability,
        ?WorkType\Model $workType
    ): SubmissionBox {
        $assessment = $this->createAssessment(
            $unitSpecFolder->getUnitSpecification(),
            $title,
            $description,
            $workType,
            $weight,
            $createdAt,
            $updatedAt,
            $position
        );

        $submissionBox = (new SubmissionBox())
            ->setAssessmentModel($assessment)
            ->setOwner((new User())->setId($boxOwnerId))
            ->setMaxMark($maxMark)
            ->setMarkType($markType)
            ->setGradeGroup($gradeGroup)
            ->setAcceptOnline($acceptOnline)
            ->setAcceptMultiple($acceptMultiple)
            ->setMarkVisibility($markVisibility)
            ->setSelfReviewability($selfReviewability)
            ->setExternalId((string)$canvasId);
        $submissionBox->getAssessmentModel()
                ->setContextFolder($unitSpecFolder)
                ->setWeight($weight);

        $this->submissionBoxMapper->save($submissionBox);

        return $submissionBox;
    }



    /**
     * Checks an assessment belongs to specified UnitSpec, moving it if required.
     *
     * @param Assessment\Model $assessment
     * @param UnitSpec\Model   $unitSpec
     * @return bool
     * @throws Database\Exception
     */
    private function checkAssessmentBelongsToUnitSpec(Assessment\Model $assessment, UnitSpec\Model $unitSpec): bool
    {
        // Check if any updates are required (including moving to new context)
        if ($assessment->getContextUnitSpecification()->getId() === $unitSpec->getId()) {
            return false;
        }

        $oldUnitSpecId = $assessment->getContextUnitSpecification()->getId();
        $newUnitSpecId = $unitSpec->getId();
        $this->log->debug("Assessment Spec moved from (unitSpecId: $oldUnitSpecId) to (unitSpecId: $newUnitSpecId)");
        $this->moveAssessmentToUnitSpec($assessment->getId(), $unitSpec->getId());
        $assessment->setContextUnitSpecification($unitSpec);

        return true;
    }

    /**
     * Moves an assessment from its current unitSpec to the specified unitSpec
     *
     * @param int $assessmentId
     * @param int $unitSpecId
     * @throws Database\Exception
     */
    private function moveAssessmentToUnitSpec(int $assessmentId, int $unitSpecId): void
    {
        $sql = "
            UPDATE assessment_course_unit_specification
            SET course_unit_specification_id = :unitSpecId
            WHERE assessment_id = :assessmentId
        ";
        $this->schoolboxDb->executeQuery($sql, ['unitSpecId' => $unitSpecId, 'assessmentId' => $assessmentId]);
    }


    /**
     * @param array $synchronizedSubmissionBoxIds
     * @throws Database\Predicate\InvalidColumnException
     * @throws Mapper\Exception
     * @throws UnderflowException
     */
    private function deleteUnSynchronizedSubmissionBoxes(array $synchronizedSubmissionBoxIds): void
    {
        $pb = $this->submissionBoxMapper->getPredicateBuilder()
                                        ->notNull('external_id')
                                        ->isNull('am.deleted_at');
        if (count($synchronizedSubmissionBoxIds)) {
            $pb->notIn('id', $synchronizedSubmissionBoxIds);
        }

        foreach ($this->submissionBoxMapper->findByPredicates($pb) as $submissionBox) {
            $this->log->debug("Deleting un-synchronised assignment '{$submissionBox->getAssessmentModel()->getTitle()}' (boxId: {$submissionBox->getId()})");
            $this->submissionBoxMapper->delete($submissionBox);
            $this->countSubmissionBoxDeleted++;
        }
    }


    /**
     * Reset all counts to 0
     */
    private function resetCounts(): void
    {
        $this->countAssignmentsSynchronised = 0;
        $this->countSubmissionBoxCreated    = 0;
        $this->countSubmissionBoxUpdated    = 0;
        $this->countSubmissionBoxMoved      = 0;
        $this->countSubmissionBoxDeleted    = 0;
    }


    /**
     * Print counts of things checked/changes
     */
    private function printCounts(): void
    {
        $this->log->notice($this->countAssignmentsSynchronised . ' Canvas assignments synchronised');
        $this->log->notice($this->countSubmissionBoxCreated . ' Schoolbox SubmissionBox Specs created');
        $this->log->notice($this->countSubmissionBoxUpdated . ' Schoolbox SubmissionBox Specs updated');
        $this->log->notice($this->countSubmissionBoxMoved . ' Schoolbox SubmissionBox Specs moved');
        $this->log->notice($this->countSubmissionBoxDeleted . ' Schoolbox SubmissionBox Specs deleted');
    }
}

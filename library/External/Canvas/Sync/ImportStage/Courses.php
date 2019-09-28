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
use Alaress\Database\Predicate\InvalidColumnException;
use Alaress_Date_Format;
use External\Canvas\Sync\Bridge;
use InvalidArgumentException;
use Psr\Log\LoggerInterface;
use Schoolbox\Assessment\Component\SubmissionBox;
use Schoolbox\Assessment\Component\SubmissionBoxMapper;
use Schoolbox\Learning\Assessment\DataModel as Assessment;
use Schoolbox\Learning\Course\DataModel\Specification as CourseSpec;
use Schoolbox\Learning\Course\DataModel\Specification\Unit as UnitSpec;
use Schoolbox\Mapper;
use Schoolbox\Resource\Folder;
use Schoolbox\Role;
use Schoolbox\User;
use UnderflowException;
use UnexpectedValueException;
use function array_key_exists;
use function count;

/**
 * Responsible for importing Canvas Courses + Assignments
 *
 * Note this stage can execute prior to class folders being created in Schoolbox
 *
 * Creates the following Schoolbox entities:
 * * CourseSpec + CourseSpec.Folder
 * * UnitSpec + UnitSpec.Folder
 * * AssessmentSpec
 */
class Courses
{

    /** @var IConnector */
    private $canvasDb;

    /** @var IConnector */
    private $schoolboxDb;

    /** @var LoggerInterface */
    private $log;

    /** @var Bridge\Account */
    private $accountBridge;

    /** @var Bridge\CourseGroup */
    private $courseGroupBridge;

    /** @var Bridge\Course */
    private $courseBridge;

    /** @var Bridge\Assignment */
    private $assignmentBridge;

    /** @var SubmissionBoxMapper */
    private $submissionBoxMapper;

    /** @var Assessment\Mapper */
    private $assessmentMapper;

    /** @var Folder\Mapper */
    private $folderMapper;

    // CourseGroups => CourseSpec
    private $countCourseGroupsSynchronised;
    private $countCourseGroupsCreated;
    private $countCourseGroupsUpdated;
    private $countCourseGroupsMoved;
    private $countCourseGroupsDeleted;

    // Courses => UnitSpecs
    private $countCoursesSynchronised;
    private $countCoursesCreated;
    private $countCoursesUpdated;
    private $countCoursesMoved;
    private $countCoursesDeleted;

    // Assignments => SubmissionBox
    private $countAssignmentsSynchronised;
    private $countSubmissionBoxCreated;
    private $countSubmissionBoxUpdated;
    private $countSubmissionBoxMoved;
    private $countSubmissionBoxDeleted;


    /**
     * @param IConnector          $canvasDb
     * @param IConnector          $schoolboxDb
     * @param Bridge\Assignment   $assignmentBridge
     * @param SubmissionBoxMapper $submissionBoxMapper
     * @param Assessment\Mapper   $assessmentMapper
     * @param Folder\Mapper       $folderMapper
     * @param LoggerInterface     $log
     * @param Bridge\Account      $accountBridge
     * @param Bridge\Course       $courseBridge
     * @param Bridge\CourseGroup  $courseGroupBridge
     */
    public function __construct(
        IConnector $canvasDb,
        IConnector $schoolboxDb,
        SubmissionBoxMapper $submissionBoxMapper,
        Assessment\Mapper $assessmentMapper,
        Folder\Mapper $folderMapper,
        LoggerInterface $log,
        Bridge\Account $accountBridge,
        Bridge\Assignment $assignmentBridge,
        Bridge\Course $courseBridge,
        Bridge\CourseGroup $courseGroupBridge
    ) {
        $this->canvasDb            = $canvasDb;
        $this->schoolboxDb         = $schoolboxDb;
        $this->log                 = $log;
        $this->assignmentBridge    = $assignmentBridge;
        $this->submissionBoxMapper = $submissionBoxMapper;
        $this->assessmentMapper    = $assessmentMapper;
        $this->folderMapper        = $folderMapper;
        $this->accountBridge       = $accountBridge;
        $this->courseGroupBridge   = $courseGroupBridge;
        $this->courseBridge        = $courseBridge;
    }


    /**
     * @throws Database\Exception
     * @throws Database\Predicate\InvalidColumnException
     * @throws InvalidArgumentException
     * @throws Mapper\Exception
     * @throws UnderflowException
     * @throws UnexpectedValueException
     */
    public function synchronise(): void
    {
        $this->log->info("Synchronising Canvas Courses + Assignments => Schoolbox CourseSpec, UnitSpec, SubmissionBoxSpec...");
        $this->resetCounts();

        // Fetch all relevant courses and their assignments
        // If multiple courses with same code over multiple years, split into timeless courseSpec + per-time unitSpec

        $sql       = "
        SELECT 
               account.canvas_id           'account.canvas_id',
               course.name                 'course.name',
               course.canvas_id            'course.canvas_id',
               course.code                 'course.code',
               enrollment_term.name        'enrollment_term.name',
               assignment.canvas_id        'assignment.canvas_id',
               assignment.title            'assignment.title',
               assignment.description      'assignment.description',
               assignment.points_possible  'assignment.points_possible',
               assignment.grading_type     'assignment.grading_type',
               assignment.submission_types 'assignment.submission_types',
               assignment.created_at       'assignment.created_at',
               assignment.updated_at       'assignment.updated_at',
               assignment.muted            'assignment.muted',
               assignment.position         'assignment.position'
        FROM course_dim course
        JOIN enrollment_term_dim enrollment_term ON enrollment_term.id = course.enrollment_term_id
        JOIN account_dim account ON course.account_id = account.id
        LEFT JOIN assignment_dim assignment ON assignment.course_id = course.id AND assignment.workflow_state != 'deleted'
        WHERE
              course.name LIKE '%notif%' AND
              course.workflow_state != 'deleted'
        ORDER BY account.canvas_id, course.code, enrollment_term.date_start, course.canvas_id, assignment.id DESC
        ";
        $statement = $this->canvasDb->executeQuery($sql);

        // Parse results into hierarchical structure
        $accountsData = [];
        while ($row = $statement->fetch()) {
            $accountCanvasId    = (int)$row['account.canvas_id'];
            $courseCode         = $row['course.code'];
            $courseCanvasId     = (int)$row['course.canvas_id'];
            $assignmentCanvasId = $row['assignment.canvas_id'] === null ? null : (int)$row['assignment.canvas_id'];

            // First time we've seen this account?
            if (!array_key_exists($accountCanvasId, $accountsData)) {
                $accountsData[$accountCanvasId] = [
                    'courseGroups' => [],
                ];
            }
            // First time we've seen course group?
            if (!array_key_exists($courseCode, $accountsData[$accountCanvasId]['courseGroups'])) {
                $accountsData[$accountCanvasId]['courseGroups'][$courseCode] = [
                    'courses' => [],
                ];
            }
            // First time we've seen course?
            if (!array_key_exists($courseCanvasId, $accountsData[$accountCanvasId]['courseGroups'][$courseCode]['courses'])) {
                $accountsData[$accountCanvasId]['courseGroups'][$courseCode]['courses'][$courseCanvasId] = [
                    'name'        => $row['course.name'],
                    'termName'    => $row['enrollment_term.name'],
                    'assignments' => [],
                ];
            }
            // Did we get assignment data?
            if ($assignmentCanvasId !== null) {
                $accountsData[$accountCanvasId]['courseGroups'][$courseCode]['courses'][$courseCanvasId]['assignments'][$assignmentCanvasId] = [
                    'title'            => $row['assignment.title'],
                    'description'      => $row['assignment.description'],
                    'points_possible'  => $row['assignment.points_possible'] === null ? null : (float)$row['assignment.points_possible'],
                    'grading_type'     => $row['assignment.grading_type'],
                    'submission_types' => $row['assignment.submission_types'],
                    'created_at'       => $row['assignment.created_at'],
                    'updated_at'       => $row['assignment.updated_at'],
                    'muted'            => $row['assignment.muted'] === 'true',
                    'position'         => (int)$row['assignment.position'],
                ];
            }
        }

        // Attempt to find a teacher for each course who we can set as the course's assessment owner
        // User must exist in Schoolbox, have roleType of 'staff', and have 'teacher-enrollment' to assignment's course
        $schoolboxStaffExternalIdToUserIdMap = $this->getSchoolboxStaffExternalIds();
        $schoolboxStaffExternalIds           = array_keys($schoolboxStaffExternalIdToUserIdMap);
        if (count($schoolboxStaffExternalIds)) {
            $bindings  = Builder::getBindings($schoolboxStaffExternalIds, 'id');
            $sql       = "
                SELECT
                    account.canvas_id     'account.canvas_id',
                    course.code           'course.code',
                    course.canvas_id      'course.canvas_id',
                    pseudonym.sis_user_id 'pseudonym.sis_user_id'
                FROM course_dim course
                JOIN account_dim account ON course.account_id = account.id
                JOIN enrollment_dim enrollment ON enrollment.course_id = course.id
                JOIN pseudonym_dim pseudonym ON pseudonym.user_id = enrollment.user_id
                WHERE
                      course.name LIKE '%notif%' AND
                      course.workflow_state = 'published' AND       -- We exclude 'unpublished' (aka draft), and 'deleted' assignments
                      enrollment.type = 'TeacherEnrollment' AND
                      pseudonym.sis_user_id IN ({$bindings->getPlaceholder()})
                GROUP BY course.id
            ";
            $statement = $this->canvasDb->executeQuery($sql, $bindings->getParams());
            while ($row = $statement->fetch()) {
                $accountCanvasId                                                                                               = (int)$row['account.canvas_id'];
                $courseCode                                                                                                    = $row['course.code'];
                $courseCanvasId                                                                                                = (int)$row['course.canvas_id'];
                $courseTeacherSisId                                                                                            = $row['pseudonym.sis_user_id'];
                $courseTeacherUserId                                                                                           = $schoolboxStaffExternalIdToUserIdMap[$courseTeacherSisId];
                $accountsData[$accountCanvasId]['courseGroups'][$courseCode]['courses'][$courseCanvasId]['teacherSchoolboxId'] = (int)$courseTeacherUserId;
            }
        }

        // Keep track of all entities synchronized.
        // We will later delete all entities in Schoolbox that appear to have been imported from Canvas
        // but appear to no longer exist.
        $synchronizedCourseGroupFolderIds = [];
        $synchronizedCourseFolderIds      = [];
        $synchronizedSubmissionBoxIds     = [];

        // Fall back to setting SubmissionBoxSpec.owner to Schoolbox Admin if no course teacher found
        $schoolboxAdminUserId = $this->getSchoolboxAdminUserId();

        foreach ($accountsData as $accountCanvasId => $accountData) {
            foreach ($accountData['courseGroups'] as $courseGroupCode => $courseGroupData) {
                $mostRecentCourse                   = end($courseGroupData['courses']);
                $mostRecentCourseName               = $mostRecentCourse['name'];
                $courseSpecFolder                   = $this->processCanvasCourseGroup($accountCanvasId, $mostRecentCourseName, $courseGroupCode);
                $synchronizedCourseGroupFolderIds[] = $courseSpecFolder->getId();

                foreach ($courseGroupData['courses'] as $courseCanvasId => $courseData) {
                    $unitSpecFolder                = $this->processCanvasCourse($courseSpecFolder, $courseCanvasId, $courseData['termName']);
                    $synchronizedCourseFolderIds[] = $unitSpecFolder->getId();

                    foreach ($courseData['assignments'] as $assignmentCanvasId => $assignmentData) {
                        $submissionBox                  = $this->processCanvasAssignment(
                            $unitSpecFolder,
                            $assignmentCanvasId,
                            $courseData['teacherSchoolboxId'] ?? $schoolboxAdminUserId,
                            $assignmentData['title'],
                            $assignmentData['description'],
                            $assignmentData['points_possible'],
                            $assignmentData['grading_type'],
                            $assignmentData['submission_types'],
                            $assignmentData['created_at'],
                            $assignmentData['updated_at'],
                            $assignmentData['muted'],
                            $assignmentData['position']
                        );
                        $synchronizedSubmissionBoxIds[] = $submissionBox->getId();
                    }
                }
            }
        }

        $this->deleteUnSynchronizedSubmissionBoxes($synchronizedSubmissionBoxIds);
        $this->deleteUnSynchronizedCourses($synchronizedCourseFolderIds);
        $this->deleteUnSynchronizedCourseGroups($synchronizedCourseGroupFolderIds);

        $this->printCounts();
    }


    /**
     * Return all user.ids of schoolbox users who are staff and have an externalId, indexed by user.synergyId
     *
     * @return int[]
     * @throws Database\Exception
     */
    private function getSchoolboxStaffExternalIds(): array
    {
        $sql = "
            SELECT u.id, u.synergy_id
            FROM user u
            JOIN `groups` g ON g.id = u.group_id
            WHERE
                  g.deleted_at IS NULL AND
                  u.deleted_at IS NULL AND
                  g.group_type = :roleType AND
                  u.synergy_id IS NOT NULL
        ";

        $externalIds = [];
        $statement   = $this->schoolboxDb->executeQuery($sql, ['roleType' => Role::STAFF]);
        while ($row = $statement->fetch()) {
            $externalIds[$row['synergy_id']] = $row['id'];
        }
        return $externalIds;
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
     * Synchronise a course group from canvas to schoolbox.
     *
     * @param int    $accountCanvasId      Enable us to locate the faculty folder where this course resides
     * @param string $mostRecentCourseName Most recent name of the Course in the CourseGroup
     * @param string $courseCode           Provides link to SB CourseSpec.folder.name
     * @return Folder\Model
     * @throws Database\Exception
     * @throws UnexpectedValueException
     * @throws Database\Predicate\InvalidColumnException
     * @throws Mapper\Exception
     */
    private function processCanvasCourseGroup(
        int $accountCanvasId,
        string $mostRecentCourseName,
        string $courseCode
    ): Folder\Model {
        $this->countCourseGroupsSynchronised++;

        $accountFolder = $this->findAccountFolder($accountCanvasId);
        $courseFolder  = $this->courseGroupBridge->findCourseSpecFolder($this->folderMapper, $accountCanvasId, $courseCode);

        // Create update Schoolbox CourseSpec
        if (!$courseFolder) {
            $courseFolder = $this->createCourseFolder($mostRecentCourseName, $courseCode, $accountFolder);
            $this->countCourseGroupsCreated++;
        } else {
            $updateRequired = false;
            if ($courseFolder->getParent()->getId() !== $accountFolder->getId()) {
                $this->countCourseGroupsMoved++;
                $this->log->debug("Folder '{$courseFolder->getName()}' moved from (folder.id: {$courseFolder->getParent()->getId()}) to (folder.id: {$accountFolder->getId()})");
                $courseFolder->setParent($accountFolder);
                $updateRequired = true;
            }
            $courseFolderName = $this->getCourseSpecFolderName($mostRecentCourseName, $courseCode);
            if ($courseFolder->getName() !== $courseFolderName) {
                $courseFolder->setName($courseFolderName);
                $this->countCourseGroupsUpdated++;
                $this->log->debug("Folder '{$courseFolder->getName()}' renamed to '$courseFolderName'");
                $updateRequired = true;
            }
            if ($updateRequired) {
                $this->updateFolder($courseFolder->getId(), $courseFolder->getName(), $courseFolder->getParent()->getId());
            }
        }
        return $courseFolder;
    }


    /**
     * Sync Canvas Course with Schoolbox UnitSpec
     *
     * @param Folder\Model $courseSpecFolder
     * @param int          $courseCanvasId
     * @param string       $termName
     * @return Folder\Model
     * @throws Database\Exception
     * @throws UnexpectedValueException
     * @throws InvalidColumnException
     */
    private function processCanvasCourse(
        Folder\Model $courseSpecFolder,
        int $courseCanvasId,
        string $termName
    ): Folder\Model {
        $this->countCoursesSynchronised++;

        $unitSpecFolder = $this->courseBridge->findUnitSpecFolder($courseCanvasId);
        if (!$unitSpecFolder) {
            $unitSpecFolder = $this->createUnitSpecFolder($courseSpecFolder, $courseCanvasId, $termName);
            $this->countCoursesCreated++;
        } else {
            $unitSpecFolderName = $this->getUnitSpecFolderName($termName, $courseCanvasId);
            $updateRequired     = false;
            if ($unitSpecFolder->getParent()->getId() !== $courseSpecFolder->getId()) {
                $updateRequired = true;
                $this->countCoursesMoved++;
                $this->log->debug("Folder '{$unitSpecFolder->getName()}' moved from (folder.id: {$unitSpecFolder->getParent()->getId()}) to (folder.id: {$courseSpecFolder->getId()})");
                $unitSpecFolder->setParent($courseSpecFolder);
            }
            if ($unitSpecFolder->getName() !== $unitSpecFolderName) {
                $updateRequired = true;
                $this->countCoursesUpdated++;
                $this->log->debug("Folder '{$unitSpecFolder->getName()}' renamed to '$unitSpecFolderName'");
                $unitSpecFolder->setName($unitSpecFolderName);
            }
            if ($updateRequired) {
                $this->updateFolder($unitSpecFolder->getId(), $unitSpecFolder->getName(), $unitSpecFolder->getParent()->getId());
            }
        }
        return $unitSpecFolder;
    }


    /**
     * Sync a Canvas Assignment with Schoolbox SubmissionBox Spec
     *
     * @param Folder\Model $unitSpecFolder
     * @param int          $canvasId
     * @param int          $courseTeacherSchoolboxUserId
     * @param string       $title
     * @param string|null  $description
     * @param int|float    $pointsPossible
     * @param string       $gradingType
     * @param string       $submissionTypes
     * @param string       $createdAt
     * @param string       $updatedAt
     * @param bool         $muted
     * @param int          $position
     * @return SubmissionBox
     * @throws Database\Exception
     * @throws InvalidArgumentException
     * @throws Mapper\Exception
     * @throws UnexpectedValueException
     */
    private function processCanvasAssignment(
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
        int $position
    ): SubmissionBox {
        $this->countAssignmentsSynchronised++;
        $submissionBox = $this->findSubmissionBoxSpec($canvasId);

        // Translate Canvas attributes to SB attributes if not a 1:1 between systems
        $maxMark           = $this->assignmentBridge->getMaxMark($pointsPossible);
        $markType          = $this->assignmentBridge->getMarkType($gradingType, $pointsPossible);
        $acceptOnline      = $this->assignmentBridge->getAcceptOnline($submissionTypes);
        $acceptMultiple    = $this->assignmentBridge->getAcceptMultiple($submissionTypes);
        $markVisibility    = $this->assignmentBridge->getMarkVisibility($muted);
        $selfReviewability = $this->assignmentBridge->getSelfReviewability();

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
                $acceptOnline,
                $acceptMultiple,
                $createdAt,
                $updatedAt,
                $markVisibility,
                $position,
                $selfReviewability
            );
        }

        $this->log->debug("Synchronising Canvas Assignment(canvasId: $canvasId) with SubmissionBoxSpec (id: {$submissionBox->getId()})");

        // Already exists in SB. Do we need to update it?
        $assessmentModel = $submissionBox->getAssessmentModel();
        // Check if any updates are required (including moving to new context)
        if ($assessmentModel->getContextUnitSpecification()->getId() !== $unitSpecFolder->getUnitSpecification()->getId()) {
            $this->countSubmissionBoxMoved++;
            $oldUnitSpecId = $assessmentModel->getContextUnitSpecification()->getId();
            $newUnitSpecId = $unitSpecFolder->getUnitSpecification()->getId();
            $this->log->debug("SubmissionBox Spec moved from (unitSpecId: $oldUnitSpecId) to (unitSpecId: $newUnitSpecId)");
            $this->moveAssessmentSpec($assessmentModel->getId(), $unitSpecFolder->getUnitSpecification()->getId());
            $assessmentModel->setContextUnitSpecification($unitSpecFolder->getUnitSpecification());
        }

        if (
            // Does any attribute no longer align?
            $assessmentModel->getTitle() !== $title ||
            $assessmentModel->getDescription() !== $description ||
            $submissionBox->getMaxMark() !== $maxMark ||
            $submissionBox->getAcceptOnline() !== $acceptOnline ||
            $submissionBox->getAcceptMultiple() !== $acceptMultiple ||
            $submissionBox->getMarkVisibility() !== $markVisibility ||
            $assessmentModel->getSequence() !== $position ||
            $submissionBox->getSelfReviewability() !== $selfReviewability ||
            $assessmentModel->getCreatedAt()->getDatabase() !== $createdAt ||
            $assessmentModel->getUpdatedAt()->getDatabase() !== $updatedAt ||
            (int)$submissionBox->getCategory()->getId() !== (int)$unitSpecFolder->getId() || // TODO remove hydration bug compensation in 19.1
            (int)$submissionBox->getMarkType()->getId() !== (int)$markType->getId()
        ) {
            // Update all attributes and update
            $this->log->debug("SubmissionBox '$title' (canvasId: $canvasId) updated");
            $this->countSubmissionBoxUpdated++;
            $submissionBox->setCategory($unitSpecFolder);
            $assessmentModel->setTitle($title);
            $assessmentModel->setDescription($description);
            $submissionBox->setMaxMark($maxMark);
            $submissionBox->setMarkType($markType);
            $submissionBox->setAcceptOnline($acceptOnline);
            $submissionBox->setAcceptMultiple($acceptMultiple);
            $assessmentModel->setCreatedAt(Alaress_Date_Format::newDatabaseDate($createdAt));
            $assessmentModel->setUpdatedAt(Alaress_Date_Format::newDatabaseDate($updatedAt));
            $submissionBox->setMarkVisibility($markVisibility);
            $assessmentModel->setSequence($position);
            $submissionBox->setSelfReviewability($selfReviewability);
            $this->updateSubmissionBox(
                $assessmentModel->getId(),
                $title,
                $description,
                $submissionBox->getCategory()->getId(),
                $maxMark,
                $markType->getId(),
                $acceptOnline,
                $acceptMultiple,
                $createdAt,
                $updatedAt,
                $markVisibility,
                $position,
                $selfReviewability
            );
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
                acus.course_unit_specification_id
            FROM submission_box sb
            JOIN assessment a ON a.id = sb.assessment_id
            JOIN assessment_course_unit_specification acus ON a.id = acus.assessment_id
            WHERE a.deleted_at IS NULL AND a.external_id = :externalId
        ";

        $rows = $this->schoolboxDb->executeQuery($sql, ['externalId' => $canvasId])->fetchAll();
        if (!count($rows)) {
            return null;
        }
        if (count($rows) > 1) {
            throw new UnexpectedValueException("Unable to uniquely identify a SubmissionBox spec for canvas id '$canvasId'");
        }

        $row        = $rows[0];
        $assessment = $this->assessmentMapper->hydrate($row, 'a');

        // TODO remove mapper hydration bug compensation in 19.1
        // Start bug compensation
        $assessment->setTask();
        $assessment->setProject();
        $assessment->setQuiz();
        // End bug compensation

        $submissionBox = $this->submissionBoxMapper->hydrate($row, 'sb');
        $submissionBox->setAssessmentModel($assessment);
        $assessment->setSubObject($submissionBox);
        $assessment->setContextUnitSpecification((new UnitSpec\Model())->setId((int)$row['course_unit_specification_id']));

        return $submissionBox;
    }


    /**
     * Creates and returns a SubmissionBox spec.
     *
     * @param Folder\Model           $unitSpecFolder
     * @param int                    $canvasId
     * @param int                    $boxOwnerId
     * @param string                 $title
     * @param string|null            $description
     * @param float|null             $maxMark
     * @param SubmissionBox\MarkType $markType
     * @param bool                   $acceptOnline
     * @param bool                   $acceptMultiple
     * @param string                 $createdAt
     * @param string                 $updatedAt
     * @param int                    $markVisibility
     * @param int                    $position
     * @param string                 $selfReviewability
     * @return SubmissionBox
     * @throws Database\Exception
     * @throws UnexpectedValueException
     */
    private function createSubmissionBoxSpec(
        Folder\Model $unitSpecFolder,
        int $canvasId,
        int $boxOwnerId,
        string $title,
        ?string $description,
        ?float $maxMark,
        SubmissionBox\MarkType $markType,
        bool $acceptOnline,
        bool $acceptMultiple,
        string $createdAt,
        string $updatedAt,
        int $markVisibility,
        int $position,
        string $selfReviewability
    ): SubmissionBox {
        $assessment = (new Assessment\Model())
            ->setContextUnitSpecification($unitSpecFolder->getUnitSpecification())
            ->setExternalId((string)$canvasId)
            ->setTitle($title)
            ->setDescription($description)
            ->setCreatedAt(Alaress_Date_Format::newDatabaseDate($createdAt))
            ->setUpdatedAt(Alaress_Date_Format::newDatabaseDate($updatedAt))
            ->setSequence($position);

        $submissionBox = (new SubmissionBox())
            ->setAssessmentModel($assessment)
            ->setOwner((new User())->setId($boxOwnerId))
            ->setMaxMark($maxMark)
            ->setCategory($unitSpecFolder)
            ->setMarkType($markType)
            ->setAcceptOnline($acceptOnline)
            ->setAcceptMultiple($acceptMultiple)
            ->setMarkVisibility($markVisibility)
            ->setSelfReviewability($selfReviewability);

        /* Cannot use mappers here to save because:
         * 1. We want to preserve Canvas create/update timestamps (alt. method would be to save via mapper, then raw query to override)
         * 2. Too many inappropriate pre/post save callbacks on mappers which cannot be altered in a master feature.
         */

        $sql = "
            INSERT INTO assessment (title, description, sequence, external_id, created_at, updated_at)
            VALUES (:title, :description, :sequence, :external_id, :created_at, :updated_at)
        ";
        $this->schoolboxDb->executeQuery($sql, [
            'title'       => $assessment->getTitle(),
            'description' => $assessment->getDescription(),
            'sequence'    => $assessment->getSequence(),
            'external_id' => $assessment->getExternalId(),
            'created_at'  => $assessment->getCreatedAt()->getDatabase(),
            'updated_at'  => $assessment->getUpdatedAt()->getDatabase(),
        ]);
        $assessment->setId((int)$this->schoolboxDb->lastInsertId());

        $sql = "INSERT INTO assessment_course_unit_specification (assessment_id, course_unit_specification_id) VALUES (:assessmentId, :unitSpecId)";
        $this->schoolboxDb->executeQuery($sql, ['assessmentId' => $assessment->getId(), 'unitSpecId' => $unitSpecFolder->getUnitSpecification()->getId()]);

        $sql = "
            INSERT INTO submission_box (assessment_id, category, owner, accept_submission, multi_submission, mark_type, max_mark, mark_visibility, self_reviewability) 
            VALUES (:assessment_id, :category, :owner, :accept_submission, :multi_submission, :mark_type, :max_mark, :mark_visibility, :self_reviewability)
        ";

        $this->schoolboxDb->executeQuery($sql, [
            'assessment_id'      => $assessment->getId(),
            'category'           => $unitSpecFolder->getId(),
            'owner'              => $submissionBox->getOwner()->getId(),
            'accept_submission'  => $submissionBox->getAcceptOnline(),
            'multi_submission'   => $submissionBox->getAcceptMultiple(),
            'mark_type'          => $submissionBox->getMarkType()->getId(),
            'max_mark'           => $submissionBox->getMaxMark(),
            'mark_visibility'    => $submissionBox->getMarkVisibility(),
            'self_reviewability' => $submissionBox->getSelfReviewability(),
        ]);
        $submissionBox->setId((int)$this->schoolboxDb->lastInsertId());

        return $submissionBox;
    }


    /**
     * Updates a Schoolbox submission_box/assessment
     *
     * @param int        $assessmentId
     * @param string     $title
     * @param string     $description
     * @param int        $folderId Old `submission_box`.`category` value
     * @param float|null $maxMark
     * @param int        $markTypeId
     * @param bool       $acceptOnline
     * @param bool       $acceptMultiple
     * @param string     $createdAt
     * @param string     $updatedAt
     * @param int        $markVisibility
     * @param int        $position
     * @param string     $selfReviewability
     * @throws Database\Exception
     */
    private function updateSubmissionBox(
        int $assessmentId,
        string $title,
        ?string $description,
        int $folderId,
        ?float $maxMark,
        int $markTypeId,
        bool $acceptOnline,
        bool $acceptMultiple,
        string $createdAt,
        string $updatedAt,
        int $markVisibility,
        int $position,
        string $selfReviewability
    ): void {
        $sql = "
            UPDATE assessment a
            JOIN submission_box sb ON sb.assessment_id = a.id
            SET
                a.title = :title,
                a.description = :description,
                sb.category = :category,
                sb.max_mark = :maxMark,
                sb.mark_type = :markType,
                sb.accept_submission = :acceptOnline,
                sb.multi_submission = :acceptMulti,
                sb.mark_visibility = :markVisibility,
                sb.self_reviewability = :selfReviewability,
                a.created_at = :createdAt,
                a.updated_at = :updatedAt,
                a.sequence = :sequence
            WHERE a.id = :assessmentId
        ";

        // Use raw query to ensure create/update dateTimes align to Canvas
        $this->schoolboxDb->executeQuery($sql, [
            'title'             => $title,
            'description'       => $description,
            'category'          => $folderId,
            'maxMark'           => $maxMark,
            'markType'          => $markTypeId,
            'acceptOnline'      => $acceptOnline,
            'acceptMulti'       => $acceptMultiple,
            'markVisibility'    => $markVisibility,
            'selfReviewability' => $selfReviewability,
            'createdAt'         => $createdAt,
            'updatedAt'         => $updatedAt,
            'sequence'          => $position,
            'assessmentId'      => $assessmentId,
        ]);
    }


    /**
     * Moves an assessment spec from its current unitSpec ot the specified unitSpec
     *
     * @param int $assessmentId
     * @param int $unitSpecId
     * @throws Database\Exception
     */
    private function moveAssessmentSpec(int $assessmentId, int $unitSpecId): void
    {
        $sql = "
            UPDATE assessment_course_unit_specification
            SET course_unit_specification_id = :unitSpecId
            WHERE assessment_id = :assessmentId
        ";
        $this->schoolboxDb->executeQuery($sql, ['unitSpecId' => $unitSpecId, 'assessmentId' => $assessmentId]);
    }


    /**
     * Returns the Schoolbox Folder that relates to a Canvas Account
     *
     * @param int $accountCanvasId
     * @return Folder\Model
     * @throws Database\Exception
     * @throws Database\Predicate\InvalidColumnException
     */
    private function findAccountFolder(int $accountCanvasId): Folder\Model
    {
        $accountFolderPb = $this->accountBridge->equalsAccountFolder($accountCanvasId, 'f');
        $accountFolderWhere = $accountFolderPb->getWhere()[0];

        $sql = "SELECT f.id, f.name FROM folder f WHERE {$accountFolderWhere} AND f.deleted_at IS NULL";
        $row = $this->schoolboxDb->executeQuery($sql, $accountFolderPb->getParams())->fetchAll()[0];
        return (new Folder\Model())->setId((int)$row['id'])->setName($row['name']);
    }


    /**
     * Create, persist + return a new CourseSpec+Folder that aligns to a Canvas CourseGroup
     *
     * @param string       $courseName
     * @param string       $courseCode
     * @param Folder\Model $accountFolder
     * @return Folder\Model
     * @throws Database\Exception
     * @throws UnexpectedValueException
     */
    private function createCourseFolder(string $courseName, string $courseCode, Folder\Model $accountFolder): Folder\Model
    {
        $folderName = $this->getCourseSpecFolderName($courseName, $courseCode);

        $folder = $this->createFolder($folderName, $accountFolder->getId())->setParent($accountFolder);

        $sql = "INSERT INTO course_specification(folder_id, created_at, updated_at) VALUES (:folder_id, NOW(), NOW())";
        $this->schoolboxDb->executeQuery($sql, ['folder_id' => $folder->getId()]);

        $this->log->debug("Created Schoolbox CourseSpec+Folder '$folderName' for Canvas CourseGroup (courseCode: '$courseCode')");

        return $folder->setCourseSpecification((new CourseSpec\Model())->setId((int)$this->schoolboxDb->lastInsertId()));
    }


    /**
     * Create, persist + return a new UnitSpec + Folder that aligns to a Canvas Course
     *
     * @param Folder\Model $courseSpecFolder
     * @param int          $courseCanvasId
     * @param string       $termName
     * @return Folder\Model
     * @throws Database\Exception
     */
    private function createUnitSpecFolder(Folder\Model $courseSpecFolder, int $courseCanvasId, string $termName): Folder\Model
    {
        $folderName = $this->getUnitSpecFolderName($termName, $courseCanvasId);
        $folder     = $this->createFolder($folderName, $courseSpecFolder->getId())->setParent($courseSpecFolder);

        $sql = "INSERT INTO course_unit_specification(folder_id, course_specification_id, created_at, updated_at) VALUES (:folderId, :courseSpecId, NOW(), NOW())";
        $this->schoolboxDb->executeQuery($sql, ['folderId' => $folder->getId(), 'courseSpecId' => $courseSpecFolder->getCourseSpecification()->getId()]);

        $this->log->debug("Created Schoolbox UnitSpec+Folder '$folderName' for Canvas Course (canvasId: $courseCanvasId)");

        return $folder->setUnitSpecification((new UnitSpec\Model())->setId((int)$this->schoolboxDb->lastInsertId()));
    }


    /**
     * Insert new folder and return new Folder model
     *
     * @param string $name
     * @param int    $parentId
     * @return Folder\Model
     * @throws Database\Exception
     */
    private function createFolder(string $name, int $parentId): Folder\Model
    {
        $sql = "INSERT INTO folder(name, parent, inherit) VALUES (:name, :parent, 1)";
        $this->schoolboxDb->executeQuery($sql, ['name' => $name, 'parent' => $parentId]);

        return (new Folder\Model())
            ->setId((int)$this->schoolboxDb->lastInsertId())
            ->setName($name);
    }


    /**
     * Generate a Schoolbox folder name for a given Canvas CourseName + CourseCode
     *
     * @param string $courseName
     * @param string $courseCode
     * @return string
     */
    private function getCourseSpecFolderName(string $courseName, string $courseCode): string
    {
        return $this->courseGroupBridge->generateLinkedFolderName($courseName, $courseCode);
    }


    /**
     * Generate a Schoolbox folder name for a given Canvas Course date + CanvasId
     *
     * @param string $termName
     * @param int    $courseCanvasId
     * @return string
     */
    private function getUnitSpecFolderName(string $termName, int $courseCanvasId): string
    {
        return $this->courseBridge->generateLinkedFolderName($termName, $courseCanvasId);
    }


    /**
     * Update a folder in the Schoolbox DB
     *
     * @param int    $id
     * @param string $name
     * @param int    $parentId
     * @throws Database\Exception
     */
    private function updateFolder(int $id, string $name, int $parentId): void
    {
        $sql = "UPDATE folder SET name = :name, parent = :parent WHERE id = :id";
        $this->schoolboxDb->executeQuery($sql, [
            'id'     => $id,
            'name'   => $name,
            'parent' => $parentId,
        ]);
    }


    /**
     * Delete any folder+courseSpec in SB that were previously imported from Canvas, but no longer exist in Canvas.
     *
     * @param array $synchronizedCourseGroupFolderIds
     * @throws Database\Predicate\InvalidColumnException
     * @throws Mapper\Exception
     * @throws UnderflowException
     */
    private function deleteUnSynchronizedCourseGroups(array $synchronizedCourseGroupFolderIds): void
    {
        $pb = $this->courseGroupBridge->equalsAnyCourseGroupFolder();
        if (count($synchronizedCourseGroupFolderIds)) {
            $pb->notIn('folder.id', $synchronizedCourseGroupFolderIds);
        }

        foreach ($this->folderMapper->findByPredicates($pb) as $folder) {
            $this->log->debug("Deleting un-synchronised course group folder '{$folder->getTitle()}' (id: {$folder->getId()})");
            $this->folderMapper->delete($folder);
            $this->countCourseGroupsDeleted++;
        }
    }


    /**
     * Delete any folder+unitSpec in SB that were previously imported from Canvas, but no longer exist in Canvas.
     *
     * @param array $synchronizedCourseFolderIds
     * @throws Database\Predicate\InvalidColumnException
     * @throws Mapper\Exception
     * @throws UnderflowException
     */
    private function deleteUnSynchronizedCourses(array $synchronizedCourseFolderIds): void
    {
        $pb = $this->courseBridge->equalsAnyCanvasCourse();
        if (count($synchronizedCourseFolderIds)) {
            $pb->notIn('id', $synchronizedCourseFolderIds);
        }

        foreach ($this->folderMapper->findByPredicates($pb) as $folder) {
            $this->log->debug("Deleting un-synchronised course folder '{$folder->getTitle()}' (id: {$folder->getId()})");
            $this->folderMapper->delete($folder);
            $this->countCoursesDeleted++;
        }
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
                                        ->notNull('am.external_id')
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
        $this->countCourseGroupsSynchronised = 0;
        $this->countCourseGroupsCreated      = 0;
        $this->countCourseGroupsUpdated      = 0;
        $this->countCourseGroupsMoved        = 0;
        $this->countCourseGroupsDeleted      = 0;

        $this->countCoursesSynchronised = 0;
        $this->countCoursesCreated      = 0;
        $this->countCoursesUpdated      = 0;
        $this->countCoursesMoved        = 0;
        $this->countCoursesDeleted      = 0;

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
        $this->log->info($this->countCourseGroupsSynchronised . ' Canvas CourseGroups synchronised');
        $this->log->info($this->countCourseGroupsCreated . ' Schoolbox CourseSpecs created');
        $this->log->info($this->countCourseGroupsUpdated . ' Schoolbox CourseSpecs updated');
        $this->log->info($this->countCourseGroupsMoved . ' Schoolbox CourseSpecs moved');
        $this->log->info($this->countCourseGroupsDeleted . ' Schoolbox CourseSpecs deleted');

        $this->log->info($this->countCoursesSynchronised . ' Canvas courses synchronised');
        $this->log->info($this->countCoursesCreated . ' Schoolbox UnitSpecs created');
        $this->log->info($this->countCoursesUpdated . ' Schoolbox UnitSpecs updated');
        $this->log->info($this->countCoursesMoved . ' Schoolbox UnitSpecs moved');
        $this->log->info($this->countCoursesDeleted . ' Schoolbox UnitSpecs deleted');

        $this->log->info($this->countAssignmentsSynchronised . ' Canvas assignments synchronised');
        $this->log->info($this->countSubmissionBoxCreated . ' Schoolbox SubmissionBox Specs created');
        $this->log->info($this->countSubmissionBoxUpdated . ' Schoolbox SubmissionBox Specs updated');
        $this->log->info($this->countSubmissionBoxMoved . ' Schoolbox SubmissionBox Specs moved');
        $this->log->info($this->countSubmissionBoxDeleted . ' Schoolbox SubmissionBox Specs deleted');
    }
}

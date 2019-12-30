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
use Evaluation_Section;
use External\Canvas\Sync\Bridge;
use External\Canvas\Sync\ImportStage\Traits\ModelSaveTrait;
use InvalidArgumentException;
use PDO;
use Psr\Log\LoggerInterface;
use Schoolbox\Assessment\Component\SubmissionBox;
use Schoolbox\Assessment\Component\SubmissionBoxMapper;
use Schoolbox\Learning\Assessment\DataModel as Assessment;
use Schoolbox\Learning\Assessment\WorkType;
use Schoolbox\Learning\Course\DataModel\Specification as CourseSpec;
use Schoolbox\Learning\Course\DataModel\Specification\Unit as UnitSpec;
use Schoolbox\Learning\Quiz;
use Schoolbox\Learning\Quiz\Section as QuizSection;
use Schoolbox\Learning\Quiz\Section\Question as QuizQuestion;
use Schoolbox\Learning\Quiz\Section\Question\QuestionType;
use Schoolbox\Learning\Quiz\Section\Question\Solution as QuizSolution;
use Schoolbox\Mapper;
use Schoolbox\Resource\Folder;
use Schoolbox\Role;
use Schoolbox\User;
use UnderflowException;
use UnexpectedValueException;
use function array_key_exists;
use function array_map;
use function count;
use function in_array;

/**
 * Responsible for importing Canvas Courses + Assignments
 *
 * Note this stage can execute prior to class folders being created in Schoolbox
 *
 * Maps the following entities from Canvas to Schoolbox:
 * * Courses are grouped by code into 'CourseGroups' => CourseSpec + UnitSpec
 * * Quiz/Assignment                                 => Quiz/SubmissionBox Spec
 */
class Courses
{
    use ModelSaveTrait;

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

    /** @var Quiz\Mapper */
    private $quizMapper;

    /** @var QuizSection\Mapper */
    private $quizSectionMapper;

    /** @var QuizQuestion\Mapper */
    private $quizQuestionMapper;

    /** @var QuestionType\Mapper */
    private $questionTypeMapper;

    /** @var QuizSolution\Mapper */
    private $quizSolutionMapper;

    /** @var SubmissionBoxMapper */
    private $submissionBoxMapper;

    /** @var Assessment\Mapper */
    private $assessmentMapper;

    /** @var Folder\Mapper */
    private $folderMapper;

    /** @var CourseSpec\Mapper */
    private $courseSpecMapper;

    /** @var UnitSpec\Mapper */
    private $unitSpecMapper;

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

    // Quiz => Quiz
    private $countQuizSynchronised;
    private $countQuizCreated;
    private $countQuizUpdated;
    private $countQuizMoved;
    private $countQuizDeleted;


    /**
     * @param IConnector          $canvasDb
     * @param IConnector          $schoolboxDb
     * @param Quiz\Mapper         $quizMapper
     * @param QuizSection\Mapper  $quizSectionMapper
     * @param QuizQuestion\Mapper $quizQuestionMapper
     * @param QuestionType\Mapper $quizQuestionTypeMapper
     * @param QuizSolution\Mapper $quizSolutionMapper
     * @param SubmissionBoxMapper $submissionBoxMapper
     * @param Assessment\Mapper   $assessmentMapper
     * @param Folder\Mapper       $folderMapper
     * @param CourseSpec\Mapper   $courseSpecMapper
     * @param UnitSpec\Mapper     $unitSpecMapper
     * @param LoggerInterface     $log
     * @param Bridge\Account      $accountBridge
     * @param Bridge\Assignment   $assignmentBridge
     * @param Bridge\Course       $courseBridge
     * @param Bridge\CourseGroup  $courseGroupBridge
     */
    public function __construct(
        IConnector $canvasDb,
        IConnector $schoolboxDb,
        Quiz\Mapper $quizMapper,
        QuizSection\Mapper $quizSectionMapper,
        QuizQuestion\Mapper $quizQuestionMapper,
        QuestionType\Mapper $quizQuestionTypeMapper,
        QuizSolution\Mapper $quizSolutionMapper,
        SubmissionBoxMapper $submissionBoxMapper,
        Assessment\Mapper $assessmentMapper,
        Folder\Mapper $folderMapper,
        CourseSpec\Mapper $courseSpecMapper,
        UnitSpec\Mapper $unitSpecMapper,
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
        $this->quizMapper          = $quizMapper;
        $this->quizSectionMapper   = $quizSectionMapper;
        $this->quizQuestionMapper  = $quizQuestionMapper;
        $this->questionTypeMapper  = $quizQuestionTypeMapper;
        $this->quizSolutionMapper  = $quizSolutionMapper;
        $this->submissionBoxMapper = $submissionBoxMapper;
        $this->assessmentMapper    = $assessmentMapper;
        $this->folderMapper        = $folderMapper;
        $this->courseSpecMapper    = $courseSpecMapper;
        $this->unitSpecMapper      = $unitSpecMapper;
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
        $this->log->notice("Synchronising Canvas Courses + Assignments => Schoolbox CourseSpec, UnitSpec, SubmissionBoxSpec...");
        $this->resetCounts();

        // Fetch all relevant courses and their assignments
        // If multiple courses with same code over multiple years, split into timeless courseSpec + per-time unitSpec

        // Note that we need to fetch similar columns from both `assignment` + `quiz` because a quiz may or may not
        // have an assignment, and an assignment may or may not have a quiz.

        $canvasCourseIds = $this->getCanvasCourseIdsToImport();
        $courseFilterPb = (new Builder($this->canvasDb, ''))->in('course.canvas_id', $canvasCourseIds);
        $courseFilterWhere = $courseFilterPb->getWhere()[0];
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
               quiz.canvas_id              'quiz.canvas_id',
               quiz.name                   'quiz.name',
               quiz.description            'quiz.description',
               quiz.created_at             'quiz.created_at',
               quiz.updated_at             'quiz.updated_at',
               quiz_fact.points_possible   'quiz.points_possible',
               quiz.quiz_type              'quiz.quiz_type',
               quiz.anonymous_submissions  'quiz.anonymous_submissions',
               quiz_fact.time_limit        'quiz_fact.time_limit',
               quiz_fact.allowed_attempts  'quiz_fact.allowed_attempts'
        FROM course_dim course
        JOIN enrollment_term_dim enrollment_term ON enrollment_term.id = course.enrollment_term_id
        JOIN account_dim account ON course.account_id = account.id
        LEFT JOIN assignment_dim assignment ON assignment.course_id = course.id AND assignment.workflow_state = 'published'
        LEFT JOIN assignment_group_dim assignment_group on assignment.assignment_group_id = assignment_group.id
        LEFT JOIN quiz_dim quiz ON quiz.course_id = course.id AND quiz.workflow_state = 'published'
        LEFT JOIN quiz_fact ON quiz_fact.quiz_id = quiz.id
        WHERE {$courseFilterWhere}
        -- TODO cater for quiz/assignment positioning
        ORDER BY account.canvas_id, course.code, enrollment_term.date_start, course.canvas_id, assignment_group.position, assignment.position
        ";
        $statement = $this->canvasDb->executeQuery($sql, $courseFilterPb->getParams());

        // Parse results into hierarchical structure
        $accountsData = [];
        $assessmentSequence = 1; // TODO remove hard-coding in develop branch
        while ($row = $statement->fetch()) {
            $accountCanvasId    = (int)$row['account.canvas_id'];
            $courseCode         = $row['course.code'];
            $courseCanvasId     = (int)$row['course.canvas_id'];
            $assignmentCanvasId = $row['assignment.canvas_id'] === null ? null : (int)$row['assignment.canvas_id'];
            $quizCanvasId       = $row['quiz.canvas_id'] === null ? null : (int)$row['quiz.canvas_id'];

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
                    'quizzes'     => [],
                    'assignments' => [],
                ];
            }
            // Did we get quiz data? (If we have both quiz + assignment data, treat as quiz)
            if(
                $quizCanvasId !== null &&
                !array_key_exists($quizCanvasId, $accountsData[$accountCanvasId]['courseGroups'][$courseCode]['courses'][$courseCanvasId]['quizzes'])
            ){
                $accountsData[$accountCanvasId]['courseGroups'][$courseCode]['courses'][$courseCanvasId]['quizzes'][$quizCanvasId] = [
                    'name'                  => $row['quiz.name'],
                    'description'           => $row['quiz.description'],
                    'points_possible'       => $row['quiz.points_possible'] === null ? null : (float)$row['quiz.points_possible'],
                    'created_at'            => $row['quiz.created_at'],
                    'updated_at'            => $row['quiz.updated_at'],
                    'muted'                 => $row['assignment.muted'] === 'true', // TODO this has been deprecated by Canvas, will always be NULL
                    'quiz_type'             => $row['quiz.quiz_type'],
                    'anonymous_submissions' => $row['quiz.anonymous_submissions'] === 'allow_anonymous_submissions',
                    'time_limit'            => $row['quiz_fact.time_limit'] === null ? null : (int)$row['quiz_fact.time_limit'],
                    'allowed_attempts'      => (int)$row['quiz_fact.allowed_attempts'],
                    'position'              => $assessmentSequence++,
                ];
            }
            // Did we get assignment w/o quiz data?
            if (
                $assignmentCanvasId !== null &&
//                !$quizCanvasId &&
                !array_key_exists($assignmentCanvasId, $accountsData[$accountCanvasId]['courseGroups'][$courseCode]['courses'][$courseCanvasId]['assignments'])
            ) {
                $accountsData[$accountCanvasId]['courseGroups'][$courseCode]['courses'][$courseCanvasId]['assignments'][$assignmentCanvasId] = [
                    'title'                 => $row['assignment.title'],
                    'description'           => $row['assignment.description'],
                    'points_possible'       => $row['assignment.points_possible'] === null ? null : (float)$row['assignment.points_possible'],
                    'grading_type'          => $row['assignment.grading_type'],
                    'submission_types'      => $row['assignment.submission_types'],
                    'created_at'            => $row['assignment.created_at'],
                    'updated_at'            => $row['assignment.updated_at'],
                    'muted'                 => $row['assignment.muted'] === 'true',  // TODO this has been deprecated by Canvas, will always be NULL
                    'position'              => $assessmentSequence++,
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
                WHERE {$courseFilterWhere} AND
                      enrollment.type = 'TeacherEnrollment' AND
                      pseudonym.sis_user_id IN ({$bindings->getPlaceholder()})
                GROUP BY course.id
            ";
            $statement = $this->canvasDb->executeQuery($sql, array_merge($bindings->getParams(), $courseFilterPb->getParams()));
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
        $synchronizedQuizIds              = [];

        // Fall back to setting SubmissionBoxSpec.owner to Schoolbox Admin if no course teacher found
        $schoolboxAdminUserId = $this->getSchoolboxAdminUserId();

        foreach ($accountsData as $accountCanvasId => $accountData) {
            foreach ($accountData['courseGroups'] as $courseGroupCode => $courseGroupData) {
                $mostRecentCourse                   = end($courseGroupData['courses']);
                if(!array_key_exists('name', $mostRecentCourse)){
                    $test = 1;
                }
                $mostRecentCourseName               = $mostRecentCourse['name'];
                $courseSpecFolder                   = $this->processCanvasCourseGroup($accountCanvasId, $mostRecentCourseName, $courseGroupCode);
                $synchronizedCourseGroupFolderIds[] = $courseSpecFolder->getId();

                foreach ($courseGroupData['courses'] as $courseCanvasId => $courseData) {
                    $unitSpecFolder                = $this->processCanvasCourse($courseSpecFolder, $courseCanvasId, $courseData['termName']);
                    $synchronizedCourseFolderIds[] = $unitSpecFolder->getId();

                    foreach ($courseData['quizzes'] as $quizCanvasId => $quizData) {
                        $quiz                  = $this->syncWithCompositeQuiz(
                            $unitSpecFolder,
                            $quizCanvasId,
                            $courseData['teacherSchoolboxId'] ?? $schoolboxAdminUserId,
                            $quizData['name'],
                            $quizData['description'],
                            $quizData['points_possible'],
                            $quizData['created_at'],
                            $quizData['updated_at'],
                            $quizData['muted'],
                            $quizData['quiz_type'],
                            $quizData['anonymous_submissions'],
                            $quizData['time_limit'],
                            $quizData['allowed_attempts'],
                            $quizData['position']
                        );
                        $synchronizedQuizIds[] = $quiz->getId();
                    }

                    foreach ($courseData['assignments'] as $assignmentCanvasId => $assignmentData) {
                        $submissionBox  = $this->syncWithSubmissionBox(
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

        $this->deleteUnSynchronizedQuizzes($synchronizedQuizIds);
        $this->deleteUnSynchronizedSubmissionBoxes($synchronizedSubmissionBoxIds);
        $this->deleteUnSynchronizedCourses($synchronizedCourseFolderIds);
        $this->deleteUnSynchronizedCourseGroups($synchronizedCourseGroupFolderIds);

        $this->printCounts();
    }


    /**
     * Returns the canvas ids of the Canvas courses to import
     *
     * Course must:
     *   * Have workflow_state 'available' or 'completed'
     *   * Have 1+ CourseSections where the Sections:
     *     * Has a SisSourceId LIKE 'Y2019C%NOT', ... where year is from 2019 to current calendar year + 1
     *
     * @return int[]
     * @throws Database\Exception
     * @throws InvalidColumnException
     * @throws UnderflowException
     */
    private function getCanvasCourseIdsToImport(): array
    {
        $pb          = $this->courseBridge->getCanvasCoursesToImport();
        $whereClause = $pb->getWhere()[0];
        $sql         = "
            SELECT DISTINCT c.canvas_id
            FROM course_dim c
            JOIN course_section_dim cs ON cs.course_id = c.id
            WHERE {$whereClause}
        ";
        $canvasIds = $this->canvasDb->executeQuery($sql, $pb->getParams())->fetchAll(PDO::FETCH_COLUMN);
        return array_map(static function (string $canvasId) {
            return (int)$canvasId;
        }, $canvasIds);
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
                $this->saveModel($this->folderMapper, $courseFolder);
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
                $this->saveModel($this->folderMapper, $unitSpecFolder);
            }
        }
        return $unitSpecFolder;
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
     * @return SubmissionBox
     * @throws Database\Exception
     * @throws InvalidArgumentException
     * @throws Mapper\Exception
     * @throws UnexpectedValueException
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
        int $position
    ): SubmissionBox {
        // Translate Canvas attributes to SB attributes if not a 1:1 between systems
        $maxMark           = $this->assignmentBridge->getMaxMark($pointsPossible);
        $markType          = $this->assignmentBridge->getMarkType($gradingType, $pointsPossible);
        $acceptOnline      = $this->assignmentBridge->getAcceptOnline($submissionTypes);
        $acceptMultiple    = $this->assignmentBridge->getAcceptMultiple($submissionTypes);
        $markVisibility    = $this->assignmentBridge->getMarkVisibility($muted);
        $selfReviewability = $this->assignmentBridge->getSelfReviewability();
        $workType          = $this->assignmentBridge->getSubmissionBoxWorkType();
        $weight            = $this->assignmentBridge->getSubmissionBoxWeight();
        $submissionBox = $this->findSubmissionBoxSpec($canvasId);

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
        if($this->checkAssessmentBelongsToUnitSpec($submissionBox->getAssessmentModel(), $unitSpecFolder->getUnitSpecification() )){
            $this->countSubmissionBoxMoved++;
        }

        $isAssessmentUpdated = $this->checkAssessmentForChanges(
            $submissionBox->getAssessmentModel(),
            $title,
            $description,
            $position,
            $workType,
            $createdAt,
            $updatedAt
        );

        $isBoxUpdated = false;
        if (
            // Does any attribute no longer align?
            $submissionBox->getWeight() !== $weight ||
            $submissionBox->getMaxMark() !== $maxMark ||
            $submissionBox->getAcceptOnline() !== $acceptOnline ||
            $submissionBox->getAcceptMultiple() !== $acceptMultiple ||
            $submissionBox->getMarkVisibility() !== $markVisibility ||
            $submissionBox->getSelfReviewability() !== $selfReviewability ||
            $submissionBox->getCategory()->getId() !== $unitSpecFolder->getId() ||
            $submissionBox->getMarkType()->getId() !== $markType->getId()
        ) {
            // Update all attributes and update
            $submissionBox
                ->setCategory($unitSpecFolder)
                ->setMaxMark($maxMark)
                ->setMarkType($markType)
                ->setAcceptOnline($acceptOnline)
                ->setAcceptMultiple($acceptMultiple)
                ->setWeight($weight)
                ->setMarkVisibility($markVisibility)
                ->setSelfReviewability($selfReviewability);
            $this->saveModel($this->submissionBoxMapper, $submissionBox);
            $isBoxUpdated = true;
        }
        if ($isAssessmentUpdated || $isBoxUpdated) {
            $this->countSubmissionBoxUpdated++;
            $this->log->debug("SubmissionBox '$title' (canvasId: $canvasId) updated");
        }
        return $submissionBox;
    }


    /**
     * Synchronises a Canvas Quiz to a Schoolbox Quiz/Section/Question/Answer
     *
     * @param Folder\Model $unitSpecFolder
     * @param int          $quizCanvasId
     * @param int          $quizOwnerId
     * @param string       $title
     * @param string|null  $description
     * @param float|null   $pointsPossible
     * @param string       $createdAt
     * @param string       $updatedAt
     * @param bool         $muted
     * @param string       $quizType
     * @param bool         $anonymousSubmissions
     * @param int          $timeLimitMinutes
     * @param int          $allowedAttempts
     * @param int          $position
     * @return Quiz\Model
     * @throws Database\Exception
     * @throws InvalidColumnException
     * @throws Mapper\Exception
     * @throws UnexpectedValueException
     */
    private function syncWithCompositeQuiz(
        Folder\Model $unitSpecFolder,
        int $quizCanvasId,
        int $quizOwnerId,
        string $title,
        ?string $description,
        ?float $pointsPossible,
        string $createdAt,
        string $updatedAt,
        bool $muted,
        string $quizType,
        bool $anonymousSubmissions,
        int $timeLimitMinutes,
        int $allowedAttempts,
        int $position
    ): Quiz\Model {
        $quiz = $this->syncWithQuiz(
            $unitSpecFolder,
            $quizCanvasId,
            $quizOwnerId,
            $title,
            $description,
            $createdAt,
            $updatedAt,
            $muted,
            $quizType,
            $anonymousSubmissions,
            $timeLimitMinutes,
            $allowedAttempts,
            $position
        );
        $section  = $this->syncWithSection($quiz);
        $question = $this->syncWithQuestion($section, $pointsPossible);
        $this->syncWithSolution($question);

        return $quiz;
    }


    /**
     * Sync Canvas Data with a Schoolbox Quiz+Assessment
     *
     * @param Folder\Model $unitSpecFolder
     * @param int          $quizCanvasId
     * @param int          $quizOwnerId
     * @param string       $title
     * @param string|null  $description
     * @param string       $createdAt
     * @param string       $updatedAt
     * @param bool         $muted
     * @param string       $quizType
     * @param bool         $anonymousSubmissions
     * @param int          $timeLimitMinutes
     * @param int          $allowedAttempts
     * @param int          $position
     * @return Quiz\Model
     * @throws Database\Exception
     * @throws Mapper\Exception
     * @throws UnexpectedValueException
     */
    private function syncWithQuiz(
        Folder\Model $unitSpecFolder,
        int $quizCanvasId,
        int $quizOwnerId,
        string $title,
        ?string $description,
        string $createdAt,
        string $updatedAt,
        bool $muted,
        string $quizType,
        bool $anonymousSubmissions,
        int $timeLimitMinutes,
        int $allowedAttempts,
        int $position
    ): Quiz\Model {
        // Translate Canvas attributes to SB attributes if not a 1:1 between systems
        $markVisibility    = $this->assignmentBridge->getMarkVisibility($muted);
        $workType          = $this->assignmentBridge->getQuizWorkType($quizType);
        $duration          = $timeLimitMinutes === -1 ? -1 : $timeLimitMinutes * 60;
        $maxAttempts       = $allowedAttempts === -1 ? 0 : $allowedAttempts;
        $weight            = $this->assignmentBridge->getQuizWeight();

        $quiz = $this->findQuizSpec($quizCanvasId);
        if (!$quiz) {
            // Not found in SB? Create and return it.
            $this->log->debug("Created new QuizSpec for Canvas Assignment '$title' (canvasId: $quizCanvasId)");
            $this->countQuizCreated++;
            return $this->createQuizSpec(
                $unitSpecFolder,
                $quizOwnerId,
                $workType,
                $title,
                $description,
                $createdAt,
                $updatedAt,
                $quizCanvasId,
                $anonymousSubmissions,
                $duration,
                $maxAttempts,
                $weight,
                $position,
                $markVisibility
            );
        }

        $this->log->debug("Synchronising Canvas Assignment(canvasId: $quizCanvasId) with QuizSpec (id: {$quiz->getId()})");

        if($this->checkAssessmentBelongsToUnitSpec($quiz->getAssessmentModel(), $unitSpecFolder->getUnitSpecification() )){
            $this->countSubmissionBoxMoved++;
        }

        $isAssessmentUpdated = $this->checkAssessmentForChanges(
            $quiz->getAssessmentModel(),
            $title,
            $description,
            $position,
            $workType,
            $createdAt,
            $updatedAt
        );

        $isQuizUpdated = false;
        if (
            // Does any attribute no longer align?
            ($quiz->getWorkType() === null xor $workType === null) ||
            $quiz->getTitle() !== $title ||
            $quiz->getDescription() !== $description ||
            $quiz->getDuration() !== $duration ||
            $quiz->getMaxAttempts() !== $maxAttempts ||
            $quiz->getWeight() !== $weight ||
            $quiz->getAvailability() !== Quiz\Model::AVAILABILITY_ASSESS ||
            $quiz->getAnonymousResult() !== $anonymousSubmissions ||
            $quiz->getMarkVisibility() !== $markVisibility ||
            $quiz->getCreated()->getDatabase() !== $createdAt ||
            $quiz->getUser()->getId() !== $quizOwnerId ||
            $quiz->getCategory()->getId() !== $unitSpecFolder->getId() ||
            ($quiz->getWorkType() && $workType && $quiz->getWorkType()->getId() !== $workType->getId())
        ) {
            // Update all attributes and update
            $quiz
                ->setCategory($unitSpecFolder)
                ->setUser((new User())->setId($quizOwnerId))
                ->setWorkType($workType)
                ->setTitle($title)
                ->setDescription($description)
                ->setDuration($duration)
                ->setMaxAttempts($maxAttempts)
                ->setWeight($weight)
                ->setAvailability(Quiz\Model::AVAILABILITY_ASSESS)
                ->setAnonymousResult($anonymousSubmissions)
                ->setMarkVisibility($markVisibility)
                ->setExternalId((string)$quizCanvasId)
                ->setCreated(Alaress_Date_Format::newDatabaseDate($createdAt));

            $this->saveModel($this->quizMapper, $quiz);

            $isQuizUpdated = true;
        }
        if ($isAssessmentUpdated || $isQuizUpdated) {
            $this->countQuizUpdated++;
            $this->log->debug("Quiz '$title' (canvasId: $quizCanvasId) updated");
        }
        return $quiz;

    }

    /**
     * Sync Canvas data with SB Quiz section
     *
     * @param Quiz\Model $quiz
     * @return QuizSection\Model
     * @throws Database\Exception
     * @throws UnexpectedValueException
     */
    private function syncWithSection(Quiz\Model $quiz): QuizSection\Model
    {
        $sequence    = 1;
        $title       = "Canvas Quiz section";
        $description = "Imported from Canvas LMS";
        $random      = false;
        $pool        = Evaluation_Section::POOL_SIZE_ALL;

        // Do a section already exist in SB?
        $sections = $this->quizSectionMapper->findByColumn(QuizSection\Mapper::COL_ASSESS_ID, $quiz->getId());
        if($sections->count() > 1){
            throw new UnexpectedValueException("Multiple sections found for quiz {$quiz->getId()}");
        }
        /** @var null|QuizSection\Model $section */
        $section = $sections->first();

        // If not exist, create and return.
        if(!$section){
            $section = (new QuizSection\Model())
                ->setQuiz($quiz)
                ->setSequence($sequence)
                ->setTitle($title)
                ->setDescription($description)
                ->setRandom($random)
                ->setPool($pool);
            return $this->saveModel($this->quizSectionMapper, $section);
        }

        // If already exists, and has changed, update it
        if(
            $section->getSequence() !== $sequence ||
            $section->getTitle() !== $title ||
            $section->getDescription() !== $description ||
            $section->getRandom() !== $random ||
            $section->getPool() !== $pool
        ) {
            $section
                ->setSequence($sequence)
                ->setTitle($title)
                ->setDescription($description)
                ->setRandom($random)
                ->setPool($pool);
            $this->saveModel($this->quizSectionMapper, $section);
        }
        return $section;
    }


    /**
     * Sync canvas data with a single Schoolbox Quiz Question
     *
     * @param QuizSection\Model $section
     * @param float|null         $pointsPossible
     * @return QuizQuestion\Model
     * @throws Database\Exception
     * @throws InvalidColumnException
     * @throws UnexpectedValueException
     */
    private function syncWithQuestion(QuizSection\Model $section, ?float $pointsPossible): QuizQuestion\Model
    {
        /** @var QuestionType\Model $questionType */
        $questionType = $this->questionTypeMapper->findByPredicates($this->assignmentBridge->getQuizQuestionTypePredicate())->first();
        $maxMark      = $this->assignmentBridge->getMaxMark($pointsPossible);
        $questionText = "Canvas aggregate question";
        $config       = null;
        $sequence     = 1;

        // Do a question already exist in SB?
        $questions = $this->quizQuestionMapper->findByColumn(QuizQuestion\Mapper::COL_SECTION_ID, $section->getId());
        if($questions->count() > 1){
            throw new UnexpectedValueException("Multiple questions found for section {$section->getId()}");
        }
        /** @var null|QuizQuestion\Model $question */
        $question = $questions->first();

        // If not exist, create and return.
        if(!$question){
            $question = (new QuizQuestion\Model())
                ->setSection($section)
                ->setSequence($sequence)
                ->setType($questionType)
                ->setMarks($maxMark)
                ->setQuestion($questionText)
                ->setConfig($config);
            return $this->saveModel($this->quizQuestionMapper, $question);
        }

        // If already exists, and has changed, update it
        if(
            $question->getSequence() !== $sequence ||
            $question->getMarks() !==$maxMark ||
            $question->getQuestion() !== $questionText ||
            $question->getConfig() !==$config ||
            $question->getType()->getId() !== $questionType->getId()
        ) {
            $question
                ->setSequence($sequence)
                ->setType($questionType)
                ->setMarks($maxMark)
                ->setQuestion($questionText)
                ->setConfig($config);
            $this->saveModel($this->quizQuestionMapper, $question);
        }
        return $question;
    }


    /**
     * Sync Canvas data w/ Schoolbox Quiz Solution
     *
     * @param QuizQuestion\Model $question
     * @return QuizSolution\Model
     * @throws Database\Exception
     * @throws UnexpectedValueException
     */
    private function syncWithSolution(QuizQuestion\Model $question): QuizSolution\Model
    {
        $sequence     = 1;
        $solutionText = '';

        // Do a question already exist in SB?
        $solutions = $this->quizSolutionMapper->findByColumn(QuizSolution\Mapper::COL_QUESTION_ID, $question->getId());
        if ($solutions->count() > 1) {
            throw new UnexpectedValueException("Multiple solutions found for question {$question->getId()}");
        }
        /** @var null|QuizSolution\Model $solution */
        $solution = $solutions->first();

        // If not exist, create and return.
        if (!$solution) {
            $solution = (new QuizSolution\Model())
                ->setQuestion($question)
                ->setSequence($sequence)
                ->setSolution($solutionText);
            return $this->saveModel($this->quizSolutionMapper, $solution);
        }

        // If already exists, and has changed, update it
        if (
            $solution->getSequence() !== $sequence ||
            $solution->getSolution() !== $solutionText
        ) {
            $solution
                ->setSequence($sequence)
                ->setSolution($solutionText);
            $this->saveModel($this->quizSolutionMapper, $solution);
        }
        return $solution;
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
            WHERE a.deleted_at IS NULL AND sb.external_id = :externalId
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

        $submissionBox = $this->submissionBoxMapper->hydrate($row, 'sb');
        $submissionBox->setAssessmentModel($assessment);
        $assessment->setSubObject($submissionBox);
        $assessment->setContextUnitSpecification((new UnitSpec\Model())->setId((int)$row['course_unit_specification_id']));

        return $submissionBox;
    }


    /**
     * Find a Quiz Spec for specified Canvas quiz.canvas_id
     *
     * @param int $quizCanvasId
     * @return Quiz\Model|null
     * @throws Database\Exception
     * @throws UnexpectedValueException
     * @throws Mapper\Exception
     */
    private function findQuizSpec(int $quizCanvasId): ?Quiz\Model
    {
        // Hydrate Quiz + Assessment + link to UnitSpec (so we can check if moved)
        $sql = "
            SELECT
                {$this->quizMapper->getColumnString('q')},
                {$this->assessmentMapper->getColumnString('a')},
                acus.course_unit_specification_id
            FROM assess q
            JOIN assessment a ON a.id = q.assessment_id
            JOIN assessment_course_unit_specification acus ON a.id = acus.assessment_id
            WHERE a.deleted_at IS NULL AND q.external_id = :externalId
        ";

        $rows = $this->schoolboxDb->executeQuery($sql, ['externalId' => $quizCanvasId])->fetchAll();
        if (!count($rows)) {
            return null;
        }
        if (count($rows) > 1) {
            throw new UnexpectedValueException("Unable to uniquely identify a Quiz spec for canvas id '$quizCanvasId'");
        }

        $row        = $rows[0];
        $assessment = $this->assessmentMapper->hydrate($row, 'a');

        $quiz = $this->quizMapper->hydrate($row, 'q');
        $quiz->setAssessmentModel($assessment);
        $assessment->setSubObject($quiz);
        $assessment->setContextUnitSpecification((new UnitSpec\Model())->setId((int)$row['course_unit_specification_id']));

        return $quiz;
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
     * @param int                    $weight
     * @param string                 $createdAt
     * @param string                 $updatedAt
     * @param int                    $markVisibility
     * @param int                    $position
     * @param string                 $selfReviewability
     * @param WorkType\Model|null    $workType
     * @return SubmissionBox
     * @throws Database\Exception
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
        int $weight,
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
            $createdAt,
            $updatedAt,
            $position
        );

        $submissionBox = (new SubmissionBox())
            ->setAssessmentModel($assessment)
            ->setOwner((new User())->setId($boxOwnerId))
            ->setMaxMark($maxMark)
            ->setCategory($unitSpecFolder)
            ->setMarkType($markType)
            ->setAcceptOnline($acceptOnline)
            ->setAcceptMultiple($acceptMultiple)
            ->setWeight($weight)
            ->setMarkVisibility($markVisibility)
            ->setSelfReviewability($selfReviewability)
            ->setExternalId((string)$canvasId);

        $this->saveModel($this->submissionBoxMapper, $submissionBox);

        return $submissionBox;
    }

    /**
     * Create a new QuizSpec model (including assessment model)
     *
     * @param Folder\Model        $unitSpecFolder
     * @param int                 $quizOwnerId
     * @param WorkType\Model|null $workType
     * @param string              $title
     * @param string|null         $description
     * @param string              $createdAt
     * @param string              $updatedAt
     * @param int                 $quizCanvasId
     * @param bool                $anonymousSubmissions
     * @param int                 $duration
     * @param int                 $maxAttempts
     * @param int                 $weight
     * @param int                 $position
     * @param int                 $markVisibility
     * @return Quiz\Model
     * @throws Database\Exception
     */
    private function createQuizSpec(
        Folder\Model $unitSpecFolder,
        int $quizOwnerId,
        ?WorkType\Model $workType,
        string $title,
        ?string $description,
        string $createdAt,
        string $updatedAt,
        int $quizCanvasId,
        bool $anonymousSubmissions,
        int $duration,
        int $maxAttempts,
        int $weight,
        int $position,
        int $markVisibility
    ): Quiz\Model {
        $assessment = $this->createAssessment(
            $unitSpecFolder->getUnitSpecification(),
            $title,
            $description,
            $workType,
            $createdAt,
            $updatedAt,
            $position
        );

        $quiz = (new Quiz\Model())
            ->setCategory($unitSpecFolder)
            ->setAssessmentModel($assessment)
            ->setUser((new User())->setId($quizOwnerId))
            ->setWorkType($workType)
            ->setTitle($title)
            ->setDescription($description)
            ->setDuration($duration)
            ->setMaxAttempts($maxAttempts)
            ->setWeight($weight)
            ->setAvailability(Quiz\Model::AVAILABILITY_ASSESS)
            ->setAnonymousResult($anonymousSubmissions)
            ->setMarkVisibility($markVisibility)
            ->setExternalId((string)$quizCanvasId)
            ->setCreated($assessment->getCreatedAt());

        $this->saveModel($this->quizMapper, $quiz);

        return $quiz;
    }


    /**
     * Create an assessment model, linked to the specified UnitSpec
     *
     * @param UnitSpec\Model      $unitSpec
     * @param string              $title
     * @param string|null         $description
     * @param WorkType\Model|null $workType
     * @param string              $createdAt
     * @param string              $updatedAt
     * @param int                 $position
     * @return Assessment\Model
     * @throws Database\Exception
     */
    private function createAssessment(
        UnitSpec\Model $unitSpec,
        string $title,
        ?string $description,
        ?WorkType\Model $workType,
        string $createdAt,
        string $updatedAt,
        int $position
    ): Assessment\Model {
        $assessment = (new Assessment\Model())
            ->setContextUnitSpecification($unitSpec)
            ->setTitle($title)
            ->setDescription($description)
            ->setWorkType($workType)
            ->setCreatedAt(Alaress_Date_Format::newDatabaseDate($createdAt))
            ->setUpdatedAt(Alaress_Date_Format::newDatabaseDate($updatedAt))
            ->setSequence($position);

        /* Cannot use mappers here to save because:
         * 1. We want to preserve Canvas create/update timestamps (alt. method would be to save via mapper, then raw query to override)
         * 2. Too many inappropriate pre/post save callbacks on mappers which cannot be altered in a master feature.
         */
        $this->saveModel($this->assessmentMapper, $assessment);

        $sql = "INSERT INTO assessment_course_unit_specification (assessment_id, course_unit_specification_id) VALUES (:assessmentId, :unitSpecId)";
        $this->schoolboxDb->executeQuery($sql, [
            'assessmentId' => $assessment->getId(),
            'unitSpecId'   => $unitSpec->getId(),
        ]);

        return $assessment;
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
     * Check if an assessment has changed, and update it in the DB if it has.
     *
     * Returns whether any changes were made.
     *
     * @param Assessment\Model    $assessment
     * @param string              $title
     * @param string|null         $description
     * @param int                 $position
     * @param WorkType\Model|null $workType
     * @param string              $createdAt
     * @param string              $updatedAt
     * @return bool
     * @throws Database\Exception
     */
    private function checkAssessmentForChanges(
        Assessment\Model $assessment,
        string $title,
        ?string $description,
        int $position,
        ?WorkType\Model $workType,
        string $createdAt,
        string $updatedAt
    ): bool {
        // Do all attributes align?
        if (
            $assessment->getTitle() === $title &&
            $assessment->getDescription() === $description &&
            $assessment->getSequence() === $position &&
            (($assessment->getWorkType() === null) === ($workType === null)) &&
            $assessment->getCreatedAt()->getDatabase() === $createdAt &&
            $assessment->getUpdatedAt()->getDatabase() === $updatedAt &&
            (!$assessment->getWorkType() || !$workType || $assessment->getWorkType()->getId() === $workType->getId())
        ) {
            return false;
        }

        $assessment->setTitle($title)
                   ->setDescription($description)
                   ->setCreatedAt(Alaress_Date_Format::newDatabaseDate($createdAt))
                   ->setUpdatedAt(Alaress_Date_Format::newDatabaseDate($updatedAt))
                   ->setSequence($position)
                   ->setWorkType($workType);

        $this->saveModel($this->assessmentMapper, $assessment);

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

        $folder = $this->createFolder($folderName, $accountFolder);

        $courseSpec = (new CourseSpec\Model())->setFolder($folder);
        $folder->setCourseSpecification($courseSpec);

        $this->saveModel($this->courseSpecMapper, $courseSpec);

        $this->log->debug("Created Schoolbox CourseSpec+Folder '$folderName' for Canvas CourseGroup (courseCode: '$courseCode')");

        return $folder;
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
        $folder     = $this->createFolder($folderName, $courseSpecFolder);
        $unitSpec   = (new UnitSpec\Model())->setFolder($folder);
        $folder->setUnitSpecification($unitSpec);
        $this->saveModel($this->unitSpecMapper, $unitSpec);
        $this->log->debug("Created Schoolbox UnitSpec+Folder '$folderName' for Canvas Course (canvasId: $courseCanvasId)");
        return $folder;
    }


    /**
     * Insert new folder and return new Folder model
     *
     * @param string       $name
     * @param Folder\Model $parent
     * @return Folder\Model
     * @throws Database\Exception
     */
    private function createFolder(string $name, Folder\Model $parent): Folder\Model
    {
        $folder = (new Folder\Model())
            ->setName($name)
            ->setParent($parent);
        return $this->saveModel($this->folderMapper, $folder);
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
     * @param array $synchronizedQuizIds
     * @throws Database\Predicate\InvalidColumnException
     * @throws Mapper\Exception
     * @throws UnderflowException
     */
    private function deleteUnSynchronizedQuizzes(array $synchronizedQuizIds): void
    {
        $pb = $this->quizMapper->getPredicateBuilder()
                               ->notNull('external_id')
                               ->isNull('am.deleted_at');
        if (count($synchronizedQuizIds)) {
            $pb->notIn('assess_id', $synchronizedQuizIds);
        }

        foreach ($this->quizMapper->findByPredicates($pb) as $quiz) {
            $this->log->debug("Deleting un-synchronised assignment '{$quiz->getAssessmentModel()->getTitle()}' (quizId: {$quiz->getId()})");
            $this->quizMapper->delete($quiz);
            $this->countQuizDeleted++;
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

        $this->countQuizSynchronised = 0;
        $this->countQuizCreated    = 0;
        $this->countQuizUpdated    = 0;
        $this->countQuizMoved      = 0;
        $this->countQuizDeleted    = 0;
    }


    /**
     * Print counts of things checked/changes
     */
    private function printCounts(): void
    {
        $this->log->notice($this->countCourseGroupsSynchronised . ' Canvas CourseGroups synchronised');
        $this->log->notice($this->countCourseGroupsCreated . ' Schoolbox CourseSpecs created');
        $this->log->notice($this->countCourseGroupsUpdated . ' Schoolbox CourseSpecs updated');
        $this->log->notice($this->countCourseGroupsMoved . ' Schoolbox CourseSpecs moved');
        $this->log->notice($this->countCourseGroupsDeleted . ' Schoolbox CourseSpecs deleted');

        $this->log->notice($this->countCoursesSynchronised . ' Canvas courses synchronised');
        $this->log->notice($this->countCoursesCreated . ' Schoolbox UnitSpecs created');
        $this->log->notice($this->countCoursesUpdated . ' Schoolbox UnitSpecs updated');
        $this->log->notice($this->countCoursesMoved . ' Schoolbox UnitSpecs moved');
        $this->log->notice($this->countCoursesDeleted . ' Schoolbox UnitSpecs deleted');

        $this->log->notice($this->countAssignmentsSynchronised . ' Canvas assignments synchronised');
        $this->log->notice($this->countSubmissionBoxCreated . ' Schoolbox SubmissionBox Specs created');
        $this->log->notice($this->countSubmissionBoxUpdated . ' Schoolbox SubmissionBox Specs updated');
        $this->log->notice($this->countSubmissionBoxMoved . ' Schoolbox SubmissionBox Specs moved');
        $this->log->notice($this->countSubmissionBoxDeleted . ' Schoolbox SubmissionBox Specs deleted');

        $this->log->notice($this->countQuizSynchronised . ' Canvas quizzes synchronised');
        $this->log->notice($this->countQuizCreated . ' Schoolbox Quiz Specs created');
        $this->log->notice($this->countQuizUpdated . ' Schoolbox Quiz Specs updated');
        $this->log->notice($this->countQuizMoved . ' Schoolbox Quiz Specs moved');
        $this->log->notice($this->countQuizDeleted . ' Schoolbox Quiz Specs deleted');
    }
}

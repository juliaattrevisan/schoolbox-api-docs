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
use External\Canvas\Sync\Helpers\CourseTeachers;
use External\Canvas\Sync\ImportStage\Traits\ModelSaveTrait;
use PDO;
use PDOException;
use Psr\Log\LoggerInterface;
use Schoolbox\IConfigLoader;
use Schoolbox\Learning\Assessment\DataModel as Assessment;
use Schoolbox\Learning\Assessment\WorkType;
use Schoolbox\Learning\Course\DataModel\Specification\Unit as UnitSpec;
use Schoolbox\Learning\Quiz;
use Schoolbox\Learning\Quiz\Section as QuizSection;
use Schoolbox\Learning\Quiz\Section\Question as QuizQuestion;
use Schoolbox\Learning\Quiz\Section\Question\QuestionType;
use Schoolbox\Learning\Quiz\Section\Question\Solution as QuizSolution;
use Schoolbox\Mapper;
use Schoolbox\Resource\Folder;
use Schoolbox\User;
use UnderflowException;
use UnexpectedValueException;
use function array_key_exists;
use function array_keys;
use function array_map;
use function count;

/**
 * Responsible for importing Canvas Quiz into Schoolbox Course Quiz Specs
 */
class CourseQuiz extends CourseAssessmentAbstract
{
    use ModelSaveTrait;

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

    /** @var Folder\Mapper */
    private $folderMapper;

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
     * @param Assessment\Mapper   $assessmentMapper
     * @param Folder\Mapper       $folderMapper
     * @param LoggerInterface     $log
     * @param IConfigLoader       $config
     * @param Bridge\Assignment   $assignmentBridge
     * @param Bridge\Course       $courseBridge
     */
    public function __construct(
        IConnector $canvasDb,
        IConnector $schoolboxDb,
        Quiz\Mapper $quizMapper,
        QuizSection\Mapper $quizSectionMapper,
        QuizQuestion\Mapper $quizQuestionMapper,
        QuestionType\Mapper $quizQuestionTypeMapper,
        QuizSolution\Mapper $quizSolutionMapper,
        Assessment\Mapper $assessmentMapper,
        Folder\Mapper $folderMapper,
        LoggerInterface $log,
        IConfigLoader $config,
        Bridge\Assignment $assignmentBridge,
        Bridge\Course $courseBridge
    ) {
        parent::__construct(
            $canvasDb,
            $schoolboxDb,
            $assessmentMapper,
            $log,
            $config
        );
        $this->assignmentBridge   = $assignmentBridge;
        $this->quizMapper         = $quizMapper;
        $this->quizSectionMapper  = $quizSectionMapper;
        $this->quizQuestionMapper = $quizQuestionMapper;
        $this->questionTypeMapper = $quizQuestionTypeMapper;
        $this->quizSolutionMapper = $quizSolutionMapper;
        $this->folderMapper       = $folderMapper;
        $this->courseBridge       = $courseBridge;
    }


    /**
     * @throws Database\Exception
     * @throws Database\Predicate\InvalidColumnException
     * @throws Mapper\Exception
     * @throws UnderflowException
     * @throws UnexpectedValueException
     * @throws PDOException
     */
    public function synchronise(): void
    {
        $this->log->notice("Synchronising Canvas Quiz => Schoolbox QuizSpec...");
        $this->resetCounts();

        $canvasCourseIds   = $this->getCanvasCourseIdsToImport();
        $courseFilterPb    = (new Builder($this->canvasDb, ''))->in('course.canvas_id', $canvasCourseIds);
        $courseFilterWhere = $courseFilterPb->getWhere()[0];
        $sql               = "
        SELECT
               course.canvas_id            'course.canvas_id',
               course.name                 'course.name',
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
        JOIN quiz_dim quiz ON quiz.course_id = course.id AND quiz.workflow_state = 'published'
        JOIN quiz_fact ON quiz_fact.quiz_id = quiz.id
        LEFT JOIN assignment_dim assignment ON quiz.assignment_id = assignment.id
        LEFT JOIN assignment_group_dim assignment_group on assignment.assignment_group_id = assignment_group.id
        WHERE {$courseFilterWhere}
        -- TODO cater for quiz/assignment positioning
        ORDER BY course.code, course.canvas_id, assignment_group.position, assignment.position
        ";
        $statement         = $this->canvasDb->executeQuery($sql, $courseFilterPb->getParams());

        // Parse results into hierarchical structure
        $coursesData        = [];
        $assessmentSequence = null;
        while ($row = $statement->fetch()) {
            $courseCanvasId = (int)$row['course.canvas_id'];
            $quizCanvasId   = $row['quiz.canvas_id'] === null ? null : (int)$row['quiz.canvas_id'];

            // First time we've seen course?
            if (!array_key_exists($courseCanvasId, $coursesData)) {
                $coursesData[$courseCanvasId] = [
                    'name'    => $row['course.name'],
                    'quizzes' => [],
                ];
                $assessmentSequence           = 1; // TODO remove hard-coding in develop branch
            }
            // First time we've seen this quiz?
            if (!array_key_exists($quizCanvasId, $coursesData[$courseCanvasId]['quizzes'])) {
                $coursesData[$courseCanvasId]['quizzes'][$quizCanvasId] = [
                    'name'                  => $row['quiz.name'],
                    'description'           => $row['quiz.description'],
                    'points_possible'       => $row['quiz.points_possible'] === null ? null : (float)$row['quiz.points_possible'],
                    'created_at'            => $row['quiz.created_at'],
                    'updated_at'            => $row['quiz.updated_at'],
                    'muted'                 => false,
                    // TODO this has been deprecated by Canvas, will always be FALSE here
                    'quiz_type'             => $row['quiz.quiz_type'],
                    'anonymous_submissions' => $row['quiz.anonymous_submissions'] === 'allow_anonymous_submissions',
                    'time_limit'            => $row['quiz_fact.time_limit'] === null ? null : (int)$row['quiz_fact.time_limit'],
                    'allowed_attempts'      => (int)$row['quiz_fact.allowed_attempts'],
                    'position'              => $assessmentSequence++,
                ];
            }
        }

        // Attempt to find a teacher for each course who we can set as the course's assessment owner
        // User must exist in Schoolbox, have roleType of 'staff', and have 'teacher-enrollment' to assignment's course
        $courseTeachers = new CourseTeachers($this->schoolboxDb, $this->canvasDb);
        foreach ($courseTeachers->getCourseTeacherMap(array_keys($coursesData)) as $courseCanvasId => $teacherUserId) {
            $coursesData[$courseCanvasId]['teacherSchoolboxId'] = $teacherUserId;
        }

        // Fall back to setting SubmissionBoxSpec.owner to Schoolbox Admin if no course teacher found
        $schoolboxAdminUserId = $this->getSchoolboxAdminUserId();

        $synchronizedQuizIds = [];
        foreach ($coursesData as $courseCanvasId => $courseData) {
            $pb             = $this->courseBridge->equalsCanvasCourse($courseCanvasId);
            $unitSpecFolder = $this->folderMapper->findOneByPredicates($pb);

            foreach ($courseData['quizzes'] as $quizCanvasId => $quizData) {
                $quiz                  = $this->syncWithCompositeQuiz(
                    $unitSpecFolder,
                    $quizCanvasId,
                    $courseData['teacherSchoolboxId'] ?? $schoolboxAdminUserId,
                    $quizData['name'],
                    $quizData['description'] ? $this->htmlPurifier->purify($quizData['description']) : '',
                    $quizData['points_possible'],
                    $quizData['created_at'],
                    $quizData['updated_at'] ?? $quizData['created_at'],
                    $quizData['muted'],
                    $quizData['quiz_type'],
                    $quizData['anonymous_submissions'],
                    $quizData['time_limit'],
                    $quizData['allowed_attempts'],
                    $quizData['position']
                );
                $synchronizedQuizIds[] = $quiz->getId();
                $this->countQuizSynchronised++;
            }
        }


        $this->deleteUnSynchronizedQuizzes($synchronizedQuizIds);
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
        // TODO dedupe this with 'CourseAssignments'
        $pb          = $this->courseBridge->getCanvasCoursesToImport();
        $whereClause = $pb->getWhere()[0];
        $sql         = "
            SELECT DISTINCT c.canvas_id
            FROM course_dim c
            JOIN course_section_dim cs ON cs.course_id = c.id
            WHERE {$whereClause}
        ";
        $canvasIds   = $this->canvasDb->executeQuery($sql, $pb->getParams())->fetchAll(PDO::FETCH_COLUMN);
        return array_map(static function (string $canvasId) {
            return (int)$canvasId;
        }, $canvasIds);
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
     * @throws Mapper\Exception
     * @throws UnexpectedValueException
     * @throws PDOException
     * @throws InvalidColumnException
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
        $quiz     = $this->syncWithQuiz(
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
     * @throws PDOException
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
        $markVisibility = $this->assignmentBridge->getMarkVisibility($muted);
        $workType       = $this->assignmentBridge->getQuizWorkType($quizType);
        $duration       = $timeLimitMinutes === -1 ? -1 : $timeLimitMinutes * 60;
        $maxAttempts    = $allowedAttempts === -1 ? 0 : $allowedAttempts;
        $weight         = $this->assignmentBridge->getQuizWeight();

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

        $this->log->debug("Synchronising Canvas Quiz (canvasId: $quizCanvasId) with QuizSpec (id: {$quiz->getId()})");

        if ($this->checkAssessmentBelongsToUnitSpec($quiz->getAssessmentModel(), $unitSpecFolder->getUnitSpecification())) {
            $this->countQuizMoved++;
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

        $isQuizUpdated = $this->checkQuizForChanges(
            $quizCanvasId,
            $quiz,
            $workType,
            $title,
            $description,
            $duration,
            $maxAttempts,
            $weight,
            $anonymousSubmissions,
            $markVisibility,
            $createdAt,
            $quizOwnerId,
            $unitSpecFolder
        );
        if ($isAssessmentUpdated || $isQuizUpdated) {
            $this->countQuizUpdated++;
            $this->log->debug("Quiz '$title' (canvasId: $quizCanvasId) updated");
        }
        return $quiz;
    }


    /**
     * Checks whether the quiz spec needs to be updated. Returns whether any changes persisted.
     *
     * @param int                 $quizCanvasId
     * @param Quiz\Model          $quiz
     * @param WorkType\Model|null $workType
     * @param string              $title
     * @param string|null         $description
     * @param int                 $duration
     * @param int                 $maxAttempts
     * @param int                 $weight
     * @param bool                $anonymousSubmissions
     * @param int                 $markVisibility
     * @param string              $createdAt
     * @param int                 $quizOwnerId
     * @param Folder\Model        $unitSpecFolder
     * @return bool
     * @throws Mapper\Exception
     * @throws UnexpectedValueException
     * @throws PDOException
     */
    private function checkQuizForChanges(
        int $quizCanvasId,
        Quiz\Model $quiz,
        ?WorkType\Model $workType,
        string $title,
        ?string $description,
        int $duration,
        int $maxAttempts,
        int $weight,
        bool $anonymousSubmissions,
        int $markVisibility,
        string $createdAt,
        int $quizOwnerId,
        Folder\Model $unitSpecFolder
    ): bool {
        if (
            // Does any attribute no longer align?
            (($quiz->getWorkType() === null) === ($workType === null)) &&
            $quiz->getTitle() === $title &&
            $quiz->getDescription() === $description &&
            $quiz->getDuration() === $duration &&
            $quiz->getMaxAttempts() === $maxAttempts &&
            $quiz->getWeight() === $weight &&
            $quiz->getAvailability() === Quiz\Model::AVAILABILITY_ASSESS &&
            $quiz->getAnonymousResult() === $anonymousSubmissions &&
            $quiz->getMarkVisibility() === $markVisibility &&
            $quiz->getCreated()->getDatabase() === $createdAt &&
            $quiz->getUser()->getId() === $quizOwnerId &&
            $quiz->getCategory()->getId() === $unitSpecFolder->getId() &&
            ($quiz->getWorkType() && $workType && $quiz->getWorkType()->getId() === $workType->getId())
        ) {
            return false;
        }
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

        $this->quizMapper->save($quiz);
        return true;
    }


    /**
     * Sync Canvas data with SB Quiz section
     *
     * @param Quiz\Model $quiz
     * @return QuizSection\Model
     * @throws Mapper\Exception
     * @throws PDOException
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
        if ($sections->count() > 1) {
            throw new UnexpectedValueException("Multiple sections found for quiz {$quiz->getId()}");
        }
        /** @var null|QuizSection\Model $section */
        $section = $sections->first();

        // If not exist, create and return.
        if (!$section) {
            $section = (new QuizSection\Model())
                ->setQuiz($quiz)
                ->setSequence($sequence)
                ->setTitle($title)
                ->setDescription($description)
                ->setRandom($random)
                ->setPool($pool);
            $this->quizSectionMapper->save($section);
            return $section;
        }

        // If already exists, and has changed, update it
        if (
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
            $this->quizSectionMapper->save($section);
        }
        return $section;
    }


    /**
     * Sync canvas data with a single Schoolbox Quiz Question
     *
     * @param QuizSection\Model $section
     * @param float|null        $pointsPossible
     * @return QuizQuestion\Model
     * @throws InvalidColumnException
     * @throws Mapper\Exception
     * @throws PDOException
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
        if ($questions->count() > 1) {
            throw new UnexpectedValueException("Multiple questions found for section {$section->getId()}");
        }
        /** @var null|QuizQuestion\Model $question */
        $question = $questions->first();

        // If not exist, create and return.
        if (!$question) {
            $question = (new QuizQuestion\Model())
                ->setSection($section)
                ->setSequence($sequence)
                ->setType($questionType)
                ->setMarks($maxMark)
                ->setQuestion($questionText)
                ->setConfig($config);
            $this->quizQuestionMapper->save($question);
            return $question;
        }

        // If already exists, and has changed, update it
        if (
            $question->getSequence() !== $sequence ||
            $question->getMarks() !== $maxMark ||
            $question->getQuestion() !== $questionText ||
            $question->getConfig() !== $config ||
            $question->getType()->getId() !== $questionType->getId()
        ) {
            $question
                ->setSequence($sequence)
                ->setType($questionType)
                ->setMarks($maxMark)
                ->setQuestion($questionText)
                ->setConfig($config);
            $this->quizQuestionMapper->save($question);
        }
        return $question;
    }


    /**
     * Sync Canvas data w/ Schoolbox Quiz Solution
     *
     * @param QuizQuestion\Model $question
     * @return QuizSolution\Model
     * @throws Mapper\Exception
     * @throws PDOException
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
            $this->quizSolutionMapper->save($solution);
            return $solution;
        }

        // If already exists, and has changed, update it
        if (
            $solution->getSequence() !== $sequence ||
            $solution->getSolution() !== $solutionText
        ) {
            $solution
                ->setSequence($sequence)
                ->setSolution($solutionText);
            $this->quizSolutionMapper->save($solution);
        }
        return $solution;
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

        // Need to respect Canvas's created_at value
        $this->saveModel($this->quizMapper, $quiz, false);

        return $quiz;
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

        $this->saveModel($this->assessmentMapper, $assessment, false, false);

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
            $this->log->debug("Deleting un-synchronised quiz '{$quiz->getAssessmentModel()->getTitle()}' (quizId: {$quiz->getId()})");
            $this->quizMapper->delete($quiz);
            $this->countQuizDeleted++;
        }
    }


    /**
     * Reset all counts to 0
     */
    private function resetCounts(): void
    {
        $this->countQuizSynchronised = 0;
        $this->countQuizCreated      = 0;
        $this->countQuizUpdated      = 0;
        $this->countQuizMoved        = 0;
        $this->countQuizDeleted      = 0;
    }


    /**
     * Print counts of things checked/changes
     */
    private function printCounts(): void
    {
        $this->log->notice($this->countQuizSynchronised . ' Canvas quizzes synchronised');
        $this->log->notice($this->countQuizCreated . ' Schoolbox Quiz Specs created');
        $this->log->notice($this->countQuizUpdated . ' Schoolbox Quiz Specs updated');
        $this->log->notice($this->countQuizMoved . ' Schoolbox Quiz Specs moved');
        $this->log->notice($this->countQuizDeleted . ' Schoolbox Quiz Specs deleted');
    }
}

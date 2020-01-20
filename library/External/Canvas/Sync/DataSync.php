<?php
/**
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */
declare(strict_types=1);

namespace External\Canvas\Sync;

use Alaress\Database\Connector\IConnector;
use DomainException;
use External_ClassInterface;
use External_UserInterface;
use InvalidArgumentException;
use Alaress\Database;
use PDOException;
use Psr\Log\LoggerInterface;
use Schoolbox\Assessment\Component\SubmissionBox\MarkTypeMapper;
use Schoolbox\Assessment\Component\SubmissionBoxMapper;
use Schoolbox\IConfigLoader;
use Schoolbox\Learning\Assessment\DataModel as Assessment;
use Schoolbox\Learning\Assessment\WorkType;
use Schoolbox\Learning\Quiz;
use Schoolbox\Learning\Quiz\Section as QuizSection;
use Schoolbox\Learning\Quiz\Section\Question as QuizQuestion;
use Schoolbox\Learning\Quiz\Section\Question\QuestionType;
use Schoolbox\Learning\Quiz\Section\Question\Solution as QuizSolution;
use Schoolbox\Assessment\Participant;
use Schoolbox\Learning\Course\DataModel\Implementation as CourseImpl;
use Schoolbox\Learning\Course\DataModel\Implementation\Unit as UnitImpl;
use Schoolbox\Learning\Course\DataModel\Specification as CourseSpec;
use Schoolbox\Learning\Course\DataModel\Specification\Unit as UnitSpec;
use Schoolbox\Resource\Folder;
use Schoolbox\Mapper;
use Schoolbox\UserMapper;
use UnderflowException;
use UnexpectedValueException;

/**
 * Synchronises staging database to schoolbox database
 */
class DataSync
{

    /** @var IConnector */
    private $canvasDb;

    /** @var IConnector */
    private $schoolboxDb;

    /** @var External_ClassInterface */
    private $externalClass;

    /** @var External_UserInterface */
    private $externalUser;

    /** @var MarkTypeMapper */
    private $markTypeMapper;

    /** @var WorkType\Mapper */
    private $workTypeMapper;

    /** @var Quiz\Mapper */
    private $quizMapper;

    /** @var QuizSection\Mapper */
    private $quizSectionMapper;

    /** @var QuizQuestion\Mapper */
    private $quizQuestionMapper;

    /** @var QuestionType\Mapper */
    private $quizQuestionTypeMapper;

    /** @var QuizSolution\Mapper */
    private $quizSolutionMapper;

    /** @var SubmissionBoxMapper */
    private $submissionBoxMapper;

    /** @var Assessment\Mapper */
    private $assessmentMapper;

    /** @var Participant\Plan\Mapper */
    private $planMapper;

    /** @var Participant\Mapper */
    private $participantMapper;

    /** @var Folder\Mapper */
    private $folderMapper;

    /** @var UserMapper */
    private $userMapper;

    /** @var CourseImpl\Mapper */
    private $courseImplMapper;

    /** @var CourseImpl\Mapper */
    private $courseSpecMapper;

    /** @var UnitImpl\Mapper */
    private $unitImplMapper;

    /** @var UnitImpl\Mapper */
    private $unitSpecMapper;

    /** @var IConfigLoader */
    private $config;

    /** @var IConnector */
    private $persist;

    /** @var LoggerInterface */
    private $log;


    /**
     * @param IConnector              $canvasDb
     * @param IConnector              $schoolboxDb
     * @param External_ClassInterface $externalClass
     * @param External_UserInterface  $externalUser
     * @param MarkTypeMapper          $markTypeMapper
     * @param WorkType\Mapper         $workTypeMapper
     * @param Quiz\Mapper             $quizMapper
     * @param QuizSection\Mapper      $quizSectionMapper
     * @param QuizQuestion\Mapper     $quizQuestionMapper
     * @param QuestionType\Mapper     $quizQuestionTypeMapper
     * @param QuizSolution\Mapper     $quizSolutionMapper
     * @param SubmissionBoxMapper     $submissionBoxMapper
     * @param Assessment\Mapper       $assessmentMapper
     * @param Participant\Plan\Mapper $planMapper
     * @param Participant\Mapper      $participantMapper
     * @param Folder\Mapper           $folderMapper
     * @param UserMapper              $userMapper
     * @param CourseImpl\Mapper       $courseImplMapper
     * @param CourseSpec\Mapper       $courseSpecMapper
     * @param UnitImpl\Mapper         $unitImplMapper
     * @param UnitSpec\Mapper         $unitSpecMapper
     * @param IConfigLoader           $config
     * @param bool                    $persist Whether to persist changes to schoolbox database
     * @param LoggerInterface         $log
     */
    public function __construct(
        IConnector $canvasDb,
        IConnector $schoolboxDb,
        External_ClassInterface $externalClass,
        External_UserInterface $externalUser,
        MarkTypeMapper $markTypeMapper,
        WorkType\Mapper $workTypeMapper,
        Quiz\Mapper $quizMapper,
        QuizSection\Mapper $quizSectionMapper,
        QuizQuestion\Mapper $quizQuestionMapper,
        QuestionType\Mapper $quizQuestionTypeMapper,
        QuizSolution\Mapper $quizSolutionMapper,
        SubmissionBoxMapper $submissionBoxMapper,
        Assessment\Mapper $assessmentMapper,
        Participant\Plan\Mapper $planMapper,
        Participant\Mapper $participantMapper,
        Folder\Mapper $folderMapper,
        UserMapper $userMapper,
        CourseImpl\Mapper $courseImplMapper,
        CourseSpec\Mapper $courseSpecMapper,
        UnitImpl\Mapper $unitImplMapper,
        UnitSpec\Mapper $unitSpecMapper,
        IConfigLoader $config,
        bool $persist,
        LoggerInterface $log
    ) {
        $this->canvasDb               = $canvasDb;
        $this->schoolboxDb            = $schoolboxDb;
        $this->externalClass          = $externalClass;
        $this->externalUser           = $externalUser;
        $this->markTypeMapper         = $markTypeMapper;
        $this->workTypeMapper         = $workTypeMapper;
        $this->quizMapper             = $quizMapper;
        $this->quizSectionMapper      = $quizSectionMapper;
        $this->quizQuestionMapper     = $quizQuestionMapper;
        $this->quizQuestionTypeMapper = $quizQuestionTypeMapper;
        $this->quizSolutionMapper     = $quizSolutionMapper;
        $this->submissionBoxMapper    = $submissionBoxMapper;
        $this->assessmentMapper       = $assessmentMapper;
        $this->planMapper             = $planMapper;
        $this->participantMapper      = $participantMapper;
        $this->folderMapper           = $folderMapper;
        $this->userMapper             = $userMapper;
        $this->courseImplMapper       = $courseImplMapper;
        $this->courseSpecMapper       = $courseSpecMapper;
        $this->unitImplMapper         = $unitImplMapper;
        $this->unitSpecMapper         = $unitSpecMapper;
        $this->config                 = $config;
        $this->persist                = $persist;
        $this->log                    = $log;
    }


    /**
     * Synchronised the Canvas data in the local staging DB with Schoolbox DB.
     *
     * Execute in progressive stages.
     *
     * @throws Database\Exception
     * @throws Database\Predicate\InvalidColumnException
     * @throws Mapper\Exception
     * @throws PDOException
     * @throws UnexpectedValueException
     * @throws UnderflowException
     * @throws InvalidArgumentException
     * @throws DomainException
     */
    public function synchronise(): void
    {
        // If not persisting changes, wrap everything in a transaction we will subsequently rollback.
        if (!$this->persist) {
            $this->schoolboxDb->beginTransaction();
        }

        $this->log->notice("Building data structures to map Canvas entities to Schoolbox entities...");
        $accountBridge            = new Bridge\Account($this->schoolboxDb);
        $assignmentBridge         = new Bridge\Assignment(
            $this->config->get('site_location'),
            $this->markTypeMapper->findAll(),
            $this->workTypeMapper->findAll()
        );
        $courseBridge             = new Bridge\Course($this->canvasDb, $this->schoolboxDb);
        $courseGroupBridge        = new Bridge\CourseGroup($this->schoolboxDb, $accountBridge);
        $assignmentDatesBridge    = new Bridge\AssignmentDates();
        $assignmentOverrideBridge = new Bridge\AssignmentOverride($this->schoolboxDb);

        $this->syncAccounts($accountBridge);
        $this->syncCourses($accountBridge, $courseBridge, $courseGroupBridge);
        $this->syncCourseAssignments($assignmentBridge, $courseBridge);
        $this->syncCourseQuiz($assignmentBridge, $courseBridge);

        // Defer building this one till as late as possible (it can take a while)
        $sectionBridge = new Bridge\CourseSection(
            $this->canvasDb,
            $this->schoolboxDb,
            $this->folderMapper,
            $courseBridge,
            $this->log
        );
        $sectionBridge->execute();

        $this->syncCourseSections($courseBridge, $courseGroupBridge, $sectionBridge);
        $this->syncClassAssignments($assignmentDatesBridge, $courseBridge, $sectionBridge, $assignmentOverrideBridge);
        $this->syncClassQuiz($assignmentDatesBridge, $courseBridge, $sectionBridge, $assignmentOverrideBridge);

        if (!$this->persist) {
            $this->schoolboxDb->rollBack();
        }
    }

    /**
     * @param Bridge\Account $accountBridge
     * @throws Database\Exception
     * @throws Database\Predicate\InvalidColumnException
     * @throws Mapper\Exception
     * @throws PDOException
     * @throws UnderflowException
     * @throws UnexpectedValueException
     */
    private function syncAccounts(Bridge\Account $accountBridge): void
    {
        (new ImportStage\CourseAccounts(
            $this->canvasDb,
            $this->folderMapper,
            $this->config,
            $this->log,
            $accountBridge
        ))->synchronise();
    }

    /**
     * @param Bridge\Account     $accountBridge
     * @param Bridge\Course      $courseBridge
     * @param Bridge\CourseGroup $courseGroupBridge
     * @throws Database\Exception
     * @throws Database\Predicate\InvalidColumnException
     * @throws Mapper\Exception
     * @throws PDOException
     * @throws UnderflowException
     * @throws UnexpectedValueException
     */
    private function syncCourses(
        Bridge\Account $accountBridge,
        Bridge\Course $courseBridge,
        Bridge\CourseGroup $courseGroupBridge
    ): void {
        (new ImportStage\Courses(
            $this->canvasDb,
            $this->folderMapper,
            $this->courseSpecMapper,
            $this->unitSpecMapper,
            $this->log,
            $accountBridge,
            $courseBridge,
            $courseGroupBridge
        ))->synchronise();
    }

    /**
     * @param Bridge\Assignment $assignmentBridge
     * @param Bridge\Course     $courseBridge
     * @throws Database\Exception
     * @throws Database\Predicate\InvalidColumnException
     * @throws InvalidArgumentException
     * @throws Mapper\Exception
     * @throws PDOException
     * @throws UnderflowException
     * @throws UnexpectedValueException
     */
    private function syncCourseAssignments(Bridge\Assignment $assignmentBridge, Bridge\Course $courseBridge): void
    {
        (new ImportStage\CourseAssignments(
            $this->canvasDb,
            $this->schoolboxDb,
            $this->submissionBoxMapper,
            $this->assessmentMapper,
            $this->log,
            $assignmentBridge,
            $courseBridge,
            $this->folderMapper
        ))->synchronise();
    }

    /**
     * @param Bridge\Assignment $assignmentBridge
     * @param Bridge\Course     $courseBridge
     * @return void
     * @throws Database\Exception
     * @throws Database\Predicate\InvalidColumnException
     * @throws Mapper\Exception
     * @throws UnderflowException
     * @throws UnexpectedValueException
     * @throws PDOException
     */
    private function syncCourseQuiz(Bridge\Assignment $assignmentBridge, Bridge\Course $courseBridge): void
    {
        (new ImportStage\CourseQuiz(
            $this->canvasDb,
            $this->schoolboxDb,
            $this->quizMapper,
            $this->quizSectionMapper,
            $this->quizQuestionMapper,
            $this->quizQuestionTypeMapper,
            $this->quizSolutionMapper,
            $this->assessmentMapper,
            $this->folderMapper,
            $this->log,
            $assignmentBridge,
            $courseBridge
        ))->synchronise();
    }


    /**
     * @param Bridge\Course        $courseBridge
     * @param Bridge\CourseGroup   $courseGroupBridge
     * @param Bridge\CourseSection $sectionBridge
     * @throws Database\Exception
     * @throws Database\Predicate\InvalidColumnException
     * @throws DomainException
     * @throws InvalidArgumentException
     * @throws Mapper\Exception
     * @throws PDOException
     * @throws UnderflowException
     * @throws UnexpectedValueException
     */
    private function syncCourseSections(Bridge\Course $courseBridge, Bridge\CourseGroup $courseGroupBridge, Bridge\CourseSection $sectionBridge): void
    {
        // Import Canvas CourseSections + Assignments => Schoolbox CourseImpl/UnitImpl/AssessmentImpl
        (new ImportStage\CourseSections(
            $this->canvasDb,
            $this->schoolboxDb,
            $this->courseImplMapper,
            $this->courseSpecMapper,
            $this->unitImplMapper,
            $this->unitSpecMapper,
            $this->folderMapper,
            $this->log,
            $courseBridge,
            $courseGroupBridge,
            $sectionBridge,
            new Bridge\CourseSectionDates()
        ))->synchronise();

        if (!$this->persist) {
            $this->schoolboxDb->rollBack();
        }
    }

    /**
     * @param Bridge\AssignmentDates    $assignmentDatesBridge
     * @param Bridge\Course             $courseBridge
     * @param Bridge\CourseSection      $courseSectionBridge
     * @param Bridge\AssignmentOverride $assignmentOverrideBridge
     * @throws Database\Exception
     * @throws Database\Predicate\InvalidColumnException
     * @throws Mapper\Exception
     * @throws UnderflowException
     * @throws UnexpectedValueException
     * @throws PDOException
     */
    private function syncClassAssignments(
        Bridge\AssignmentDates $assignmentDatesBridge,
        Bridge\Course $courseBridge,
        Bridge\CourseSection $courseSectionBridge,
        Bridge\AssignmentOverride $assignmentOverrideBridge
    ): void {
        (new ImportStage\SectionAssignment(
            $this->canvasDb,
            $this->schoolboxDb,
            $this->externalClass,
            $this->externalUser->getCurrentYear(),
            $this->unitImplMapper,
            $this->unitSpecMapper,
            $this->submissionBoxMapper,
            $this->assessmentMapper,
            $this->planMapper,
            $this->participantMapper,
            $this->userMapper,
            $this->log,
            $assignmentDatesBridge,
            $courseBridge,
            $courseSectionBridge,
            $assignmentOverrideBridge
        ))->synchronise();
    }


    /**
     * @param Bridge\AssignmentDates    $assignmentDatesBridge
     * @param Bridge\Course             $courseBridge
     * @param Bridge\CourseSection      $courseSectionBridge
     * @param Bridge\AssignmentOverride $assignmentOverrideBridge
     * @throws Database\Exception
     * @throws Database\Predicate\InvalidColumnException
     * @throws Mapper\Exception
     * @throws PDOException
     * @throws UnderflowException
     * @throws UnexpectedValueException
     */
    private function syncClassQuiz(
        Bridge\AssignmentDates $assignmentDatesBridge,
        Bridge\Course $courseBridge,
        Bridge\CourseSection $courseSectionBridge,
        Bridge\AssignmentOverride $assignmentOverrideBridge
    ): void {
        (new ImportStage\SectionQuiz(
            $this->canvasDb,
            $this->schoolboxDb,
            $this->externalClass,
            $this->externalUser->getCurrentYear(),
            $this->unitImplMapper,
            $this->unitSpecMapper,
            $this->quizMapper,
            $this->quizSectionMapper,
            $this->quizQuestionMapper,
            $this->quizSolutionMapper,
            $this->assessmentMapper,
            $this->planMapper,
            $this->participantMapper,
            $this->userMapper,
            $this->log,
            $assignmentDatesBridge,
            $courseBridge,
            $courseSectionBridge,
            $assignmentOverrideBridge
        ))->synchronise();
    }
}

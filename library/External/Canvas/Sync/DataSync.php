<?php

/**
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace External\Canvas\Sync;

use Alaress\Database\Connector\IConnector;
use DomainException;
use External\Canvas\Importer\CanvasDb;
use External_ClassInterface;
use External_UserInterface;
use InvalidArgumentException;
use Alaress\Database;
use PDOException;
use Psr\Log\LoggerInterface;
use Schoolbox\Assessment\Component\SubmissionBox\GradeLevel\Group\Mapper as GradeLevelGroupMapper;
use Schoolbox\Assessment\Component\SubmissionBox\GradeLevel\Mapper as GradeLevelMapper;
use Schoolbox\Assessment\Component\SubmissionBox\MarkTypeMapper;
use Schoolbox\Assessment\Component\SubmissionBox\ReturnedWorkMapper;
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
    /** @var string */
    private string $siteSchoolCode;

    private string $schoolboxTimezone;

    /**
     * @param CanvasDb                   $canvasDb
     * @param IConnector                 $schoolboxDb
     * @param External_ClassInterface    $externalClass
     * @param External_UserInterface     $externalUser
     * @param MarkTypeMapper             $markTypeMapper
     * @param GradeLevelGroupMapper      $gradeLevelGroupMapper
     * @param GradeLevelMapper           $gradeLevelMapper
     * @param WorkType\Mapper            $workTypeMapper
     * @param Quiz\Mapper                $quizMapper
     * @param QuizSection\Mapper         $quizSectionMapper
     * @param QuizQuestion\Mapper        $quizQuestionMapper
     * @param QuestionType\Mapper        $quizQuestionTypeMapper
     * @param QuizSolution\Mapper        $quizSolutionMapper
     * @param Quiz\Attempt\Mapper        $quizAttemptMapper
     * @param Quiz\Attempt\Answer\Mapper $quizAnswerMapper
     * @param SubmissionBoxMapper        $submissionBoxMapper
     * @param ReturnedWorkMapper         $returnedWorkMapper
     * @param Assessment\MapperProxy     $assessmentDataSource
     * @param Assessment\Mapper          $assessmentMapper
     * @param Participant\Plan\Mapper    $planMapper
     * @param Participant\Mapper         $participantMapper
     * @param Folder\Mapper              $folderMapper
     * @param UserMapper                 $userMapper
     * @param CourseImpl\Mapper          $courseImplMapper
     * @param CourseSpec\Mapper          $courseSpecMapper
     * @param UnitImpl\Mapper            $unitImplMapper
     * @param UnitSpec\Mapper            $unitSpecMapper
     * @param IConfigLoader              $config
     * @param bool                       $persist Whether to persist changes to schoolbox database
     * @param LoggerInterface            $log
     */
    public function __construct(
        private CanvasDb $canvasDb,
        private IConnector $schoolboxDb,
        private External_ClassInterface $externalClass,
        private External_UserInterface $externalUser,
        private MarkTypeMapper $markTypeMapper,
        private GradeLevelGroupMapper $gradeLevelGroupMapper,
        private GradeLevelMapper $gradeLevelMapper,
        private WorkType\Mapper $workTypeMapper,
        private Quiz\Mapper $quizMapper,
        private QuizSection\Mapper $quizSectionMapper,
        private QuizQuestion\Mapper $quizQuestionMapper,
        private QuestionType\Mapper $quizQuestionTypeMapper,
        private QuizSolution\Mapper $quizSolutionMapper,
        private Quiz\Attempt\Mapper $quizAttemptMapper,
        private Quiz\Attempt\Answer\Mapper $quizAnswerMapper,
        private SubmissionBoxMapper $submissionBoxMapper,
        private ReturnedWorkMapper $returnedWorkMapper,
        private Assessment\MapperProxy $assessmentDataSource,
        private Assessment\Mapper $assessmentMapper,
        private Participant\Plan\Mapper $planMapper,
        private Participant\Mapper $participantMapper,
        private Folder\Mapper $folderMapper,
        private UserMapper $userMapper,
        private CourseImpl\Mapper $courseImplMapper,
        private CourseSpec\Mapper $courseSpecMapper,
        private UnitImpl\Mapper $unitImplMapper,
        private UnitSpec\Mapper $unitSpecMapper,
        private IConfigLoader $config,
        private bool $persist,
        private LoggerInterface $log
    ) {
        $this->siteSchoolCode         = strtolower($config->get('site_school_code'));
        $this->schoolboxTimezone      = $config->get('date_timezone');
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
        $this->schoolboxDb->beginTransaction();

        try {
            $bridgeFactory = new BridgeFactory($this->siteSchoolCode);

            $this->log->notice("Building data structures to map Canvas entities to Schoolbox entities...");
            $accountBridge    = new Bridge\Account($this->schoolboxDb);
            $assignmentBridge = $bridgeFactory->getAssignment(
                $this->markTypeMapper->findAll(),
                $this->workTypeMapper->findAll()
            );
            $assignmentBridge->prepareLetterGrades($this->gradeLevelGroupMapper, $this->gradeLevelMapper, $this->userMapper);
            $courseBridge             = $bridgeFactory->getCourse($this->canvasDb, $this->schoolboxDb);
            $courseGroupBridge        = new Bridge\CourseGroup($this->schoolboxDb, $accountBridge);
            $assignmentDatesBridge    = $bridgeFactory->getAssignmentDates();
            $assignmentOverrideBridge = new Bridge\AssignmentOverride($this->schoolboxDb);

            $this->syncAccounts($accountBridge);
            $this->syncCourses($accountBridge, $courseBridge, $courseGroupBridge);
            $this->syncCourseAssignments($assignmentBridge, $courseBridge);
            $this->syncCourseQuiz($assignmentBridge, $courseBridge);
            // Defer building this one till as late as possible (it can take a while)
            $sectionBridge = $bridgeFactory->getCourseSection(
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
        } catch (\Exception $e) {
            $this->log->critical(sprintf("Sync Failure: %s", $e->getMessage()));
            $this->schoolboxDb->rollBack();
            exit(1);
        }

        if ($this->persist) {
            $this->schoolboxDb->commit();
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
            $this->config,
            $assignmentBridge,
            $courseBridge,
            $this->folderMapper,
            $this->schoolboxTimezone
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
            $this->config,
            $assignmentBridge,
            $courseBridge,
            $this->schoolboxTimezone
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
            new Bridge\CourseSectionDates(),
            $this->schoolboxTimezone
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
            $this->returnedWorkMapper,
            $this->assessmentMapper,
            $this->planMapper,
            $this->participantMapper,
            $this->userMapper,
            $this->log,
            $assignmentDatesBridge,
            $courseBridge,
            $courseSectionBridge,
            $assignmentOverrideBridge,
            $this->schoolboxTimezone,
            $this->assessmentDataSource
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
            $this->quizAttemptMapper,
            $this->quizAnswerMapper,
            $this->assessmentMapper,
            $this->planMapper,
            $this->participantMapper,
            $this->userMapper,
            $this->log,
            $assignmentDatesBridge,
            $courseBridge,
            $courseSectionBridge,
            $assignmentOverrideBridge,
            $this->schoolboxTimezone,
            $this->assessmentDataSource
        ))->synchronise();
    }
}

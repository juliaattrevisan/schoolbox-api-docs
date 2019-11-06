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
use Schoolbox\Config\ConfigMapper;
use Schoolbox\Learning\Assessment\DataModel as Assessment;
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

    /** @var ConfigMapper */
    private $configMapper;

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
     * @param ConfigMapper            $configMapper
     * @param bool                    $persist Whether to persist changes to schoolbox database
     * @param LoggerInterface         $log
     */
    public function __construct(
        IConnector $canvasDb,
        IConnector $schoolboxDb,
        External_ClassInterface $externalClass,
        External_UserInterface $externalUser,
        MarkTypeMapper $markTypeMapper,
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
        ConfigMapper $configMapper,
        bool $persist,
        LoggerInterface $log
    ) {
        $this->canvasDb            = $canvasDb;
        $this->schoolboxDb         = $schoolboxDb;
        $this->externalClass       = $externalClass;
        $this->externalUser        = $externalUser;
        $this->markTypeMapper      = $markTypeMapper;
        $this->submissionBoxMapper = $submissionBoxMapper;
        $this->assessmentMapper    = $assessmentMapper;
        $this->planMapper          = $planMapper;
        $this->participantMapper   = $participantMapper;
        $this->folderMapper        = $folderMapper;
        $this->userMapper          = $userMapper;
        $this->courseImplMapper    = $courseImplMapper;
        $this->courseSpecMapper    = $courseSpecMapper;
        $this->unitImplMapper      = $unitImplMapper;
        $this->unitSpecMapper      = $unitSpecMapper;
        $this->configMapper        = $configMapper;
        $this->persist             = $persist;
        $this->log                 = $log;
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
        $accountBridge     = new Bridge\Account($this->schoolboxDb);
        $assignmentBridge  = new Bridge\Assignment($this->markTypeMapper->findAll());
        $courseBridge      = new Bridge\Course($this->canvasDb, $this->schoolboxDb);
        $courseGroupBridge = new Bridge\CourseGroup($this->schoolboxDb, $accountBridge);

        // Import Canvas Accounts => Schoolbox Faculty folders
        (new ImportStage\CourseAccounts(
            $this->canvasDb,
            $this->schoolboxDb,
            $this->folderMapper,
            $this->configMapper,
            $this->log,
            $accountBridge
        ))->synchronise();

        // Import Canvas Courses => Schoolbox CourseSpec/UnitSpec
        (new ImportStage\Courses(
            $this->canvasDb,
            $this->schoolboxDb,
            $this->submissionBoxMapper,
            $this->assessmentMapper,
            $this->folderMapper,
            $this->log,
            $accountBridge,
            $assignmentBridge,
            $courseBridge,
            $courseGroupBridge
        ))->synchronise();

        // Delay building this one, it takes a while.
        $sectionBridge = new Bridge\CourseSection(
            $this->canvasDb,
            $this->schoolboxDb,
            $this->folderMapper,
            $courseBridge,
            $this->log
        );
        $sectionBridge->execute();

        // Import Canvas CourseSections + Assignments => Schoolbox CourseImpl/UnitImpl/AssessmentImpl
        (new ImportStage\CourseSections(
            $this->canvasDb,
            $this->schoolboxDb,
            $this->externalClass,
            $this->externalUser->getCurrentYear(),
            $this->courseImplMapper,
            $this->courseSpecMapper,
            $this->unitImplMapper,
            $this->unitSpecMapper,
            $this->submissionBoxMapper,
            $this->assessmentMapper,
            $this->planMapper,
            $this->participantMapper,
            $this->folderMapper,
            $this->userMapper,
            $this->log,
            new Bridge\AssignmentDates(),
            $courseBridge,
            $courseGroupBridge,
            $sectionBridge,
            new Bridge\CourseSectionDates(),
            new Bridge\AssignmentOverride($this->schoolboxDb)
        ))->synchronise();

        if (!$this->persist) {
            $this->schoolboxDb->rollBack();
        }
    }
}

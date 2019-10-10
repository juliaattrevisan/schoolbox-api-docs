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
use DomainException;
use External\Canvas\Sync\Bridge;
use External_ClassInterface;
use PDO;
use PDOException;
use Psr\Log\LoggerInterface;
use Schoolbox\Assessment\Component\SubmissionBox;
use Schoolbox\Assessment\Component\SubmissionBoxMapper;
use Schoolbox\Learning\Assessment\DataModel as Assessment;
use Schoolbox\Assessment\Participant\Plan;
use Schoolbox\Learning\Course\DataModel\Implementation as CourseImpl;
use Schoolbox\Learning\Course\DataModel\Implementation\Unit as UnitImpl;
use Schoolbox\Learning\Course\DataModel\Specification as CourseSpec;
use Schoolbox\Learning\Course\DataModel\Specification\Unit as UnitSpec;
use Schoolbox\Mapper;
use Schoolbox\Resource\Folder;
use Schoolbox\Role;
use Schoolbox\User;
use Schoolbox\UserMapper;
use UnderflowException;
use UnexpectedValueException;
use function array_column;
use function array_key_exists;
use function array_map;
use function array_merge;
use function count;
use function implode;

/**
 * Synchronise Canvas CourseSections+Assignments => Schoolbox CourseImpl+UnitImpl+AssessmentImpl
 */
class CourseSections
{

    /** @var IConnector */
    private $canvasDb;

    /** @var IConnector */
    private $schoolboxDb;

    /** @var External_ClassInterface */
    private $externalClass;

    /** @var null|string */
    private $currentFileYear;

    /** @var CourseImpl\Mapper */
    private $courseImplMapper;

    /** @var CourseSpec\Mapper */
    private $courseSpecMapper;

    /** @var UnitImpl\Mapper */
    private $unitImplMapper;

    /** @var UnitSpec\Mapper */
    private $unitSpecMapper;

    /** @var SubmissionBoxMapper */
    private $submissionBoxMapper;

    /** @var Assessment\Mapper */
    private $assessmentMapper;

    /** @var Plan\Mapper */
    private $participantPlanMapper;

    /** @var Folder\Mapper */
    private $folderMapper;

    /** @var UserMapper */
    private $userMapper;

    /** @var LoggerInterface */
    private $log;

    /** @var Bridge\AssignmentDates */
    private $assignmentDatesBridge;

    /** @var Bridge\Course */
    private $courseBridge;

    /** @var Bridge\CourseGroup */
    private $courseGroupBridge;

    /** @var Bridge\CourseSection */
    private $courseSectionBridge;

    /** @var Bridge\CourseSectionDates */
    private $courseSectionDatesBridge;

    /**
     * Indexed by Canvas `assignment`.`canvas_id`
     *
     * @var SubmissionBox[]
     */
    private $submissionBoxSpecCache = [];

    // Per-execution counts

    // Canvas source entities
    private $countCanvasSectionsSynchronised;
    private $countCanvasAssignmentsSynchronised; // Σ[CourseSections * CourseAssignments] for all Course.

    // Schoolbox destination entities
    private $countCourseImplCreated;
    private $countCourseImplMoved;
    private $countCourseImplUpdated;
    private $countCourseImplDeleted;

    private $countUnitImplCreated;
    private $countUnitImplMoved;
    private $countUnitImplUpdated;
    private $countUnitImplDeleted;

    private $countSubmissionBoxCreated;
    private $countSubmissionBoxMoved;
    private $countSubmissionBoxUpdated;
    private $countSubmissionBoxDeleted;


    /**
     * @param IConnector                $canvasDb
     * @param IConnector                $schoolboxDb
     * @param External_ClassInterface   $externalClass
     * @param string|null               $currentFileYear
     * @param CourseImpl\Mapper         $courseImplMapper
     * @param CourseSpec\Mapper         $courseSpecMapper
     * @param UnitImpl\Mapper           $unitImplMapper
     * @param UnitSpec\Mapper           $unitSpecMapper
     * @param SubmissionBoxMapper       $submissionBoxMapper
     * @param Assessment\Mapper         $assessmentMapper
     * @param Plan\Mapper               $planMapper
     * @param Folder\Mapper             $folderMapper
     * @param UserMapper                $userMapper
     * @param LoggerInterface           $log
     * @param Bridge\AssignmentDates    $assignmentDatesBridge
     * @param Bridge\Course             $courseBridge
     * @param Bridge\CourseGroup        $courseGroupBridge
     * @param Bridge\CourseSection      $courseSectionBridge
     * @param Bridge\CourseSectionDates $courseSectionDatesBridge
     */
    public function __construct(
        IConnector $canvasDb,
        IConnector $schoolboxDb,
        External_ClassInterface $externalClass,
        ?string $currentFileYear,
        CourseImpl\Mapper $courseImplMapper,
        CourseSpec\Mapper $courseSpecMapper,
        UnitImpl\Mapper $unitImplMapper,
        UnitSpec\Mapper $unitSpecMapper,
        SubmissionBoxMapper $submissionBoxMapper,
        Assessment\Mapper $assessmentMapper,
        Plan\Mapper $planMapper,
        Folder\Mapper $folderMapper,
        UserMapper $userMapper,
        LoggerInterface $log,
        Bridge\AssignmentDates $assignmentDatesBridge,
        Bridge\Course $courseBridge,
        Bridge\CourseGroup $courseGroupBridge,
        Bridge\CourseSection $courseSectionBridge,
        Bridge\CourseSectionDates $courseSectionDatesBridge
    ) {
        $this->canvasDb                 = $canvasDb;
        $this->schoolboxDb              = $schoolboxDb;
        $this->externalClass            = $externalClass;
        $this->currentFileYear          = $currentFileYear;
        $this->courseImplMapper         = $courseImplMapper;
        $this->courseSpecMapper         = $courseSpecMapper;
        $this->unitImplMapper           = $unitImplMapper;
        $this->unitSpecMapper           = $unitSpecMapper;
        $this->submissionBoxMapper      = $submissionBoxMapper;
        $this->assessmentMapper         = $assessmentMapper;
        $this->participantPlanMapper    = $planMapper;
        $this->folderMapper             = $folderMapper;
        $this->userMapper               = $userMapper;
        $this->log                      = $log;
        $this->assignmentDatesBridge    = $assignmentDatesBridge;
        $this->courseBridge             = $courseBridge;
        $this->courseGroupBridge        = $courseGroupBridge;
        $this->courseSectionBridge      = $courseSectionBridge;
        $this->courseSectionDatesBridge = $courseSectionDatesBridge;
    }


    /**
     * @throws Database\Exception
     * @throws DomainException
     * @throws InvalidColumnException
     * @throws Mapper\Exception
     * @throws PDOException
     * @throws UnderflowException
     * @throws UnexpectedValueException
     */
    public function synchronise(): void
    {
        $this->log->info("Synchronising Canvas CourseSections + Assignments => CourseImpl, UnitImpl, AssessmentImpl...");
        $this->resetCounts();

        // all submissionBox.externalId
        $assignmentCanvasIds = $this->getAssignmentCanvasIds();
        // All sections.canvasId that match a SchoolboxId
        $sectionSisSourceIds = $this->courseSectionBridge->getMappedSisSourceIds();

        $data = [];
        if (count($assignmentCanvasIds) && count($sectionSisSourceIds)) {
            $assignmentCanvasIdBindings = Builder::getBindings($assignmentCanvasIds, 'a_cid');
            $sectionSisSourceIdBindings = Builder::getBindings($sectionSisSourceIds, 's_cid');

            $sql       = "
                SELECT 
                    account.canvas_id         'account.canvas_id',
                    course.code               'course.code',
                    course.canvas_id          'course.canvas_id',
                    course.start_at           'course.start_at',
                    course.conclude_at        'course.conclude_at',
                    courseTerm.date_start     'courseTerm.date_start',
                    courseTerm.date_end       'courseTerm.date_end',
                    section.canvas_id         'section.canvas_id',
                    section.name              'section.name',
                    section.sis_source_id     'section.sis_source_id',
                    section.start_at          'section.start_at',
                    section.end_at            'section.end_at',
                    sectionTerm.date_start    'sectionTerm.date_start',
                    sectionTerm.date_end      'sectionTerm.date_end',
                    assignment.canvas_id      'assignment.canvas_id',
                    assignment.unlock_at      'assignment.unlock_at',
                    assignment.lock_at        'assignment.lock_at',
                    assignment.due_at         'assignment.due_at',
                    assignment.all_day        'assignment.all_day',
                    assignment.all_day_date   'assignment.all_day_date'
                FROM course_section_dim section
                JOIN assignment_dim assignment ON assignment.course_id = section.course_id
                JOIN course_dim course ON course.id = section.course_id
                JOIN account_dim account ON account.id = course.account_id
                LEFT JOIN enrollment_term_dim sectionTerm ON section.enrollment_term_id = sectionTerm.id
                LEFT JOIN enrollment_term_dim courseTerm ON course.enrollment_term_id = courseTerm.id
                WHERE 
                    assignment.canvas_id IN ({$assignmentCanvasIdBindings->getPlaceholder()}) AND
                    section.sis_source_id IN ({$sectionSisSourceIdBindings->getPlaceholder()})
                ORDER BY account.canvas_id, course.code, course.canvas_id, section.canvas_id, assignment.canvas_id
            ";
            $params    = array_merge($assignmentCanvasIdBindings->getParams(), $sectionSisSourceIdBindings->getParams());
            $statement = $this->canvasDb->executeQuery($sql, $params);
            while ($row = $statement->fetch()) {
                $accountCanvasId    = $row['account.canvas_id'];
                $courseGroupCode    = $row['course.code'];
                $courseCanvasId     = $row['course.canvas_id'];
                $sectionCanvasId    = $row['section.canvas_id'];
                $assignmentCanvasId = $row['assignment.canvas_id'];

                if (!array_key_exists($accountCanvasId, $data)) {
                    $data[$accountCanvasId] = [];
                }
                if (!array_key_exists($courseGroupCode, $data[$accountCanvasId])) {
                    $data[$accountCanvasId][$courseGroupCode] = [];
                }
                if (!array_key_exists($courseCanvasId, $data[$accountCanvasId][$courseGroupCode])) {
                    $data[$accountCanvasId][$courseGroupCode][$courseCanvasId] = [
                        'start_at'        => $row['course.start_at'],
                        'conclude_at'     => $row['course.conclude_at'],
                        'term_date_start' => $row['courseTerm.date_start'],
                        'term_date_end'   => $row['courseTerm.date_end'],
                        'sections'        => [],
                    ];
                }
                if (!array_key_exists($sectionCanvasId, $data[$accountCanvasId][$courseGroupCode][$courseCanvasId]['sections'])) {
                    $data[$accountCanvasId][$courseGroupCode][$courseCanvasId]['sections'][$sectionCanvasId] = [
                        'name'            => $row['section.name'],
                        'sis_source_id'   => $row['section.sis_source_id'],
                        'start_at'        => $row['section.start_at'],
                        'end_at'          => $row['section.end_at'],
                        'term_date_start' => $row['sectionTerm.date_start'],
                        'term_date_end'   => $row['sectionTerm.date_end'],
                        'assignments'     => [],
                    ];
                }
                // First time we've seen this assignment in this section?
                if (!array_key_exists($sectionCanvasId, $data[$accountCanvasId][$courseGroupCode][$courseCanvasId]['sections'][$sectionCanvasId]['assignments'])) {
                    $data[$accountCanvasId][$courseGroupCode][$courseCanvasId]['sections'][$sectionCanvasId]['assignments'][$assignmentCanvasId] = [
                        'unlock_at'    => $row['assignment.unlock_at'],
                        'lock_at'      => $row['assignment.lock_at'],
                        'due_at'       => $row['assignment.due_at'],
                        'all_day'      => $row['assignment.all_day'],
                        'all_day_date' => $row['assignment.all_day_date'],
                    ];
                }
            }
        }

        // Track the IDs of all synchronised entities so we can delete any no longer in Canvas
        $courseImplSynchronised        = [];
        $unitImplSynchronised          = [];
        $submissionBoxImplSynchronised = [];

        foreach ($data as $accountCanvasId => $accountData) {
            foreach ($accountData as $courseGroupCode => $courseGroupData) {
                $courseSpecFolder = $this->courseGroupBridge->findCourseSpecFolder($this->folderMapper, $accountCanvasId, $courseGroupCode);
                if ($courseSpecFolder === null) {
                    throw new UnexpectedValueException("Unable to find folder for account.canvasId '$accountCanvasId' and course.code '$courseGroupCode'");
                }
                foreach ($courseGroupData as $courseCanvasId => $courseData) {
                    $unitSpecFolder = $this->courseBridge->findUnitSpecFolder($courseCanvasId);
                    if ($unitSpecFolder === null) {
                        throw new UnexpectedValueException("Unable to find folder for course.canvasId '$courseCanvasId'");
                    }
                    foreach ($courseData['sections'] as $sectionCanvasId => $section) {
                        $this->countCanvasSectionsSynchronised++;
                        $this->log->debug("Processing Canvas CourseSection (SisSourceId: '{$section['sis_source_id']}')");
                        $classFolder = $this->courseSectionBridge->getFolderByBySisSourceId($section['sis_source_id']);
                        if ($classFolder === null) {
                            throw new UnexpectedValueException("Unable to find folder for section.sis_source_id '{$section['sis_source_id']}'");
                        }
                        $courseImpl = $this->syncCourseSectionToCourseImpl($courseSpecFolder->getCourseSpecification(), $classFolder);
                        $unitImpl   = $this->syncCourseSectionWithUnitImpl(
                            $courseImpl,
                            $unitSpecFolder,
                            $section['name'],
                            $section['start_at'],
                            $section['end_at'],
                            $section['term_date_start'],
                            $section['term_date_end'],
                            $courseData['start_at'],
                            $courseData['conclude_at'],
                            $courseData['term_date_start'],
                            $courseData['term_date_end']
                        );

                        // Try to find a class teacher from SIS, this is likely more accurate that Course teacher user for Spec Assessment
                        $classTeacher = $this->getClassTeacher($section['sis_source_id']);

                        $courseImplSynchronised[] = $courseImpl->getId();
                        $unitImplSynchronised[]   = $unitImpl->getId();

                        foreach ($section['assignments'] as $assignmentCanvasId => $assignment) {
                            $this->countCanvasAssignmentsSynchronised++;
                            $this->log->debug("Processing Canvas Assignment (CanvasId: $assignmentCanvasId)");
                            $submissionBoxImpl               = $this->syncAssignmentWithSubmissionBoxImpl(
                                $assignmentCanvasId,
                                $unitImpl,
                                $classTeacher,
                                $assignment['unlock_at'],
                                $assignment['lock_at'],
                                $assignment['due_at'],
                                $assignment['all_day'] === 'true',
                                $assignment['all_day_date']
                            );
                            $submissionBoxImplSynchronised[] = $submissionBoxImpl->getId();
                        }
                    }
                }
            }
        }

        $this->deleteUnSynchronisedSubmissionBoxImpl($submissionBoxImplSynchronised);
        $this->deleteUnSynchronisedUnitImpl($unitImplSynchronised);
        $this->deleteUnSynchronisedCourseImpl($courseImplSynchronised);

        $this->printCounts();
    }


    /**
     * Returns a Schoolbox user who teachers the class (as per SIS).
     *
     * Only works for current file year
     *
     * @param string $sectionSisSourceId
     * @return User|null
     * @throws Mapper\Exception
     * @todo Add support for non-current file year
     * @todo Write custom query that can fetch ALL teachers for ALL file years in a SINGLE query for performance.
     *       This is currently one of the slower parts of the feature.
     */
    private function getClassTeacher(string $sectionSisSourceId): ?User
    {
        [$year, $folderCode] = $this->courseSectionBridge->getYearAndFolderCodeBySisSourceId($sectionSisSourceId);

        // Schoolbox does not support fetching teachers by folderCode for anything but the current file year
        // TODO write custom SIS external query to fetch teacher for historical classes
        if ($year !== $this->currentFileYear) {
            return null;
        }

        $this->log->debug("Fetching class teachers for folder code '$folderCode' for year '$year'");
        $externalIds = $this->externalClass->getTeachers($folderCode);
        $columnList  = ['synergy_id' => $externalIds, 'g.group_type' => Role::STAFF];
        return $this->userMapper->findByColumnList($columnList, ['limit' => 1])->first();
    }


    /**
     * Synchronised a Canvas CourseSection with Schoolbox CourseImpl
     *
     * @param CourseSpec\Model $courseSpec
     * @param Folder\Model     $classFolder
     * @return CourseImpl\Model
     * @throws Database\Exception
     * @throws InvalidColumnException
     * @throws Mapper\Exception
     * @throws PDOException
     * @throws UnexpectedValueException
     * @throws DomainException
     */
    private function syncCourseSectionToCourseImpl(CourseSpec\Model $courseSpec, Folder\Model $classFolder): CourseImpl\Model
    {
        $courseImpl = $this->findCourseImpl($classFolder);

        if (!$courseImpl) {
            $this->countCourseImplCreated++;
            $sisSourceId = $this->courseSectionBridge->getSisSourceIdForFolderId($classFolder->getId());
            $this->log->debug("Creating new CourseImpl for Canvas CourseSection (sisSourceId: $sisSourceId)");
            return $this->createCourseImpl($courseSpec, $classFolder);
        }

        $currentCourseSpec = $courseImpl->getSpecification();
        if ($currentCourseSpec->getId() !== $courseSpec->getId()) {
            $currentAccountId  = $this->courseGroupBridge->getAccountCanvasIdFromCourseSpecFolder($currentCourseSpec->getFolder());
            $currentCourseCode = $this->courseGroupBridge->getCourseCodeFromCourseSpecFolder($currentCourseSpec->getFolder());
            $newAccountId      = $this->courseGroupBridge->getAccountCanvasIdFromCourseSpecFolder($courseSpec->getFolder());
            $newCourseCode     = $this->courseGroupBridge->getCourseCodeFromCourseSpecFolder($courseSpec->getFolder());
            $this->log->debug(
                "Moving CourseImpl from Canvas Account (canvasId: $currentAccountId) + CourseGroup (courseCode: '$currentCourseCode') to " .
                "Account (canvasId: $newAccountId) + CourseGroup (courseCode: '$newCourseCode')"
            );
            $courseImpl->setSpecification($courseSpec);
            $this->courseImplMapper->save($courseImpl);
            $this->countCourseImplMoved++;
        }

        return $courseImpl;
    }


    /**
     * Synchronised a Canvas CourseSection with Schoolbox UnitImpl
     *
     * @param CourseImpl\Model $courseImpl
     * @param Folder\Model     $unitSpecFolder
     * @param string           $sectionName
     * @param string           $sectionStartAt
     * @param string           $sectionEndAt
     * @param string|null      $sectionTermDateStart
     * @param string|null      $sectionTermDateEnd
     * @param string|null      $courseStartAt
     * @param string|null      $courseConcludeAt
     * @param string|null      $courseTermDateStart
     * @param string|null      $courseTermDateEnd
     * @return UnitImpl\Model
     * @throws Database\Exception
     * @throws Mapper\Exception
     * @throws PDOException
     * @throws UnexpectedValueException
     */
    private function syncCourseSectionWithUnitImpl(
        CourseImpl\Model $courseImpl,
        Folder\Model $unitSpecFolder,
        string $sectionName,
        ?string $sectionStartAt,
        ?string $sectionEndAt,
        ?string $sectionTermDateStart,
        ?string $sectionTermDateEnd,
        ?string $courseStartAt,
        ?string $courseConcludeAt,
        ?string $courseTermDateStart,
        ?string $courseTermDateEnd
    ): UnitImpl\Model {

        $unitImpl = $this->findUnitImpl($courseImpl);

        [$openDate, $dueDate] = $this->courseSectionDatesBridge->unitImplDates(
            $sectionStartAt,
            $sectionEndAt,
            $sectionTermDateStart,
            $sectionTermDateEnd,
            $courseStartAt,
            $courseConcludeAt,
            $courseTermDateStart,
            $courseTermDateEnd
        );

        if (!$unitImpl) {
            $this->countUnitImplCreated++;
            $sisSourceId = $this->courseSectionBridge->getSisSourceIdForFolderId($courseImpl->getFolder()->getId());
            $this->log->debug("Creating new UnitImpl for Canvas CourseSection '$sectionName' (sisSourceId: $sisSourceId)");
            return $this->createUnitImpl($courseImpl, $unitSpecFolder->getUnitSpecification(), $openDate, $dueDate);
        }

        $currentUnitSpec = $unitImpl->getSpecification();
        if ($currentUnitSpec->getId() !== $unitSpecFolder->getUnitSpecification()->getId()) {
            $currentCourseCanvasId = $this->courseBridge->getCourseCanvasIdFromFolder($currentUnitSpec->getFolder());
            $newCourseCanvasId     = $this->courseBridge->getCourseCanvasIdFromFolder($unitSpecFolder);
            $this->log->debug("Moving UnitImpl (id: {$unitImpl->getId()}) from Canvas Course (canvasId: $currentCourseCanvasId) to (canvasId: $newCourseCanvasId)");
            $unitImpl->setSpecification($unitSpecFolder->getUnitSpecification());
            $this->unitImplMapper->save($unitImpl);
            $this->countUnitImplMoved++;
        }

        if (
            $unitImpl->getTitle() !== $unitSpecFolder->getName() ||
            $unitImpl->getOpenDate()->getDatabase() !== $openDate ||
            $unitImpl->getDueDate()->getDatabase() !== $dueDate
        ) {
            $this->countUnitImplUpdated++;
            $unitImpl->setTitle($unitSpecFolder->getName());
            $unitImpl->setOpenDate(Alaress_Date_Format::newDatabaseDate($openDate));
            $unitImpl->setDueDate(Alaress_Date_Format::newDatabaseDate($dueDate));
            $this->unitImplMapper->save($unitImpl);
        }

        return $unitImpl;
    }


    /**
     * Synchronise Canvas Assignment + CourseSection with Schoolbox SubmissionBox Impl
     *
     * @param int            $assignmentCanvasId
     * @param UnitImpl\Model $unitImpl
     * @param User|null      $classTeacher
     * @param string|null    $unlockAt
     * @param string|null    $lockAt
     * @param string|null    $dueAt
     * @param bool           $allDay
     * @param string|null    $allDayDate
     * @return SubmissionBox
     * @throws Database\Exception
     * @throws Mapper\Exception
     * @throws PDOException
     * @throws UnexpectedValueException
     */
    private function syncAssignmentWithSubmissionBoxImpl(
        int $assignmentCanvasId,
        UnitImpl\Model $unitImpl,
        ?User $classTeacher,
        ?string $unlockAt,
        ?string $lockAt,
        ?string $dueAt,
        bool $allDay,
        ?string $allDayDate
    ): SubmissionBox {
        $submissionBoxSpec = $this->findSubmissionBoxSpec($assignmentCanvasId);
        $submissionBoxImpl = $this->findSubmissionBoxImpl($submissionBoxSpec->getAssessmentModel(), $unitImpl);

        $unitOpenDate = $unitImpl->getOpenDate()->getDatabase();
        $unitDueDate  = $unitImpl->getDueDate()->getDatabase();

        [$publishDate, $openDate, $dueDate, $closeDate] = $this->assignmentDatesBridge
            ->assessmentDates($unlockAt, $dueAt, $allDay, $allDayDate, $lockAt, $unitOpenDate, $unitDueDate);
        [$publishDate, $openDate, $dueDate, $closeDate] = $this->assignmentDatesBridge
            ->normaliseDates($publishDate, $openDate, $dueDate, $closeDate);

        $owner = $classTeacher ?? $submissionBoxSpec->getOwner();

        $sisSourceId = $this->courseSectionBridge->getSisSourceIdForFolderId($unitImpl->getFolder()->getId());

        if (!$submissionBoxImpl) {
            $this->countSubmissionBoxCreated++;

            $this->log->debug("Creating new SubmissionBoxImpl for Canvas CourseSection (sisSourceId: '$sisSourceId') and Assignment (canvasId: $assignmentCanvasId)");
            return $this->createSubmissionBoxImpl($submissionBoxSpec, $unitImpl, $owner, $publishDate, $openDate, $dueDate, $closeDate);
        }
        $this->log->debug(
            "Synchronising Canvas Assignment(canvasId: $assignmentCanvasId) and CourseSection (sisSourceId: '$sisSourceId') "
            . "with SubmissionBoxSpec (id: {$submissionBoxImpl->getId()})"
        );

        $assessmentSpec = $submissionBoxSpec->getAssessmentModel();
        $assessmentImpl = $submissionBoxImpl->getAssessmentModel();

        $updated = false;
        if (
            $assessmentImpl->getTitle() !== $assessmentSpec->getTitle() ||
            $assessmentImpl->getDescription() !== $assessmentSpec->getDescription() ||
            $assessmentImpl->getSequence() !== $assessmentSpec->getSequence() ||
            $assessmentImpl->getPublishDate()->getDatabase() !== $publishDate ||
            $assessmentImpl->getOpenDate()->getDatabase() !== $openDate ||
            $assessmentImpl->getDueDate()->getDatabase() !== $dueDate ||
            $assessmentImpl->getCloseDate()->getDatabase() !== $closeDate
        ) {
            $this->log->debug("Updating AssessmentImpl for Canvas Assignment (canvasId: $assignmentCanvasId)");
            $updated = true;
            $assessmentImpl
                ->setTitle($assessmentSpec->getTitle())
                ->setDescription($assessmentSpec->getDescription())
                ->setPublishDate(Alaress_Date_Format::newDatabaseDate($publishDate))
                ->setOpenDate(Alaress_Date_Format::newDatabaseDate($openDate))
                ->setDueDate(Alaress_Date_Format::newDatabaseDate($dueDate))
                ->setCloseDate(Alaress_Date_Format::newDatabaseDate($closeDate))
                ->setSequence($assessmentSpec->getSequence());
            $this->assessmentMapper->save($assessmentImpl);
        }

        if (
            $submissionBoxImpl->getMaxMark() !== $submissionBoxSpec->getMaxMark() ||
            $submissionBoxImpl->getAcceptOnline() !== $submissionBoxSpec->getAcceptOnline() ||
            $submissionBoxImpl->getAcceptMultiple() !== $submissionBoxSpec->getAcceptMultiple() ||
            $submissionBoxImpl->getMarkVisibility() !== $submissionBoxSpec->getMarkVisibility() ||
            $submissionBoxImpl->getSelfReviewability() !== $submissionBoxSpec->getSelfReviewability() ||
            (int)$submissionBoxImpl->getOwner()->getId() !== (int)$owner->getId() ||
            (int)$submissionBoxImpl->getCategory()->getId() !== (int)$unitImpl->getFolder()->getId() ||
            (int)$submissionBoxImpl->getMarkType()->getId() !== (int)$submissionBoxSpec->getMarkType()->getId()  // TODO remove hydration bug compensation in 19.1
        ) {
            $this->log->debug("Updating SubmissionBoxImpl for Canvas Assignment (canvasId: $assignmentCanvasId)");
            $updated = true;
            $submissionBoxImpl
                ->setMaxMark($submissionBoxSpec->getMaxMark())
                ->setAcceptOnline($submissionBoxSpec->getAcceptOnline())
                ->setAcceptMultiple($submissionBoxSpec->getAcceptMultiple())
                ->setMarkVisibility($submissionBoxSpec->getMarkVisibility())
                ->setSelfReviewability($submissionBoxSpec->getSelfReviewability())
                ->setOwner($owner)
                ->setCategory($unitImpl->getFolder())
                ->setMarkType($submissionBoxSpec->getMarkType());
            $this->submissionBoxMapper->save($submissionBoxImpl);
        }
        if ($updated) {
            $this->countSubmissionBoxUpdated++;
        }

        return $submissionBoxImpl;
    }


    /**
     * Returns the submission box spec for the specified canvas assignment.canvas_id
     *
     * @param int $assignmentCanvasId
     * @return SubmissionBox
     * @throws Database\Exception
     * @throws UnexpectedValueException
     * @throws Mapper\Exception
     */
    private function findSubmissionBoxSpec(int $assignmentCanvasId): SubmissionBox
    {
        // Because Canvas Assignments (SB SubBox) belong to a Course, rather than CourseSection (SB Class),
        // we either need to either loop assignment per class, or classes per assignment.
        // Eg, if fetching assignments inside a class, we don't want to hit the DB next time we process the same logical
        // assignment in a different class.
        // Therefore cache the assignment fetch so subsequent class iterations don't hit the db.
        if (!array_key_exists($assignmentCanvasId, $this->submissionBoxSpecCache)) {
            $sql  = "
                SELECT
                    {$this->submissionBoxMapper->getColumnString('sb')},
                    {$this->assessmentMapper->getColumnString('a')}
                FROM submission_box sb
                JOIN assessment a ON a.id = sb.assessment_id
                WHERE sb.external_id = :sbExternalId AND a.deleted_at IS NULL
            ";
            $rows = $this->schoolboxDb->executeQuery($sql, ['sbExternalId' => $assignmentCanvasId])->fetchAll();
            if (!count($rows)) {
                throw new UnexpectedValueException("Unable to find a submission box spec for Canvas assignment.canvas_id $assignmentCanvasId");
            }
            if (count($rows) > 1) {
                $assessmentIds = implode(',', array_column($rows, 'a.id'));
                throw new UnexpectedValueException(
                    "Multiple SubmissionBox Specs found for assignment.canvas_id $assignmentCanvasId. Assessment Ids: $assessmentIds"
                );
            }
            $assessment    = $this->assessmentMapper->hydrate($rows[0], 'a');
            $submissionBox = $this->submissionBoxMapper->hydrate($rows[0], 'sb');

            $submissionBox->setAssessmentModel($assessment);
            $assessment->setDueWork($submissionBox);
            // TODO remove hydration bug compensation fix in 19.1.
            $assessment
                ->setQuiz()
                ->setTask()
                ->setProject();
            $this->submissionBoxSpecCache[$assignmentCanvasId] = $submissionBox;
        }

        return $this->submissionBoxSpecCache[$assignmentCanvasId];
    }


    /**
     * Returns the course implementation for the specified Canvas CourseSection.SisSourceId, null if none found.
     *
     * @param Folder\Model $classFolder
     * @return CourseImpl\Model|null
     * @throws Database\Exception
     * @throws InvalidColumnException
     * @throws UnexpectedValueException
     * @throws Mapper\Exception
     * @throws DomainException
     */
    private function findCourseImpl(Folder\Model $classFolder): ?CourseImpl\Model
    {
        $courseSpecFolderPb    = $this->courseGroupBridge->equalsAnyCourseGroupFolder('csf');
        $courseSpecFolderWhere = $courseSpecFolderPb->getWhere()[0];

        // In Canvas, a CourseSection belongs to a Course.
        // In Schoolbox, this logically equates to a Class Folder always having a single CourseImpl,
        // which belongs to a CourseSpec that relates to a CanvasCourse.
        // Any Schoolbox CourseImpl whose CourseSpec does not relate to a Canvas CourseGroup can be ignored.
        $sql = "
            SELECT 
                   {$this->courseImplMapper->getColumnString('ci')},
                   {$this->courseSpecMapper->getColumnString('cs')}
            FROM course_implementation ci
            JOIN course_specification cs on ci.course_specification_id = cs.id
            JOIN folder csf ON csf.id = cs.folder_id
            WHERE 
                ci.deleted_at IS NULL AND
                csf.deleted_at IS NULL AND 
                ci.folder_id = :ciFolderId AND
                $courseSpecFolderWhere
        ";

        $rows = $this->schoolboxDb->executeQuery($sql, array_merge($courseSpecFolderPb->getParams(), ['ciFolderId' => $classFolder->getId()]))->fetchAll();

        if (!count($rows)) {
            return null;
        }
        if (count($rows) > 1) {
            $courseSpecIds = implode(',', array_column($rows, 'ci.course_specification_id'));
            $sisSourceId   = $this->courseSectionBridge->getSisSourceIdForFolderId($classFolder->getId());
            throw new UnexpectedValueException(
                "Multiple CourseImplementations found for SisSourceId '$sisSourceId'. CourseSpecs: ($courseSpecIds)"
            );
        }
        $courseSpec = $this->courseSpecMapper->hydrate($rows[0], 'cs');
        $courseImpl = $this->courseImplMapper->hydrate($rows[0], 'ci');
        $courseSpec->setCourseImplementations($this->courseImplMapper->createCollection()->setOffset($courseImpl->getId(), $courseImpl));
        return $courseImpl->setSpecification($courseSpec);
    }


    /**
     * Returns the course implementation for the specified Canvas CourseSection.SisSourceId, null if none found.
     *
     * Hydrates:
     *   UnitImpl
     *   UnitImpl.UnitSpec
     *
     * @param CourseImpl\Model $courseImpl
     * @return CourseImpl\Model|null
     * @throws Database\Exception
     * @throws UnexpectedValueException
     * @throws Mapper\Exception
     */
    private function findUnitImpl(CourseImpl\Model $courseImpl): ?UnitImpl\Model
    {
        // A Canvas CourseSection is mapped to a 1:1:1:1 of SB CourseSpec:CourseImpl:UnitImpl:ClassFolder.
        $sql = "
            SELECT 
                   {$this->unitImplMapper->getColumnString('ui')},
                   {$this->unitSpecMapper->getColumnString('us')}
            FROM course_unit_implementation ui
            JOIN course_unit_specification us on ui.course_unit_specification_id = us.id
            WHERE 
                  ui.course_implementation_id = :ciId AND
                  ui.deleted_at IS NULL
        ";

        $rows = $this->schoolboxDb->executeQuery($sql, ['ciId' => $courseImpl->getId()])->fetchAll();

        if (!count($rows)) {
            return null;
        }
        if (count($rows) > 1) {
            throw new UnexpectedValueException("Multiple UnitImplementations found for CourseImpl {$courseImpl->getId()}");
        }
        $unitImpl = $this->unitImplMapper->hydrate($rows[0], 'ui');
        $unitSpec = $this->unitSpecMapper->hydrate($rows[0], 'us');
        $unitSpec->setUnitImplementations($this->unitImplMapper->createCollection()->setOffset($unitImpl->getId(), $unitImpl));
        return $unitImpl->setSpecification($unitSpec);
    }


    /**
     * Returns a SubmissionBox Impl for the specified AssessmentSpecId + UnitImplId, or NULL if none found
     *
     * Hydrates:
     *  submission_box
     *  submission_box.assessment
     *  submission_box.assessment.participant_plan (so we don't recreate plan due to mapper pre-save callback) TODO fix this properly in develop
     *
     * @param Assessment\Model $assessmentSpec
     * @param UnitImpl\Model   $unitImpl
     * @return SubmissionBox|null
     * @throws Database\Exception
     * @throws Mapper\Exception
     * @throws UnexpectedValueException
     */
    private function findSubmissionBoxImpl(Assessment\Model $assessmentSpec, UnitImpl\Model $unitImpl): ?SubmissionBox
    {
        $sql  = "
                SELECT
                    {$this->submissionBoxMapper->getColumnString('sb')},
                    {$this->assessmentMapper->getColumnString('a')},
                    {$this->participantPlanMapper->getColumnString('pp')}
                FROM submission_box sb
                JOIN assessment a ON a.id = sb.assessment_id
                LEFT JOIN participant_plan pp ON pp.assessment_id = a.id
                JOIN assessment_specification_implementation asi ON a.id = asi.implementation_assessment_id
                JOIN assessment_course_unit_implementation acui ON a.id = acui.assessment_id
                WHERE
                      asi.specification_assessment_id = :aSpecId AND
                      acui.course_unit_implementation_id = :unitImplId AND
                      a.deleted_at IS NULL
            ";
        $rows = $this->schoolboxDb->executeQuery($sql, ['aSpecId' => $assessmentSpec->getId(), 'unitImplId' => $unitImpl->getId()])->fetchAll();
        if (!count($rows)) {
            return null;
        }

        /** @var Assessment\Model[] $assessments */
        $assessments = [];
        /** @var SubmissionBox[] $submissionBoxes */
        $submissionBoxes = [];
        foreach ($rows as $row) {
            $assessmentId = $row['a.id'];
            $boxId        = $row['sb.id'];
            if (!isset($assessments[$assessmentId])) {
                $assessments[$assessmentId] = $this->assessmentMapper->hydrate($row, 'a')->setPlans($this->participantPlanMapper->createCollection());
            }
            $submissionBoxes[$boxId] = $submissionBoxes[$boxId] ?? $this->submissionBoxMapper->hydrate($row, 'sb');

            if ($row['pp.id']) {
                $plan = $this->participantPlanMapper->hydrate($row, 'pp');
                $assessments[$assessmentId]->getPlans()->setOffset($plan->getId(), $plan);
            }
        }

        if (count($assessments) > 1) {
            throw new UnexpectedValueException("Multiple SubBox Specs found for assessmentSpecId {$assessmentSpec->getId()} in UnitImpl {$unitImpl->getId()}");
        }
        $assessmentImpl = reset($assessments);
        $submissionBox  = reset($submissionBoxes);

        $submissionBox->setAssessmentModel($assessmentImpl);
        $assessmentImpl->setDueWork($submissionBox);
        $assessmentImpl->setSpecificationAssessment($assessmentSpec);
        $assessmentImpl->setContextUnitImplementation($unitImpl);
        return $submissionBox;
    }


    /**
     * Create and return a CourseImpl for the specified folder and CourseSpec
     *
     * @param CourseSpec\Model $courseSpec
     * @param Folder\Model     $classFolder
     * @return CourseImpl\Model
     * @throws UnexpectedValueException
     * @throws PDOException
     * @throws Mapper\Exception
     */
    private function createCourseImpl(CourseSpec\Model $courseSpec, Folder\Model $classFolder): CourseImpl\Model
    {
        $courseImpl = (new CourseImpl\Model())
            ->setFolder($classFolder)
            ->setSpecification($courseSpec);
        $this->courseImplMapper->save($courseImpl);
        return $courseImpl;
    }


    /**
     * Create and return a CourseImpl for the specified folder and CourseSpec
     *
     * @param CourseImpl\Model $courseImpl
     * @param UnitSpec\Model   $unitSpec
     * @param string           $startAt
     * @param string           $endAt
     * @return UnitImpl\Model
     * @throws Mapper\Exception
     * @throws PDOException
     * @throws UnexpectedValueException
     */
    private function createUnitImpl(CourseImpl\Model $courseImpl, UnitSpec\Model $unitSpec, string $startAt, string $endAt): UnitImpl\Model
    {
        $unitImpl = (new UnitImpl\Model())
            ->setCourse($courseImpl)
            ->setSpecification($unitSpec)
            ->setTitle($unitSpec->getFolder()->getName())
            ->setOpenDate(Alaress_Date_Format::newDatabaseDate($startAt))
            ->setDueDate(Alaress_Date_Format::newDatabaseDate($endAt));
        $this->unitImplMapper->save($unitImpl);
        return $unitImpl;
    }


    /**
     * Create and return SubmissionBox Impl
     *
     * @param SubmissionBox  $submissionBoxSpec
     * @param UnitImpl\Model $unitImpl
     * @param User           $owner
     * @param string         $publishDate
     * @param string         $openDate
     * @param string         $dueDate
     * @param string         $closeDate
     * @return SubmissionBox
     * @throws Mapper\Exception
     * @throws PDOException
     * @throws UnexpectedValueException
     */
    private function createSubmissionBoxImpl(
        SubmissionBox $submissionBoxSpec,
        UnitImpl\Model $unitImpl,
        User $owner,
        string $publishDate,
        string $openDate,
        string $dueDate,
        string $closeDate
    ): SubmissionBox {
        $assessmentSpec = $submissionBoxSpec->getAssessmentModel();
        $assessmentImpl = clone $assessmentSpec;
        /** @var SubmissionBox $submissionBoxImpl */
        $submissionBoxImpl = $assessmentImpl->getDueWork();
        $submissionBoxImpl
            ->setAssessmentModel($assessmentImpl)            // TODO This line compensates for a core bug. Fix this properly in develop.
            ->setCategory($unitImpl->getFolder())
            ->setOwner($owner)
            ->setExternalId(null);

        $assessmentImpl
            ->setAssessmentContext($unitImpl)
            ->setSpecificationAssessment($assessmentSpec)
            ->setPublishDate(Alaress_Date_Format::newDatabaseDate($publishDate))
            ->setOpenDate(Alaress_Date_Format::newDatabaseDate($openDate))
            ->setDueDate(Alaress_Date_Format::newDatabaseDate($dueDate))
            ->setCloseDate(Alaress_Date_Format::newDatabaseDate($closeDate));

        $this->assessmentMapper->save($assessmentImpl);          // TODO This will trigger the creation of a dynamic plan - fix this properly in develop.
        $this->submissionBoxMapper->save($submissionBoxImpl);

        return $submissionBoxImpl;
    }


    /**
     * Returns all submissionBox.externalId
     *
     * @return int[]
     * @throws Database\Exception
     */
    private function getAssignmentCanvasIds(): array
    {
        $sql      = "
            SELECT sb.external_id
            FROM assessment a
            JOIN submission_box sb on a.id = sb.assessment_id
            WHERE sb.external_id IS NOT NULL AND a.deleted_at IS NULL;
        ";
        $callback = static function (string $externalId): int {
            return (int)$externalId;
        };
        return array_map($callback, $this->schoolboxDb->executeQuery($sql)->fetchAll(PDO::FETCH_COLUMN));
    }


    /**
     * Resets all results to 0
     */
    private function resetCounts(): void
    {
        $this->countCanvasSectionsSynchronised    = 0;
        $this->countCanvasAssignmentsSynchronised = 0;

        $this->countCourseImplCreated = 0;
        $this->countCourseImplMoved   = 0;
        $this->countCourseImplUpdated = 0;
        $this->countCourseImplDeleted = 0;

        $this->countUnitImplCreated = 0;
        $this->countUnitImplMoved   = 0;
        $this->countUnitImplUpdated = 0;
        $this->countUnitImplDeleted = 0;

        $this->countSubmissionBoxCreated = 0;
        $this->countSubmissionBoxMoved   = 0;
        $this->countSubmissionBoxUpdated = 0;
        $this->countSubmissionBoxDeleted = 0;
    }


    /**
     * Deletes any SubmissionBoxImpl that appear to have been previously imported from a Canvas Course but no longer require synchronisation.
     *
     * @param array $submissionBoxImplSynchronised
     * @throws Database\Exception
     * @throws InvalidColumnException
     * @throws Mapper\Exception
     * @throws UnderflowException
     */
    private function deleteUnSynchronisedSubmissionBoxImpl(array $submissionBoxImplSynchronised): void
    {
        $pb = $this->assessmentMapper->getPredicateBuilder()
                                     ->notNull('sbSpec.external_id')
                                     ->isNull('aImpl.deleted_at');
        if (count($submissionBoxImplSynchronised)) {
            $pb->notIn('sbImpl.id', $submissionBoxImplSynchronised);
        }
        $whereClause = implode(' AND ', $pb->getWhere());

        $sql = "
            SELECT aImpl.id
            FROM assessment aImpl
            JOIN submission_box sbImpl ON aImpl.id = sbImpl.assessment_id
            JOIN assessment_specification_implementation asi ON aImpl.id = asi.implementation_assessment_id
            JOIN assessment aSpec ON aSpec.id = asi.specification_assessment_id
            JOIN submission_box sbSpec ON sbSpec.assessment_id = aSpec.id
            WHERE {$whereClause}
        ";

        $assessmentIdsToDelete = $this->schoolboxDb->executeQuery($sql, $pb->getParams())->fetchAll(PDO::FETCH_COLUMN);

        // TODO this should delete the submissionBox, not the Assessment
        $idColumn = $this->assessmentMapper->getIdentifierColumn();
        foreach ($this->assessmentMapper->findByColumn($idColumn, $assessmentIdsToDelete) as $assessmentImpl) {
            $this->log->debug("Deleting un-synchronised SubmissionBoxImpl (assessment.id: {$assessmentImpl->getId()})");
            $this->assessmentMapper->delete($assessmentImpl);
            $this->countSubmissionBoxDeleted++;
        }
    }


    /**
     * Deletes any UnitImpl that appear to have been previously imported from a Canvas Course but no longer require synchronisation.
     *
     * @param array $unitImplSynchronised
     * @throws Database\Exception
     * @throws InvalidColumnException
     * @throws Mapper\Exception
     * @throws UnderflowException
     */
    private function deleteUnSynchronisedUnitImpl(array $unitImplSynchronised): void
    {
        $pb = $this->courseBridge->equalsAnyCanvasCourse('usf');
        if (count($unitImplSynchronised)) {
            $pb->notIn('ui.id', $unitImplSynchronised);
        }
        $pb->isNull('ui.deleted_at');
        $whereClause = implode(' AND ', $pb->getWhere());

        $sql = "
            SELECT ui.id
            FROM course_unit_implementation ui
            JOIN course_unit_specification us ON us.id = ui.course_unit_specification_id
            JOIN folder usf ON usf.id = us.folder_id
            WHERE {$whereClause}
        ";

        $unitImplIdsToDelete = $this->schoolboxDb->executeQuery($sql, $pb->getParams())->fetchAll(PDO::FETCH_COLUMN);

        $idColumn = $this->unitImplMapper->getIdentifierColumn();
        foreach ($this->unitImplMapper->findByColumn($idColumn, $unitImplIdsToDelete) as $unitImpl) {
            $this->log->debug("Deleting un-synchronised UnitImpl (id: {$unitImpl->getId()})");
            $this->unitImplMapper->delete($unitImpl);
            $this->countUnitImplDeleted++;
        }
    }


    /**
     * Deletes any CourseImpl that appear to have been previously imported from a Canvas Course but no longer require synchronisation.
     *
     * @param array $courseImplSynchronised
     * @throws Database\Exception
     * @throws InvalidColumnException
     * @throws Mapper\Exception
     * @throws UnderflowException
     */
    private function deleteUnSynchronisedCourseImpl(array $courseImplSynchronised): void
    {
        $pb = $this->courseGroupBridge->equalsAnyCourseGroupFolder('csf');
        if (count($courseImplSynchronised)) {
            $pb->notIn('ci.id', $courseImplSynchronised);
        }
        $pb->isNull('ci.deleted_at');
        $whereClause = implode(' AND ', $pb->getWhere());

        $sql = "
            SELECT ci.id
            FROM course_implementation ci
            JOIN course_specification cs ON cs.id = ci.course_specification_id
            JOIN folder csf ON csf.id = cs.folder_id
            WHERE {$whereClause}
        ";

        $courseImplIdsToDelete = $this->schoolboxDb->executeQuery($sql, $pb->getParams())->fetchAll(PDO::FETCH_COLUMN);

        $idColumn = $this->courseImplMapper->getIdentifierColumn();
        foreach ($this->courseImplMapper->findByColumn($idColumn, $courseImplIdsToDelete) as $courseImpl) {
            $this->log->debug("Deleting un-synchronised CourseImpl (id: {$courseImpl->getId()})");
            $this->courseImplMapper->delete($courseImpl);
            $this->countCourseImplDeleted++;
        }
    }


    /**
     * Logs the results of this stage
     */
    private function printCounts(): void
    {
        $this->log->info($this->countCanvasSectionsSynchronised . ' Canvas CourseSections synchronised');
        $this->log->info($this->countCanvasAssignmentsSynchronised . ' Canvas CourseSections-Assignments synchronised');

        $this->log->info($this->countCourseImplCreated . ' Schoolbox CourseImpl created');
        $this->log->info($this->countCourseImplMoved . ' Schoolbox CourseImpl moved');
        $this->log->info($this->countCourseImplUpdated . ' Schoolbox CourseImpl updated');
        $this->log->info($this->countCourseImplDeleted . ' Schoolbox CourseImpl deleted');


        $this->log->info($this->countUnitImplCreated . ' Schoolbox UnitImpl created');
        $this->log->info($this->countUnitImplMoved . ' Schoolbox UnitImpl moved');
        $this->log->info($this->countUnitImplUpdated . ' Schoolbox UnitImpl updated');
        $this->log->info($this->countUnitImplDeleted . ' Schoolbox UnitImpl deleted');

        $this->log->info($this->countSubmissionBoxCreated . ' Schoolbox SubmissionBoxImpl created');
        $this->log->info($this->countSubmissionBoxMoved . ' Schoolbox SubmissionBoxImpl moved');
        $this->log->info($this->countSubmissionBoxUpdated . ' Schoolbox SubmissionBoxImpl updated');
        $this->log->info($this->countSubmissionBoxDeleted . ' Schoolbox SubmissionBoxImpl deleted');
    }
}

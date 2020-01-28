<?php
/**
 * @copyright Copyright 2020, Schoolbox Pty Ltd
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
use External\Canvas\Sync\ImportStage\Traits\ModelSaveTrait;
use External_ClassInterface;
use PDOException;
use Psr\Log\LoggerInterface;
use Schoolbox\GenericCollection;
use Schoolbox\Learning\Assessment\DataModel as Assessment;
use Schoolbox\Learning\Assessment\DataModel\IAssessableDataModel;
use Schoolbox\Assessment\Participant;
use Schoolbox\Learning\Course\DataModel\Implementation\Unit as UnitImpl;
use Schoolbox\Learning\Course\DataModel\Specification\Unit as UnitSpec;
use Schoolbox\Mapper;
use Schoolbox\Role;
use Schoolbox\User;
use Schoolbox\UserMapper;
use UnderflowException;
use UnexpectedValueException;
use function array_column;
use function array_diff;
use function array_filter;
use function array_intersect;
use function array_key_exists;
use function array_keys;
use function array_push;
use function array_unique;
use function count;
use function implode;
use function is_array;
use function reset;
use function ucfirst;

/**
 * Common implementation to import Canvas Class Assignments+Quizzes.
 *
 * The is a large degree of similarity between these entities, particularly around the overrides,
 * which map logically to Schoolbox plans + participation.
 */
abstract class SectionAssessmentAbstract
{
    use ModelSaveTrait;

    /** @var IConnector */
    private $canvasDb;

    /** @var IConnector */
    private $schoolboxDb;

    /** @var External_ClassInterface */
    private $externalClass;

    /** @var null|string */
    private $currentFileYear;

    /** @var UnitImpl\Mapper */
    private $unitImplMapper;

    /** @var UnitSpec\Mapper */
    private $unitSpecMapper;

    /** @var Assessment\Mapper */
    private $assessmentMapper;

    /** @var Participant\Plan\Mapper */
    private $participantPlanMapper;

    /** @var Participant\Mapper */
    private $participantMapper;

    /** @var UserMapper */
    private $userMapper;

    /** @var LoggerInterface */
    private $log;

    /** @var Bridge\AssignmentDates */
    private $assignmentDatesBridge;

    /** @var Bridge\Course */
    private $courseBridge;

    /** @var Bridge\CourseSection */
    private $courseSectionBridge;

    /** @var Bridge\AssignmentOverride */
    private $assignmentOverrideBridge;


    // Per-execution counts

    // Canvas source entities
    private $countCanvasSectionAssessmentsSynchronised; // Σ[CourseSections * CourseAssessment] for all Course.
    private $countCanvasAssessmentOverridesSynchronised;

    // Schoolbox destination entities
    private $countAssessablesCreated;
    private $countAssessablesMoved;
    private $countAssessablesUpdated;
    private $countAssessablesDeleted;

    private $countPlansCreated;
    private $countPlansUpdated;
    private $countPlansDeleted;

    private $countParticipantsCreated;
    private $countParticipantsUpdated;
    private $countParticipantsDeleted;


    /**
     * @param IConnector                $canvasDb
     * @param IConnector                $schoolboxDb
     * @param External_ClassInterface   $externalClass
     * @param string|null               $currentFileYear
     * @param UnitImpl\Mapper           $unitImplMapper
     * @param UnitSpec\Mapper           $unitSpecMapper
     * @param Assessment\Mapper         $assessmentMapper
     * @param Participant\Plan\Mapper   $planMapper
     * @param Participant\Mapper        $participantMapper
     * @param UserMapper                $userMapper
     * @param LoggerInterface           $log
     * @param Bridge\AssignmentDates    $assignmentDatesBridge
     * @param Bridge\Course             $courseBridge
     * @param Bridge\CourseSection      $courseSectionBridge
     * @param Bridge\AssignmentOverride $assignmentOverrideBridge
     */
    public function __construct(
        IConnector $canvasDb,
        IConnector $schoolboxDb,
        External_ClassInterface $externalClass,
        ?string $currentFileYear,
        UnitImpl\Mapper $unitImplMapper,
        UnitSpec\Mapper $unitSpecMapper,
        Assessment\Mapper $assessmentMapper,
        Participant\Plan\Mapper $planMapper,
        Participant\Mapper $participantMapper,
        UserMapper $userMapper,
        LoggerInterface $log,
        Bridge\AssignmentDates $assignmentDatesBridge,
        Bridge\Course $courseBridge,
        Bridge\CourseSection $courseSectionBridge,
        Bridge\AssignmentOverride $assignmentOverrideBridge
    ) {
        $this->canvasDb                 = $canvasDb;
        $this->schoolboxDb              = $schoolboxDb;
        $this->externalClass            = $externalClass;
        $this->currentFileYear          = $currentFileYear;
        $this->unitImplMapper           = $unitImplMapper;
        $this->unitSpecMapper           = $unitSpecMapper;
        $this->assessmentMapper         = $assessmentMapper;
        $this->participantPlanMapper    = $planMapper;
        $this->participantMapper        = $participantMapper;
        $this->userMapper               = $userMapper;
        $this->log                      = $log;
        $this->assignmentDatesBridge    = $assignmentDatesBridge;
        $this->courseBridge             = $courseBridge;
        $this->courseSectionBridge      = $courseSectionBridge;
        $this->assignmentOverrideBridge = $assignmentOverrideBridge;
    }


    /**
     * Returns the Canvas entity being synced by this class. One of ['assignment','quiz']
     *
     * @return string
     */
    abstract protected function getCanvasEntity(): string;


    /**
     * Returns the Schoolbox entity being synced by this class. One of ['submissionBox','quiz']
     *
     * @return string
     */
    abstract protected function getSchoolboxEntity(): string;


    /**
     * @throws Database\Exception
     * @throws InvalidColumnException
     * @throws Mapper\Exception
     * @throws UnderflowException
     * @throws UnexpectedValueException
     * @throws PDOException
     */
    public function synchronise(): void
    {
        $canvasEntity    = ucfirst($this->getCanvasEntity());
        $schoolboxEntity = ucfirst($this->getSchoolboxEntity());
        $this->log->notice("Synchronising Canvas CourseSections + $canvasEntity => {$schoolboxEntity}Impl...");

        $this->resetCounts();

        $sectionSisSourceIds   = $this->courseSectionBridge->getMappedSisSourceIds();
        $coursesSectionData    = $this->loadCanvasSectionStudentData($sectionSisSourceIds);
        $coursesAssessmentData = $this->loadCanvasAssessmentAndOverrideData($sectionSisSourceIds);

        /** @var UnitImpl\Model[] $unitImplCache Indexed by `section`.`sisSourceId` */
        $unitImplCache = [];

        /** @var int[] $assessmentImplIdSynchronised */
        $assessmentImplIdSynchronised = [];

        /** @var Participant\Plan\Model[] $plansSynchronised Indexed by plan.id */
        $plansSynchronised = [];

        /** @var User[] $classTeacherCache Indexed by `section`.`sisSourceId` */
        $classTeacherCache = [];

        foreach ($coursesSectionData as $courseCanvasId => $courseSectionsData) {
            $this->log->info("Processing Canvas Course '{$courseSectionsData['name']}' (CanvasId: $courseCanvasId)");
            $unitSpec = $this->unitSpecMapper->findOneByPredicates($this->courseBridge->equalsCanvasCourse($courseCanvasId, 'f'));
            if (!array_key_exists($courseCanvasId, $coursesAssessmentData)) {
                continue;
            }
            foreach ($coursesAssessmentData[$courseCanvasId] as $assessmentCanvasId => $assessment) {
                /* We need to examine the Canvas assignment/quiz and assignment-overrides to determine:
                 * - What class folders does this assignment/quiz need to be copied to?
                 * - What plans are required for the Schoolbox assessments?
                 *
                 * It is quite possible for a user in canvas to be associated with an assignment/quiz via multiple overrides. The precedence is:
                 * 1. Section
                 * 2. Group
                 * 3. Individual (aka 'Ad Hoc')
                 * 4. Course (aka 'everyone')
                 *
                 * It appears it's also quite possible for the same user to be enrolled in multiple sections for the same course.
                 *
                 * if(section override)
                 *   copy to section's folder w/ dynamic plan
                 * elseif(assignment.visibility = 'everyone')
                 *   then copy to all course sections (minus any section overrides), with dynamic plans
                 * elseif(group/adHoc overrides)
                 *   derive override user set
                 *   derive section user sets from canvas
                 */
                $this->log->info("Processing Canvas {$canvasEntity} '{$assessment['title']}' (CanvasId: $assessmentCanvasId)");

                // Need to check we do not create assessments and static plans for group/adHoc students
                // if those students are not present in Schoolbox. This implies that we don't create an
                // assessment in Schoolbox if no students can participate in it.
                // Build a map of students' externalIds => user.id
                $studentExternalIdUserIdMap = $this->buildStudentExternalIdUserIdMap();

                // Split overrides (particularly group+adHoc overrides, sections are trivial) over sections.
                // Section override     => 1 section
                // AdHoc/Group override => N sections (depending on which sections Group/AdHoc users are enrolled in)
                /**
                 * Indexed by `section`.`sis_source_id`
                 *
                 * @var $overridesPerSection
                 */
                $overridesPerSection = $this->buildOverridesPerSection($assessment, $courseSectionsData['sections'], $studentExternalIdUserIdMap);

                // We can now sync the assignment + re-structured override data with Schoolbox DB.
                foreach ($courseSectionsData['sections'] as $sectionCanvasId => $section) {
                    $sectionSisSourceId = $section['sis_source_id'];
                    // Do we not have overrides for this section and assignment is not for everyone?
                    if (!array_key_exists($sectionSisSourceId, $overridesPerSection) && $assessment['visibility'] !== 'everyone') {
                        // No need to import this assignment/quiz into this class.
                        continue;
                    }
                    // We need to create the assessment, we will need a CourseImpl + UnitImpl
                    // Use a cache, because we may encounter the same class for multiple assignments.
                    $classFolder = $this->courseSectionBridge->getFolderByBySisSourceId($sectionSisSourceId);
                    if ($classFolder === null) {
                        throw new UnexpectedValueException("Unable to find folder for section.sis_source_id '$sectionSisSourceId'");
                    }
                    if (!array_key_exists($sectionSisSourceId, $unitImplCache)) {
                        $unitImplCache[$sectionSisSourceId] = $this->unitImplMapper->findOneByColumnList([
                            'cf.id' => $classFolder->getId(),
                            's.id'  => $unitSpec->getId(),
                        ]);
                    }
                    $unitImpl = $unitImplCache[$sectionSisSourceId];

                    if (!array_key_exists($sectionSisSourceId, $classTeacherCache)) {
                        // Try to find a class teacher from SIS, this is likely more accurate that Course teacher user for Spec Assessment
                        $classTeacherCache[$sectionSisSourceId] = $this->getClassTeacher($sectionSisSourceId);
                    }
                    $classTeacher = $classTeacherCache[$sectionSisSourceId];

                    // If assignment has a section override, then use section dates as assignment dates, so section overrides dates can be NULL
                    $sectionOverride = null;
                    foreach ($overridesPerSection[$sectionSisSourceId] ?? [] as $overrideCanvasId => $override) {
                        if (count($override['sections'])) {
                            $sectionOverride = $override;
                            break;
                        }
                    }
                    if ($sectionOverride) {
                        $unlockAt   = $sectionOverride['unlock_at'];
                        $lockAt     = $sectionOverride['lock_at'];
                        $dueAt      = $sectionOverride['due_at'];
                        $allDay     = $sectionOverride['all_day'];
                        $allDayDate = $sectionOverride['all_day_date'];
                    } else {
                        $unlockAt   = $assessment['unlock_at'];
                        $lockAt     = $assessment['lock_at'];
                        $dueAt      = $assessment['due_at'];
                        $allDay     = $assessment['all_day'];
                        $allDayDate = $assessment['all_day_date'];
                    }

                    $assessableImpl = $this->syncAssessableImpl(
                        $assessmentCanvasId,
                        $unitImpl,
                        $classTeacher,
                        $unlockAt,
                        $lockAt,
                        $dueAt,
                        $allDay,
                        $allDayDate
                    );

                    $assessmentImplId                                = $assessableImpl->getAssessmentModel()->getId();
                    $assessmentImplIdSynchronised[$assessmentImplId] = $assessmentImplId;
                    $this->countCanvasSectionAssessmentsSynchronised++;

                    $hasSectionOverride = false;
                    foreach ($overridesPerSection[$sectionSisSourceId] ?? [] as $overrideCanvasId => $override) {
                        if (count($override['sections'])) {
                            $hasSectionOverride = true;
                        }
                        $participants = null;
                        if (count($override['groups'])) {
                            $participants = reset($override['groups'])['members'];
                        } elseif (count($override['ad_hoc_users'])) {
                            $participants = $override['ad_hoc_users'];
                        }
                        foreach ($participants ?? [] as $idx => $externalId) {
                            if (!array_key_exists($externalId, $studentExternalIdUserIdMap)) {
                                $this->log->warning("Unable to sync participant record for student '$externalId', no user found in Schoolbox");
                                unset($participants[$idx]);
                            }
                        }
                        $plan = $this->syncOverrideWithPlan(
                            $assessableImpl->getAssessmentModel(),
                            $overrideCanvasId,
                            $override['title'],
                            $override['unlock_at'],
                            $override['lock_at'],
                            $override['due_at'],
                            $override['all_day'],
                            $override['all_day_date'],
                            $participants,
                            $studentExternalIdUserIdMap
                        );

                        $plansSynchronised[$plan->getId()] = $plan;
                    }
                    // If we didn't have a section override, and assignment is for everyone in course,
                    // create a dynamic plan for this class.
                    if (!$hasSectionOverride && $assessment['visibility'] === 'everyone') {
                        $plan = $this->syncOverrideWithPlan(
                            $assessableImpl->getAssessmentModel(),
                            null,
                            null,
                            null,
                            null,
                            null,
                            null,
                            null,
                            null,
                            $studentExternalIdUserIdMap
                        );

                        $plansSynchronised[$plan->getId()] = $plan;
                    }
                }
            }
        }

        $this->deleteUnSynchronisedAssessableImpl($assessmentImplIdSynchronised);
        $this->deleteUnSynchronisedAssessablePlans($this->getCandidatePlanIds(), array_keys($plansSynchronised));

        $this->printCounts();
    }


    /**
     * Resets all results to 0
     */
    protected function resetCounts(): void
    {
        $this->countCanvasSectionAssessmentsSynchronised  = 0;
        $this->countCanvasAssessmentOverridesSynchronised = 0;

        $this->countAssessablesCreated = 0;
        $this->countAssessablesMoved   = 0;
        $this->countAssessablesUpdated = 0;
        $this->countAssessablesDeleted = 0;

        $this->countPlansCreated = 0;
        $this->countPlansUpdated = 0;
        $this->countPlansDeleted = 0;

        $this->countParticipantsCreated = 0;
        $this->countParticipantsUpdated = 0;
        $this->countParticipantsDeleted = 0;
    }


    /**
     * Deletes any AssessableImpl that:
     * * is of this class's entity type,
     * * appears to have been previously imported from a Canvas Course, and
     * * no longer require synchronisation.
     *
     * @param int[] $assessmentImplIdsSynchronised
     * @throws Database\Exception
     * @throws Mapper\Exception
     */
    protected function deleteUnSynchronisedAssessableImpl(array $assessmentImplIdsSynchronised): void
    {
        $candidateAssessmentIds = $this->getAssessableAssessmentIds();
        if (!count($candidateAssessmentIds)) {
            return;
        }

        $schoolboxEntity       = ucfirst($this->getSchoolboxEntity());
        $assessmentIdsToDelete = array_diff($candidateAssessmentIds, $assessmentImplIdsSynchronised);

        // TODO this should delete the Assessable, not the Assessment
        $idColumn = $this->assessmentMapper->getIdentifierColumn();
        foreach ($this->assessmentMapper->findByColumn($idColumn, $assessmentIdsToDelete) as $assessmentImpl) {
            $this->log->debug("Deleting un-synchronised {$schoolboxEntity}Impl (assessment.id: {$assessmentImpl->getId()})");
            $this->assessmentMapper->delete($assessmentImpl);
            $this->countAssessablesDeleted++;
        }
    }


    /**
     * Returns all assessment ids for this assessable type that appear to have been imported
     *
     * @return int[]
     * @throws Database\Exception
     */
    abstract protected function getAssessableAssessmentIds(): array;


    /**
     * Deletes any Plan that:
     * * belong to schoolbox assessment of this class's type,
     * * appear to have been previously imported from Canvas, and
     * * no longer require synchronisation.
     *
     * @param int[] $candidatePlanIds    All SubBox plan ids
     * @param int[] $synchronisedPlanIds Plan ids we wish to preserve
     * @throws InvalidColumnException
     * @throws Mapper\Exception
     * @throws UnderflowException
     */
    protected function deleteUnSynchronisedAssessablePlans(array $candidatePlanIds, array $synchronisedPlanIds): void
    {
        // 9737 + 2411
        $planIdsToDelete = array_diff($candidatePlanIds, $synchronisedPlanIds);
        if (!count($planIdsToDelete)) {
            return;
        }

        $planTable = $this->participantPlanMapper->getTable();
        $pb        = $this->assignmentOverrideBridge->equalsAnyCanvasOverride($planTable);
        $pb
            ->isNull("{$planTable}.deleted_at")
            ->in("{$planTable}.id", $planIdsToDelete);

        $plansAndParticipants = $this->participantPlanMapper->findPlansAndParticipants($pb);
        foreach ($plansAndParticipants as $plan) {
            $this->log->debug("Deleting un-synchronised Plan '{$plan->getName()}' (id: {$plan->getId()})");
            foreach ($plan->getParticipants() as $participant) {
                $user = $participant->getUser();
                // This implies that if we add participants manually in Schoolbox, they will also be deleted here.
                $this->log->debug("Deleting un-synchronised Plan participant (user: '{$user->getFullName()}') (user.externalId: {$user->getExternalId()})");
                $this->participantMapper->delete($participant);
                $this->countParticipantsDeleted++;
            }
            $this->participantPlanMapper->delete($plan);
            $this->countPlansDeleted++;
        }
    }


    /**
     * Returns the ids of all plans that are for assessments of this class's schoolbox entity
     *
     * @return int[]
     * @throws Database\Exception
     */
    abstract protected function getCandidatePlanIds(): array;


    /**
     * Synchronise Canvas Assignment/Quiz + CourseSection with Schoolbox SubmissionBox/Quiz Impl
     *
     * @param int            $assessmentCanvasId
     * @param UnitImpl\Model $unitImpl
     * @param User|null      $classTeacher
     * @param string|null    $unlockAt
     * @param string|null    $lockAt
     * @param string|null    $dueAt
     * @param bool           $allDay
     * @param string|null    $allDayDate
     * @return IAssessableDataModel
     * @throws Database\Exception
     * @throws Mapper\Exception
     * @throws UnexpectedValueException
     * @throws PDOException
     */
    protected function syncAssessableImpl(
        int $assessmentCanvasId,
        UnitImpl\Model $unitImpl,
        ?User $classTeacher,
        ?string $unlockAt,
        ?string $lockAt,
        ?string $dueAt,
        bool $allDay,
        ?string $allDayDate
    ): IAssessableDataModel {
        $assessableSpec = $this->findAssessableSpec($assessmentCanvasId);
        $assessableImpl = $this->findAssessableImpl($assessableSpec->getAssessmentModel(), $unitImpl);

        $unitOpenDate = $unitImpl->getOpenDate()->getDatabase();
        $unitDueDate  = $unitImpl->getDueDate()->getDatabase();

        [$publishDate, $openDate, $dueDate, $closeDate] = $this->assignmentDatesBridge
            ->assessmentDates($unlockAt, $dueAt, $allDay, $allDayDate, $lockAt, $unitOpenDate, $unitDueDate);
        [$publishDate, $openDate, $dueDate, $closeDate] = $this->assignmentDatesBridge
            ->normaliseDates($publishDate, $openDate, $dueDate, $closeDate, $this->log);

        $owner       = $classTeacher ?? $this->getAssessableOwner($assessableSpec);
        $sisSourceId = $this->courseSectionBridge->getSisSourceIdForFolderId($unitImpl->getFolder()->getId());

        $canvasEntity    = ucfirst($this->getCanvasEntity());
        $schoolboxEntity = ucfirst($this->getSchoolboxEntity());

        if (!$assessableImpl) {
            $this->log->debug("Creating new {$schoolboxEntity}Impl for Canvas CourseSection (sisSourceId: '$sisSourceId') and {$canvasEntity} (canvasId: $assessmentCanvasId)");
            return $this->createAssessableImpl($assessableSpec, $unitImpl, $owner, $publishDate, $openDate, $dueDate, $closeDate);
        }
        $this->log->debug(
            "Synchronising Canvas {$canvasEntity}(canvasId: $assessmentCanvasId) and CourseSection (sisSourceId: '$sisSourceId') "
            . "with {$schoolboxEntity}Impl (id: {$assessableImpl->getId()})"
        );

        $assessmentSpec = $assessableSpec->getAssessmentModel();
        $assessmentImpl = $assessableImpl->getAssessmentModel();

        $assessmentUpdated = $this->checkAssessmentForUpdates(
            $assessmentCanvasId,
            $assessmentImpl,
            $assessmentSpec,
            $publishDate,
            $openDate,
            $dueDate,
            $closeDate
        );
        $assessableUpdated = $this->checkAssessableForUpdates(
            $assessmentCanvasId,
            $assessableImpl,
            $assessableSpec,
            $owner,
            $unitImpl,
            $openDate,
            $dueDate
        );
        if ($assessmentUpdated || $assessableUpdated) {
            $this->countAssessablesUpdated++;
        }

        return $assessableImpl;
    }


    /**
     * Returns the owner of the $assessable
     *
     * @param IAssessableDataModel $assessable
     * @return User
     */
    abstract protected function getAssessableOwner(IAssessableDataModel $assessable): User;


    /**
     * Checks whether we need to update the Assessable, returns whether any update was made
     *
     * @param int                  $assessmentCanvasId
     * @param IAssessableDataModel $assessableImpl
     * @param IAssessableDataModel $assessableSpec
     * @param User                 $owner
     * @param UnitImpl\Model       $unitImpl
     * @param string               $openDate
     * @param string               $dueDate
     * @return bool
     * @throws Mapper\Exception
     * @throws PDOException
     * @throws UnexpectedValueException
     */
    abstract protected function checkAssessableForUpdates(
        int $assessmentCanvasId,
        IAssessableDataModel $assessableImpl,
        IAssessableDataModel $assessableSpec,
        User $owner,
        UnitImpl\Model $unitImpl,
        string $openDate,
        string $dueDate
    ): bool;


    /**
     * Returns the assessable spec for the specified canvas assessment.canvas_id that matches this classes type
     *
     * @param int $assessmentCanvasId
     * @return IAssessableDataModel
     * @throws Mapper\Exception
     */
    abstract protected function findAssessableSpec(int $assessmentCanvasId): IAssessableDataModel;


    /**
     * Returns a Assessable Impl for the specified AssessmentSpecId + UnitImplId, or NULL if none found
     *
     * Hydrates:
     *  assessable
     *  assessable.assessment
     *  assessable.assessment.participant_plan (so we don't recreate plan due to mapper pre-save callback) TODO fix this properly in develop
     *
     * @param Assessment\Model $assessmentSpec
     * @param UnitImpl\Model   $unitImpl
     * @return null|IAssessableDataModel
     * @throws Database\Exception
     * @throws Mapper\Exception
     * @throws UnexpectedValueException
     */
    abstract protected function findAssessableImpl(Assessment\Model $assessmentSpec, UnitImpl\Model $unitImpl): ?IAssessableDataModel;


    /**
     * Create and return an Assessable Impl
     *
     * @param IAssessableDataModel $assessableSpec
     * @param UnitImpl\Model       $unitImpl
     * @param User                 $owner
     * @param string               $publishDate
     * @param string               $openDate
     * @param string               $dueDate
     * @param string               $closeDate
     * @return IAssessableDataModel
     * @throws Database\Exception
     * @throws Mapper\Exception
     * @throws PDOException
     * @throws UnexpectedValueException
     */
    abstract protected function createAssessableImpl(
        IAssessableDataModel $assessableSpec,
        UnitImpl\Model $unitImpl,
        User $owner,
        string $publishDate,
        string $openDate,
        string $dueDate,
        string $closeDate
    ): IAssessableDataModel;


    /**
     * Load and return Assessable data from canvas DB in a nested array
     *
     * In the return data structure annotations below, '$assessmentCanvasId'
     * will be either an assignment.canvas_id or quiz.canvas_id
     *
     * @param string[] $sectionSisSourceIds
     * @return array
     * @throws Database\Exception
     * @throws InvalidColumnException
     * @throws UnderflowException
     * @internal null|string $return[$courseCanvasId][$assessmentCanvasId]['unlock_at']
     * @internal null|string $return[$courseCanvasId][$assessmentCanvasId]['title']
     * @internal null|string $return[$courseCanvasId][$assessmentCanvasId]['lock_at']
     * @internal null|string $return[$courseCanvasId][$assessmentCanvasId]['due_at']
     * @internal bool        $return[$courseCanvasId][$assessmentCanvasId]['all_day']
     * @internal null|string $return[$courseCanvasId][$assessmentCanvasId]['all_day_date']
     * @internal null|string $return[$courseCanvasId][$assessmentCanvasId]['visibility']
     * @internal array       $return[$courseCanvasId][$assessmentCanvasId]['overrides']
     * @internal null|string $return[$courseCanvasId][$assessmentCanvasId]['overrides'][$overrideCanvasId]['title']
     * @internal null|string $return[$courseCanvasId][$assessmentCanvasId]['overrides'][$overrideCanvasId]['unlock_at']
     * @internal null|string $return[$courseCanvasId][$assessmentCanvasId]['overrides'][$overrideCanvasId]['lock_at']
     * @internal null|string $return[$courseCanvasId][$assessmentCanvasId]['overrides'][$overrideCanvasId]['due_at']
     * @internal bool        $return[$courseCanvasId][$assessmentCanvasId]['overrides'][$overrideCanvasId]['all_day']
     * @internal null|string $return[$courseCanvasId][$assessmentCanvasId]['overrides'][$overrideCanvasId]['all_day_date']
     * @internal string      $return[$courseCanvasId][$assessmentCanvasId]['overrides'][$overrideCanvasId]['sections'][$overrideSectionCanvasId]['sis_source_id']
     * @internal string      $return[$courseCanvasId][$assessmentCanvasId]['overrides'][$overrideCanvasId]['groups'][$overrideGroupCanvasId]['name']
     * @internal string      $return[$courseCanvasId][$assessmentCanvasId]['overrides'][$overrideCanvasId]['groups'][$overrideGroupCanvasId]['members'][$overrideGroupMemberCanvasId]['sis_user_id']
     * @internal string      $return[$courseCanvasId][$assessmentCanvasId]['overrides'][$overrideCanvasId]['ad_hoc_users'][$overrideAdHocUserCanvasId]['sis_user_id']
     */
    abstract protected function loadCanvasAssessmentAndOverrideData(array $sectionSisSourceIds): array;


    /**
     * Process the assessment-specific query to build a consistent override data structure
     *
     * @param Database\Statement\Query\Base $statement
     * @return array
     * @internal null|string $return[$courseCanvasId][$assessmentCanvasId]['unlock_at']
     * @internal null|string $return[$courseCanvasId][$assessmentCanvasId]['title']
     * @internal null|string $return[$courseCanvasId][$assessmentCanvasId]['lock_at']
     * @internal null|string $return[$courseCanvasId][$assessmentCanvasId]['due_at']
     * @internal bool        $return[$courseCanvasId][$assessmentCanvasId]['all_day']
     * @internal null|string $return[$courseCanvasId][$assessmentCanvasId]['all_day_date']
     * @internal null|string $return[$courseCanvasId][$assessmentCanvasId]['visibility']
     * @internal array       $return[$courseCanvasId][$assessmentCanvasId]['overrides']
     * @internal null|string $return[$courseCanvasId][$assessmentCanvasId]['overrides'][$overrideCanvasId]['title']
     * @internal null|string $return[$courseCanvasId][$assessmentCanvasId]['overrides'][$overrideCanvasId]['unlock_at']
     * @internal null|string $return[$courseCanvasId][$assessmentCanvasId]['overrides'][$overrideCanvasId]['lock_at']
     * @internal null|string $return[$courseCanvasId][$assessmentCanvasId]['overrides'][$overrideCanvasId]['due_at']
     * @internal bool        $return[$courseCanvasId][$assessmentCanvasId]['overrides'][$overrideCanvasId]['all_day']
     * @internal null|string $return[$courseCanvasId][$assessmentCanvasId]['overrides'][$overrideCanvasId]['all_day_date']
     * @internal string      $return[$courseCanvasId][$assessmentCanvasId]['overrides'][$overrideCanvasId]['sections'][$overrideSectionCanvasId]['sis_source_id']
     * @internal string      $return[$courseCanvasId][$assessmentCanvasId]['overrides'][$overrideCanvasId]['groups'][$overrideGroupCanvasId]['name']
     * @internal string      $return[$courseCanvasId][$assessmentCanvasId]['overrides'][$overrideCanvasId]['groups'][$overrideGroupCanvasId]['members'][$overrideGroupMemberCanvasId]['sis_user_id']
     * @internal string      $return[$courseCanvasId][$assessmentCanvasId]['overrides'][$overrideCanvasId]['ad_hoc_users'][$overrideAdHocUserCanvasId]['sis_user_id']
     */
    protected function buildCanvasOverrideData(Database\Statement\Query\Base $statement): array
    {
        $data = [];
        while ($row = $statement->fetch()) {
            $courseCanvasId              = $row['course.canvas_id'];
            $assessmentCanvasId          = $row['assessable.canvas_id'];
            $overrideCanvasId            = $row['override.canvas_id'];
            $overrideSectionCanvasId     = $row['override_section.canvas_id'];
            $overrideGroupCanvasId       = $row['override_group.canvas_id'];
            $overrideGroupMemberCanvasId = $row['group_pseudonym.canvas_id'];
            $overrideAdHocUserCanvasId   = $row['user_pseudonym.canvas_id'];

            if (!array_key_exists($courseCanvasId, $data)) {
                $data[$courseCanvasId] = [];
            }

            // First time we've seen this assignment/quiz?
            if ($assessmentCanvasId !== null && !array_key_exists($assessmentCanvasId, $data[$courseCanvasId])) {
                $data[$courseCanvasId][$assessmentCanvasId] = [
                    'unlock_at'    => $row['assessable.unlock_at'],
                    'title'        => $row['assessable.title'],
                    'lock_at'      => $row['assessable.lock_at'],
                    'due_at'       => $row['assessable.due_at'],
                    'all_day'      => $row['assessable.all_day'] === 'true',
                    'all_day_date' => $row['assessable.all_day_date'],
                    'visibility'   => $row['assessable.visibility'],
                    'overrides'    => [],
                ];
            }

            // First time we've see this override?
            if (
                $overrideCanvasId !== null &&
                !array_key_exists($overrideCanvasId, $data[$courseCanvasId][$assessmentCanvasId]['overrides'])
            ) {
                $data[$courseCanvasId][$assessmentCanvasId]['overrides'][$overrideCanvasId] = [
                    'title'        => $row['override.title'],
                    'unlock_at'    => $row['override.unlock_at'],
                    'lock_at'      => $row['override.lock_at'],
                    'due_at'       => $row['override.due_at'],
                    'all_day'      => $row['override.all_day'] === 'true',
                    'all_day_date' => $row['override.all_day_date'],
                    'sections'     => [],
                    'groups'       => [],
                    'ad_hoc_users' => [],
                ];
            }

            // Is this a section override row?
            if (
                $overrideSectionCanvasId !== null &&
                !array_key_exists($overrideSectionCanvasId, $data[$courseCanvasId][$assessmentCanvasId]['overrides'][$overrideCanvasId]['sections'])
            ) {
                $data[$courseCanvasId][$assessmentCanvasId]['overrides'][$overrideCanvasId]['sections'][$overrideSectionCanvasId] = [
                    'sis_source_id' => $row['override_section.sis_source_id'],
                ];
            }

            // Is this a group override row?
            if (($overrideGroupCanvasId !== null) && !array_key_exists($overrideGroupCanvasId, $data[$courseCanvasId][$assessmentCanvasId]['overrides'][$overrideCanvasId]['groups'])) {
                $data[$courseCanvasId][$assessmentCanvasId]['overrides'][$overrideCanvasId]['groups'][$overrideGroupCanvasId] = [
                    'name'    => $row['override_group.name'],
                    'members' => [],
                ];
            }

            // Is this a override group member?
            if (
                $overrideGroupMemberCanvasId !== null &&
                !array_key_exists($overrideGroupMemberCanvasId, $data[$courseCanvasId][$assessmentCanvasId]['overrides'][$overrideCanvasId]['groups'][$overrideGroupCanvasId]['members'])
            ) {
                $data[$courseCanvasId][$assessmentCanvasId]['overrides'][$overrideCanvasId]['groups'][$overrideGroupCanvasId]['members'][$overrideGroupMemberCanvasId] = [
                    'sis_user_id' => $row['group_pseudonym.sis_user_id'],
                ];
            }

            // Is this an ad_hoc (individual users) override?
            if (
                $overrideAdHocUserCanvasId !== null &&
                !array_key_exists($overrideAdHocUserCanvasId, $data[$courseCanvasId][$assessmentCanvasId]['overrides'][$overrideCanvasId]['ad_hoc_users'])
            ) {
                $data[$courseCanvasId][$assessmentCanvasId]['overrides'][$overrideCanvasId]['ad_hoc_users'][$overrideAdHocUserCanvasId] = [
                    'sis_user_id' => $row['user_pseudonym.sis_user_id'],
                ];
            }
        }
        return $data;
    }


    /**
     * @param Assessment\Model $assessmentSpec
     * @param UnitImpl\Model   $unitImpl
     * @param string           $publishDate
     * @param string           $openDate
     * @param string           $dueDate
     * @param string           $closeDate
     * @return Assessment\Model
     * @throws Database\Exception
     */
    protected function createAssessmentImplFromSpec(
        Assessment\Model $assessmentSpec,
        UnitImpl\Model $unitImpl,
        string $publishDate,
        string $openDate,
        string $dueDate,
        string $closeDate
    ): Assessment\Model {
        $assessmentImpl = clone $assessmentSpec;
        $assessmentImpl
            ->setAssessmentContext($unitImpl)
            ->setSpecificationAssessment($assessmentSpec)
            ->setPublishDate(Alaress_Date_Format::newDatabaseDate($publishDate))
            ->setOpenDate(Alaress_Date_Format::newDatabaseDate($openDate))
            ->setDueDate(Alaress_Date_Format::newDatabaseDate($dueDate))
            ->setPlans(Participant\Plan\Model::createCollection())
            ->setCloseDate(Alaress_Date_Format::newDatabaseDate($closeDate))
            ->setCreatedAt(Alaress_Date_Format::now())
            ->setUpdatedAt(Alaress_Date_Format::now());

        // Insert AssessmentImpl
        $this->saveModel($this->assessmentMapper, $assessmentImpl, false, false);

        // Insert Assessment.context
        $sql = "INSERT INTO assessment_course_unit_implementation (assessment_id, course_unit_implementation_id) VALUES (:assessment_id, :unit_impl_id)";
        $this->schoolboxDb->executeQuery($sql, [
            'assessment_id' => $assessmentImpl->getId(),
            'unit_impl_id'  => $unitImpl->getId(),
        ]);

        // Insert Assessment.specification
        $sql = "INSERT INTO assessment_specification_implementation (specification_assessment_id, implementation_assessment_id) VALUES (:spec_id, :impl_id)";
        $this->schoolboxDb->executeQuery($sql, [
            'spec_id' => $assessmentImpl->getSpecificationAssessment()->getId(),
            'impl_id' => $assessmentImpl->getId(),
        ]);

        $this->countAssessablesCreated++;
        return $assessmentImpl;
    }


    /**
     * Checks whether we need to update the assessment impl model and returns whether changes were made
     *
     * @param int              $assessmentCanvasId
     * @param Assessment\Model $assessmentImpl
     * @param Assessment\Model $assessmentSpec
     * @param string           $publishDate
     * @param string           $openDate
     * @param string           $dueDate
     * @param string           $closeDate
     * @return bool
     * @throws Database\Exception
     */
    protected function checkAssessmentForUpdates(
        int $assessmentCanvasId,
        Assessment\Model $assessmentImpl,
        Assessment\Model $assessmentSpec,
        string $publishDate,
        string $openDate,
        string $dueDate,
        string $closeDate
    ): bool {
        if (
            $assessmentImpl->getTitle() === $assessmentSpec->getTitle() &&
            $assessmentImpl->getDescription() === $assessmentSpec->getDescription() &&
            $assessmentImpl->getSequence() === $assessmentSpec->getSequence() &&
            $assessmentImpl->getPublishDate()->getDatabase() === $publishDate &&
            $assessmentImpl->getOpenDate()->getDatabase() === $openDate &&
            $assessmentImpl->getDueDate()->getDatabase() === $dueDate &&
            $assessmentImpl->getCloseDate()->getDatabase() === $closeDate
        ) {
            return false;
        }
        $entityName = ucfirst($this->getCanvasEntity());
        $this->log->debug("Updating AssessmentImpl for Canvas {$entityName} (canvasId: $assessmentCanvasId)");
        $assessmentImpl
            ->setTitle($assessmentSpec->getTitle())
            ->setDescription($assessmentSpec->getDescription())
            ->setPublishDate(Alaress_Date_Format::newDatabaseDate($publishDate))
            ->setOpenDate(Alaress_Date_Format::newDatabaseDate($openDate))
            ->setDueDate(Alaress_Date_Format::newDatabaseDate($dueDate))
            ->setCloseDate(Alaress_Date_Format::newDatabaseDate($closeDate))
            ->setSequence($assessmentSpec->getSequence());
        $this->saveModel($this->assessmentMapper, $assessmentImpl, false, false);
        return true;
    }


    /**
     * Logs the results of this stage
     */
    protected function printCounts(): void
    {
        $canvasEntity    = ucfirst($this->getCanvasEntity());
        $schoolboxEntity = ucfirst($this->getSchoolboxEntity());

        $this->log->notice($this->countCanvasSectionAssessmentsSynchronised . " Canvas CourseSections-{$canvasEntity} synchronised");
        $this->log->notice($this->countCanvasAssessmentOverridesSynchronised . " Canvas {$canvasEntity} Overrides synchronised");

        $this->log->notice($this->countAssessablesCreated . " Schoolbox {$schoolboxEntity} Impl created");
        $this->log->notice($this->countAssessablesMoved . " Schoolbox {$schoolboxEntity} Impl moved");
        $this->log->notice($this->countAssessablesUpdated . " Schoolbox {$schoolboxEntity} Impl updated");
        $this->log->notice($this->countAssessablesDeleted . " Schoolbox {$schoolboxEntity} Impl deleted");

        $this->log->notice($this->countPlansCreated . " Schoolbox {$schoolboxEntity} Impl Plans created");
        $this->log->notice($this->countPlansUpdated . " Schoolbox {$schoolboxEntity} Impl Plans updated");
        $this->log->notice($this->countPlansDeleted . " Schoolbox {$schoolboxEntity} Impl Plans deleted");

        $this->log->notice($this->countParticipantsCreated . " Schoolbox {$schoolboxEntity} Impl Participants created");
        $this->log->notice($this->countParticipantsUpdated . " Schoolbox {$schoolboxEntity} Impl Participants updated");
        $this->log->notice($this->countParticipantsDeleted . " Schoolbox {$schoolboxEntity} Impl Participants deleted");
    }


    /**
     * Load and return Canvas Section student data from canvas DB in a nested array
     *
     * @param string[] $sectionSisSourceIds
     * @return array
     * @throws Database\Exception
     * @throws InvalidColumnException
     * @throws UnderflowException
     * @internal string      $return[$courseCanvasId]['name']
     * @internal string      $return[$courseCanvasId]['sections'][$sectionCanvasId]['sis_source_id']
     * @internal string      $return[$courseCanvasId]['sections'][$sectionCanvasId]['students'][$sectionStudentCanvasId]['sis_user_id']
     */
    protected function loadCanvasSectionStudentData(array $sectionSisSourceIds): array
    {
        if (!count($sectionSisSourceIds)) {
            return [];
        }

        $mySqlQuoter = new Database\Quoter\Mysql\Quoter();
        $pb          = (new Builder($mySqlQuoter, ''))->in('section.sis_source_id', $sectionSisSourceIds);
        $whereClause = implode(' AND ', $pb->getWhere());
        $sql         = "
            SELECT
                course.canvas_id            'course.canvas_id',
                course.name                 'course.name',
                section.canvas_id           'section.canvas_id',
                section.sis_source_id       'section.sis_source_id',
                section_student.canvas_id   'section_student.canvas_id',
                section_student.sis_user_id 'section_student.sis_user_id'
            FROM course_dim course
            JOIN course_section_dim section ON course.id = section.course_id
            LEFT JOIN enrollment_dim enrollment ON enrollment.course_section_id = section.id AND enrollment.type = 'StudentEnrollment' AND enrollment.workflow_state = 'active'
            LEFT JOIN pseudonym_dim section_student ON section_student.user_id = enrollment.user_id AND section_student.sis_user_id IS NOT NULL
            WHERE {$whereClause}
            ORDER BY course.code, course.canvas_id, section.canvas_id
        ";

        $data      = [];
        $statement = $this->canvasDb->executeQuery($sql, $pb->getParams());
        while ($row = $statement->fetch()) {
            $courseCanvasId         = $row['course.canvas_id'];
            $sectionCanvasId        = $row['section.canvas_id'];
            $sectionStudentCanvasId = $row['section_student.canvas_id'];

            // First time we've seen course?
            if (!array_key_exists($courseCanvasId, $data)) {
                $data[$courseCanvasId] = [
                    'name'     => $row['course.name'],
                    'sections' => [],
                ];
            }
            // First time we've seen section?
            if (!array_key_exists($sectionCanvasId, $data[$courseCanvasId]['sections'])) {
                $data[$courseCanvasId]['sections'][$sectionCanvasId] = [
                    'sis_source_id' => $row['section.sis_source_id'],
                    'students'      => [],
                ];
            }
            // First time we've seen student?
            if (
                $sectionStudentCanvasId !== null &&
                !array_key_exists($sectionStudentCanvasId, $data[$courseCanvasId]['sections'][$sectionCanvasId]['students'])
            ) {
                $data[$courseCanvasId]['sections'][$sectionCanvasId]['students'][$sectionStudentCanvasId] = [
                    'sis_user_id' => $row['section_student.sis_user_id'],
                ];
            }
        }
        return $data;
    }


    /**
     * Uses the assignment override + section data to build override data per section.
     *
     * @param array $assignment
     * @param array $sections
     * @param array $studentExternalIdUserIdMap
     * @return array
     */
    private function buildOverridesPerSection(array $assignment, array $sections, array $studentExternalIdUserIdMap): array
    {
        $overridesPerSection = [];
        foreach ($assignment['overrides'] as $overrideCanvasId => $override) {
            $this->log->info("Processing Canvas Assignment Override (CanvasId: $overrideCanvasId)");
            $this->countCanvasAssessmentOverridesSynchronised++;

            if (count($override['sections'])) {
                // Trivial 'section' case
                $overridesPerSection[reset($override['sections'])['sis_source_id']][$overrideCanvasId] = $override;
            } else {
                // Complex 'AdHoc/Group' case
                $isGroupOverride = (bool)count($override['groups']);
                if ($isGroupOverride) {
                    $overrideStudents = reset($override['groups'])['members'];
                } else {
                    $overrideStudents = $override['ad_hoc_users'];
                }
                $overrideStudents = array_filter(array_column($overrideStudents, 'sis_user_id'));
                foreach ($sections as $section) {
                    $sectionSisSourceId = $section['sis_source_id'];
                    $sectionStudents    = array_column($section['students'], 'sis_user_id');
                    $intersect          = array_intersect($overrideStudents, $sectionStudents);
                    if (count($intersect)) {
                        $overridesPerSection[$sectionSisSourceId][$overrideCanvasId] = $override;
                        if ($isGroupOverride) {
                            $overrideGroupCanvasIds = array_keys($overridesPerSection[$sectionSisSourceId][$overrideCanvasId]['groups']);
                            $overrideGroupCanvasId  = reset($overrideGroupCanvasIds);
                            $this->log->debug("Group override intersects section '$sectionSisSourceId'");
                            $overridesPerSection[$sectionSisSourceId][$overrideCanvasId]['groups'][$overrideGroupCanvasId]['members'] = $intersect;
                        } else {
                            $this->log->debug("AdHoc override intersects section '$sectionSisSourceId'");
                            $overridesPerSection[$sectionSisSourceId][$overrideCanvasId]['ad_hoc_users'] = $intersect;
                        }
                    }
                }
            }
        }

        // For any section that now has both section and group/adHoc overrides, we can discard the group/AdHoc override as
        // these are of lower precedence than section override
        foreach ($overridesPerSection as $sectionSisSourceId => $splitOverrides) {
            $sectionOverride = null;
            foreach ($splitOverrides as $overrideCanvasId => $splitOverride) {
                if (count($splitOverride['sections'])) {
                    $sectionOverride = $splitOverride;
                    break;
                }
            }
            if ($sectionOverride !== null) {
                foreach ($splitOverrides as $overrideCanvasId => $splitOverride) {
                    if (count($splitOverride['sections'])) {
                        // Ignore the 'section' override
                        continue;
                    }
                    $overrideType = count($splitOverride['groups']) ? 'Group' : 'Ad Hoc';
                    $this->log->debug(
                        "$overrideType override '{$splitOverride['title']}' is clobbered by section " .
                        "override for section '$sectionSisSourceId', discarding..."
                    );
                    unset($overridesPerSection[$sectionSisSourceId][$overrideCanvasId]);
                }
            }
        }

        // For any section that has both Group+AdHoc overrides, discard any AdHoc users that are present in a group override
        // as AdHoc users are of lower precedence that Group members
        foreach ($overridesPerSection as $sectionSisSourceId => $splitOverrides) {
            $groupMembers = [];
            foreach ($splitOverrides as $overrideCanvasId => $splitOverride) {
                if (count($splitOverride['groups'])) {
                    array_push($groupMembers, ...reset($splitOverride['groups'])['members']);
                }
            }
            $groupMembers = array_unique($groupMembers);
            if (count($groupMembers)) {
                foreach ($splitOverrides as $overrideCanvasId => $splitOverride) {
                    if (!count($splitOverride['ad_hoc_users'])) {
                        // Ignore the 'section' + 'group' overrides
                        continue;
                    }
                    $intersect = array_intersect($splitOverride['ad_hoc_users'], $groupMembers);
                    if (!count($intersect)) {
                        continue;
                    }
                    $nonClobberedAdHocUsers = array_diff($splitOverride['ad_hoc_users'], $groupMembers);
                    if (count($nonClobberedAdHocUsers)) {
                        $this->log->debug("Ad Hoc override '{$splitOverride['title']}' is partially clobbered by group override[s] for section '$sectionSisSourceId', removing clobbered ah hoc users...");
                        $overridesPerSection[$sectionSisSourceId][$overrideCanvasId]['ad_hoc_users'] = $nonClobberedAdHocUsers;
                    } else {
                        $this->log->debug("Ad Hoc override '{$splitOverride['title']}' is clobbered by group override[s] for section '$sectionSisSourceId', discarding override...");
                        unset($overridesPerSection[$sectionSisSourceId][$overrideCanvasId]);
                    }
                }
            }
        }

        // For each section overrides, if the override would require a static plan, but no members exist in Schoolbox,
        // discard the section override as there is no point creating it.
        foreach ($overridesPerSection as $sectionSisSourceId => $splitOverrides) {
            foreach ($splitOverrides as $overrideCanvasId => $splitOverride) {
                if (count($splitOverride['sections'])) {
                    // Ignore section overrides, they get dynamic plans
                    continue;
                }
                $students = count($splitOverride['groups'])
                    ? reset($splitOverride['groups'])['members']
                    : $splitOverride['ad_hoc_users'];
                // Are at least one student found in Schoolbox?
                $found = false;
                foreach ($students as $externalId) {
                    if (array_key_exists($externalId, $studentExternalIdUserIdMap)) {
                        $found = true;
                        break;
                    }
                }
                if (!$found) {
                    $this->log->warning(
                        "No Override members exist in Schoolbox, discarding override " .
                        "(canvasId: $overrideCanvasId) for section (sisSourceId: '$sectionSisSourceId')"
                    );
                    unset($overridesPerSection[$sectionSisSourceId][$overrideCanvasId]);
                }
            }
        }
        return $overridesPerSection;
    }


    /**
     * Synchronises a Canvas Override with a Schoolbox Participation Plan
     *
     * @param Assessment\Model $assessmentModel
     * @param int|null         $canvasId
     * @param string|null      $title
     * @param string|null      $unlockAt
     * @param string|null      $lockAt
     * @param string|null      $dueAt
     * @param bool|null        $allDay
     * @param string|null      $allDayDate
     * @param string[]|null    $participants Student's externalIds
     * @param int[]            $studentExternalIdUserIdMap
     * @return Participant\Plan\Model
     * @throws Database\Exception
     * @throws InvalidColumnException
     * @throws Mapper\Exception
     * @throws UnexpectedValueException
     * @throws PDOException
     */
    private function syncOverrideWithPlan(
        Assessment\Model $assessmentModel,
        ?int $canvasId,
        ?string $title,
        ?string $unlockAt,
        ?string $lockAt,
        ?string $dueAt,
        ?bool $allDay,
        ?string $allDayDate,
        ?array $participants,
        array $studentExternalIdUserIdMap
    ): Participant\Plan\Model {
        // Create a dynamic plan if assignment is for 'everyone'.
        // This will always have NULL fields to allow the assignment's details to flow through.
        $isEveryonePlan = $canvasId === null;
        $canvasId       = $isEveryonePlan
            ? $this->assignmentOverrideBridge->getEveryoneDynamicPlanCanvasId()
            : $canvasId;
        $title          = $isEveryonePlan ? $this->assignmentOverrideBridge->getEveryoneOverrideTitle() : $title;
        $planName       = $this->assignmentOverrideBridge->generateLinkedPlanName($title, $canvasId);
        $isDynamic      = !is_array($participants);
        $planType       = $isDynamic ? 'dynamic' : 'static';

        if ($isEveryonePlan || $isDynamic || $allDay === null || ($unlockAt === null && $lockAt === null && $dueAt === null && $allDayDate === null)) {
            // If dynamic, or no dates are specified, set all plans dates to null to enable assessment dates to apply
            [$publish, $open, $due, $close] = [null, null, null, null];
        } else {
            // Else, set all dates, bounding by assessment if not supplied for a subset of dates
            [$publish, $open, $due, $close] = $this->assignmentDatesBridge->assessmentDates(
                $unlockAt,
                $dueAt,
                $allDay,
                $allDayDate,
                $lockAt,
                $assessmentModel->getPublishDate()->getDatabase(),
                $assessmentModel->getDueDate()->getDatabase()
            );
            [$publish, $open, $due, $close,] = $this->assignmentDatesBridge
                ->normaliseDates($publish, $open, $due, $close, $this->log);
        }

        $plan = $this->findPlan($assessmentModel, $canvasId);
        if (!$plan) {
            $this->log->debug("Creating new $planType plan '$planName'");
            $this->countPlansCreated++;
            $plan = $this->createPlan($assessmentModel, $isDynamic, $planName, $publish, $open, $due, $close);
        } else {
            $currentPublish = ($plan->getPublishDate() === null || $plan->getPublishDate()->isNull()) ? null : $plan->getPublishDate()->getDatabase();
            $currentOpen    = ($plan->getOpenDate() === null || $plan->getOpenDate()->isNull()) ? null : $plan->getOpenDate()->getDatabase();
            $currentDue     = ($plan->getDueDate() === null || $plan->getDueDate()->isNull()) ? null : $plan->getDueDate()->getDatabase();
            $currentClose   = ($plan->getCloseDate() === null || $plan->getCloseDate()->isNull()) ? null : $plan->getCloseDate()->getDatabase();

            if (
                $currentPublish !== $publish ||
                $currentOpen !== $open ||
                $currentDue !== $due ||
                $currentClose !== $close ||
                $plan->getName() !== $planName ||
                $plan->getDescription() !== null
            ) {
                $plan
                    ->setName($planName)
                    ->setDescription(null)
                    ->setPublishDate($publish === null ? null : Alaress_Date_Format::newDatabaseDate($publish))
                    ->setOpenDate($open === null ? null : Alaress_Date_Format::newDatabaseDate($open))
                    ->setDueDate($due === null ? null : Alaress_Date_Format::newDatabaseDate($due))
                    ->setCloseDate($close === null ? null : Alaress_Date_Format::newDatabaseDate($close));
                $this->log->debug("Updating $planType plan '$planName'");
                $this->countPlansUpdated++;
                $this->participantPlanMapper->save($plan);
            }
        }

        foreach ($participants ?? [] as $externalId) {
            $userId = $studentExternalIdUserIdMap[$externalId];
            /** @var Participant\Model|null $participant */
            $participant = $plan->getParticipants()->getOffset($userId);
            if (!$participant) {
                $this->log->debug("Creating new participant (externalId: '$externalId', userId: $userId)");
                $this->countParticipantsCreated++;
                $participant = (new Participant\Model())
                    ->setUser((new User())->setId($userId))
                    ->setPlan($plan);
                $this->participantMapper->save($participant);
                $plan->getParticipants()->setOffset($userId, $participant);
            } elseif ($participant->isExcluded()) {
                $this->log->debug("Un-excluding participant (externalId: '$externalId', userId: $userId)");
                $this->countParticipantsUpdated++;
                $participant->setExcludedAt(null);
                $this->participantMapper->save($participant);
            }
        }

        return $plan;
    }


    /**
     * Loads and returns the a Plan for the specified override, returns NULL if none found.
     *
     * Loads any Participant records
     *
     * @param Assessment\Model $assessmentImpl
     * @param int              $overrideCanvasId
     * @return Participant\Plan\Model|null
     * @throws Database\Exception
     * @throws InvalidColumnException
     * @throws Mapper\Exception
     * @throws UnexpectedValueException
     */
    private function findPlan(Assessment\Model $assessmentImpl, int $overrideCanvasId): ?Participant\Plan\Model
    {
        $pb          = $this->assignmentOverrideBridge->equalsCanvasOverride(
            $overrideCanvasId,
            $assessmentImpl->getId(),
            'pp'
        );
        $whereClause = $pb->getWhere()[0];
        $sql         = "
            SELECT
                   {$this->participantPlanMapper->getColumnString('pp')},
                   {$this->participantMapper->getColumnString('p')}
            FROM participant_plan pp
            LEFT JOIN participant p on pp.id = p.participant_plan_id
            WHERE {$whereClause} AND pp.deleted_at IS NULL
        ";
        $rows        = $this->schoolboxDb->executeQuery($sql, $pb->getParams())->fetchAll();
        if (!count($rows)) {
            return null;
        }
        /** @var Participant\Plan\Model[] $plans */
        $plans = [];
        foreach ($rows as $row) {
            $planId        = $row['pp.id'];
            $participantId = $row['p.id'];
            if (!array_key_exists($planId, $plans)) {
                $plans[$planId] = $this->participantPlanMapper
                    ->hydrate($rows[0], 'pp')
                    ->setParticipants($this->participantMapper->createCollection());
            }
            $plan = $plans[$planId];
            if ($participantId) {
                $participant = $this->participantMapper->hydrate($row, 'p');
                $plan->getParticipants()->setOffset($participant->getUser()->getId(), $participant);
            }
        }

        if (count($plans) > 1) {
            $planIds = implode(',', array_keys($plans));
            throw new UnexpectedValueException(
                "Multiple plans found for assessment (id: {$assessmentImpl->getId()}) and " .
                "override (canvasId: $overrideCanvasId): [$planIds]"
            );
        }
        return reset($plans);
    }


    /**
     * Create and return a participation plan
     *
     * @param Assessment\Model $assessmentImpl
     * @param bool             $isDynamic
     * @param string           $planName
     * @param string|null      $publish
     * @param string|null      $open
     * @param string|null      $due
     * @param string|null      $close
     * @return Participant\Plan\Model
     * @throws Mapper\Exception
     * @throws PDOException
     * @throws UnexpectedValueException
     */
    private function createPlan(
        Assessment\Model $assessmentImpl,
        bool $isDynamic,
        string $planName,
        ?string $publish,
        ?string $open,
        ?string $due,
        ?string $close
    ): Participant\Plan\Model {
        $plan = (new Participant\Plan\Model())
            ->setAssessment($assessmentImpl)
            ->setName($planName)
            ->setIsDynamic($isDynamic)
            ->setParticipants(new GenericCollection(Participant\Model::class))
            ->setPublishDate($publish === null ? null : Alaress_Date_Format::newDatabaseDate($publish))
            ->setOpenDate($open === null ? null : Alaress_Date_Format::newDatabaseDate($open))
            ->setDueDate($due === null ? null : Alaress_Date_Format::newDatabaseDate($due))
            ->setCloseDate($close === null ? null : Alaress_Date_Format::newDatabaseDate($close));
        $this->participantPlanMapper->save($plan);
        $assessmentImpl->getPlans()->setOffset($plan->getId(), $plan);
        return $plan;
    }


    /**
     * Return a map of student's externalIds to user ids
     *
     * @return int[]
     * @throws Database\Exception
     */
    private function buildStudentExternalIdUserIdMap(): array
    {
        $sql = "
            SELECT u.synergy_id, u.id
            FROM user u
            JOIN `groups` g ON g.id = u.group_id
            WHERE
              u.synergy_id IS NOT NULL AND
              g.group_type = :group_type AND
              u.deleted_at IS NULL
        ";

        $studentExternalIdToUserIdMap = [];
        $statement                    = $this->schoolboxDb->executeQuery($sql, ['group_type' => Role::STUDENT]);
        while ($row = $statement->fetch()) {
            $studentExternalIdToUserIdMap[$row['synergy_id']] = (int)$row['id'];
        }
        return $studentExternalIdToUserIdMap;
    }


    /**
     * Returns a Schoolbox user who teachers the class (as per SIS).
     *
     * Only works for current file year
     *
     * @param string $sectionSisSourceId
     * @return User|null
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
}

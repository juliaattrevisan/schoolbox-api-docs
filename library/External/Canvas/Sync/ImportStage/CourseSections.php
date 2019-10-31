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
use Schoolbox\GenericCollection;
use Schoolbox\IMapper;
use Schoolbox\Learning\Assessment\DataModel as Assessment;
use Schoolbox\Assessment\Participant;
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
use function array_diff;
use function array_filter;
use function array_intersect;
use function array_key_exists;
use function array_keys;
use function array_map;
use function array_merge;
use function array_push;
use function array_unique;
use function count;
use function implode;
use function is_array;
use function reset;

/**
 * Synchronise Canvas CourseSections+Assignments+Overrides => Schoolbox CourseImpl+UnitImpl+AssessmentImpl+Plans+Participants
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

    /** @var Participant\Plan\Mapper */
    private $participantPlanMapper;

    /** @var Participant\Mapper */
    private $participantMapper;

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

    /** @var Bridge\AssignmentOverride */
    private $assignmentOverrideBridge;

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
    private $countCanvasAssignmentOverridesSynchronised;

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
     * @param CourseImpl\Mapper         $courseImplMapper
     * @param CourseSpec\Mapper         $courseSpecMapper
     * @param UnitImpl\Mapper           $unitImplMapper
     * @param UnitSpec\Mapper           $unitSpecMapper
     * @param SubmissionBoxMapper       $submissionBoxMapper
     * @param Assessment\Mapper         $assessmentMapper
     * @param Participant\Plan\Mapper   $planMapper
     * @param Participant\Mapper        $participantMapper
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
        Participant\Plan\Mapper $planMapper,
        Participant\Mapper $participantMapper,
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
        $this->participantMapper        = $participantMapper;
        $this->folderMapper             = $folderMapper;
        $this->userMapper               = $userMapper;
        $this->log                      = $log;
        $this->assignmentDatesBridge    = $assignmentDatesBridge;
        $this->courseBridge             = $courseBridge;
        $this->courseGroupBridge        = $courseGroupBridge;
        $this->courseSectionBridge      = $courseSectionBridge;
        $this->courseSectionDatesBridge = $courseSectionDatesBridge;
        $this->assignmentOverrideBridge = new Bridge\AssignmentOverride($this->schoolboxDb); // TODO inject
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
        $this->log->notice("Synchronising Canvas CourseSections + Assignments => CourseImpl, UnitImpl, AssessmentImpl...");
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
                    account.canvas_id              'account.canvas_id',
                    course.code                    'course.code',
                    course.canvas_id               'course.canvas_id',
                    course.start_at                'course.start_at',
                    course.conclude_at             'course.conclude_at',
                    courseTerm.date_start          'courseTerm.date_start',
                    courseTerm.date_end            'courseTerm.date_end',
                    section.canvas_id              'section.canvas_id',
                    section.name                   'section.name',
                    section.sis_source_id          'section.sis_source_id',
                    section.start_at               'section.start_at',
                    section.end_at                 'section.end_at',
                    sectionTerm.date_start         'sectionTerm.date_start',
                    sectionTerm.date_end           'sectionTerm.date_end',
                    section_student.canvas_id      'section_student.canvas_id',
                    section_student.sis_user_id    'section_student.sis_user_id',
                    assignment.canvas_id           'assignment.canvas_id',
                    assignment.title               'assignment.title',
                    assignment.visibility          'assignment.visibility',
                    assignment.unlock_at           'assignment.unlock_at',
                    assignment.lock_at             'assignment.lock_at',
                    assignment.due_at              'assignment.due_at',
                    assignment.all_day             'assignment.all_day',
                    assignment.all_day_date        'assignment.all_day_date',
                    override.canvas_id             'override.canvas_id',
                    override.title                 'override.title',
                    override.unlock_at             'override.unlock_at',
                    override.lock_at               'override.lock_at',
                    override.due_at                'override.due_at',
                    override.all_day               'override.all_day',
                    override.all_day_date          'override.all_day_date',
                    override_section.canvas_id     'override_section.canvas_id',
                    override_section.sis_source_id 'override_section.sis_source_id',
                    override_group.canvas_id       'override_group.canvas_id',
                    override_group.name            'override_group.name',
                    group_pseudonym.canvas_id      'group_pseudonym.canvas_id',
                    group_pseudonym.sis_user_id    'group_pseudonym.sis_user_id',
                    user_pseudonym.canvas_id       'user_pseudonym.canvas_id',
                    user_pseudonym.sis_user_id     'user_pseudonym.sis_user_id'
                FROM course_section_dim section
                LEFT JOIN enrollment_dim enrollment ON enrollment.course_section_id = section.id AND enrollment.type = 'StudentEnrollment' AND enrollment.workflow_state = 'active'
                LEFT JOIN pseudonym_dim section_student ON section_student.user_id = enrollment.user_id AND section_student.sis_user_id IS NOT NULL
                JOIN assignment_dim assignment ON assignment.course_id = section.course_id
                JOIN course_dim course ON course.id = section.course_id
                JOIN account_dim account ON account.id = course.account_id
                LEFT JOIN enrollment_term_dim sectionTerm ON section.enrollment_term_id = sectionTerm.id
                LEFT JOIN enrollment_term_dim courseTerm ON course.enrollment_term_id = courseTerm.id
                LEFT JOIN assignment_override_dim override ON override.assignment_id = assignment.id AND override.workflow_state = 'active' AND override.set_type IS NOT NULL -- (master paths have set_type = NULL, not yet supported)
                -- course sections
                LEFT JOIN course_section_dim override_section ON override.course_section_id = override_section.id AND override.set_type = 'course_section'
                -- course groups
                LEFT JOIN group_dim override_group ON override_group.id = override.group_id AND override.set_type = 'group'
                LEFT JOIN group_membership_fact group_membership ON group_membership.group_id = override_group.id 
                LEFT JOIN pseudonym_dim group_pseudonym ON group_pseudonym.user_id = group_membership.user_id
                -- individual assignees
                LEFT JOIN assignment_override_user_dim override_user ON override_user.assignment_override_id = override.id AND override.set_type = 'adhoc'
                LEFT JOIN pseudonym_dim user_pseudonym ON user_pseudonym.user_id = override_user.user_id
                WHERE 
                    assignment.canvas_id IN ({$assignmentCanvasIdBindings->getPlaceholder()}) AND
                    section.sis_source_id IN ({$sectionSisSourceIdBindings->getPlaceholder()})
                ORDER BY account.canvas_id, course.code, course.canvas_id, section.canvas_id, assignment.canvas_id, override.canvas_id
            ";
            $params    = array_merge($assignmentCanvasIdBindings->getParams(), $sectionSisSourceIdBindings->getParams());
            $statement = $this->canvasDb->executeQuery($sql, $params);
            while ($row = $statement->fetch()) {
                $accountCanvasId             = $row['account.canvas_id'];
                $courseGroupCode             = $row['course.code'];
                $courseCanvasId              = $row['course.canvas_id'];
                $sectionCanvasId             = $row['section.canvas_id'];
                $sectionStudentCanvasId      = $row['section_student.canvas_id'];
                $assignmentCanvasId          = $row['assignment.canvas_id'];
                $overrideCanvasId            = $row['override.canvas_id'];
                $overrideSectionCanvasId     = $row['override_section.canvas_id'];
                $overrideGroupCanvasId       = $row['override_group.canvas_id'];
                $overrideGroupMemberCanvasId = $row['group_pseudonym.canvas_id'];
                $overrideAdHocUserCanvasId   = $row['user_pseudonym.canvas_id'];

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
                        'assignments'     => [],
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
                        'students'        => [],
                    ];
                }
                if ($sectionStudentCanvasId !== null && !array_key_exists($sectionStudentCanvasId, $data[$accountCanvasId][$courseGroupCode][$courseCanvasId]['sections'][$sectionCanvasId]['students'])) {
                    $data[$accountCanvasId][$courseGroupCode][$courseCanvasId]['sections'][$sectionCanvasId]['students'][$sectionStudentCanvasId] = [
                        'sis_user_id' => $row['section_student.sis_user_id'],
                    ];
                }

                // First time we've seen this assignment?
                if (!array_key_exists($assignmentCanvasId, $data[$accountCanvasId][$courseGroupCode][$courseCanvasId]['assignments'])) {
                    $data[$accountCanvasId][$courseGroupCode][$courseCanvasId]['assignments'][$assignmentCanvasId] = [
                        'unlock_at'    => $row['assignment.unlock_at'],
                        'title'        => $row['assignment.title'],
                        'lock_at'      => $row['assignment.lock_at'],
                        'due_at'       => $row['assignment.due_at'],
                        'all_day'      => $row['assignment.all_day'] === 'true',
                        'all_day_date' => $row['assignment.all_day_date'],
                        'visibility'   => $row['assignment.visibility'],
                        'overrides'    => [],
                    ];
                }

                // First time we've see this override?
                if (($overrideCanvasId !== null) && !array_key_exists($overrideCanvasId, $data[$accountCanvasId][$courseGroupCode][$courseCanvasId]['assignments'][$assignmentCanvasId]['overrides'])) {
                    $data[$accountCanvasId][$courseGroupCode][$courseCanvasId]['assignments'][$assignmentCanvasId]['overrides'][$overrideCanvasId] = [
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
                if (($overrideSectionCanvasId !== null) && !array_key_exists($overrideSectionCanvasId, $data[$accountCanvasId][$courseGroupCode][$courseCanvasId]['assignments'][$assignmentCanvasId]['overrides'][$overrideCanvasId]['sections'])) {
                    $data[$accountCanvasId][$courseGroupCode][$courseCanvasId]['assignments'][$assignmentCanvasId]['overrides'][$overrideCanvasId]['sections'][$overrideSectionCanvasId] = [
                        'sis_source_id' => $row['override_section.sis_source_id'],
                    ];
                }

                // Is this a group override row?
                if (($overrideGroupCanvasId !== null) && !array_key_exists($overrideGroupCanvasId, $data[$accountCanvasId][$courseGroupCode][$courseCanvasId]['assignments'][$assignmentCanvasId]['overrides'][$overrideCanvasId]['groups'])) {
                    $data[$accountCanvasId][$courseGroupCode][$courseCanvasId]['assignments'][$assignmentCanvasId]['overrides'][$overrideCanvasId]['groups'][$overrideGroupCanvasId] = [
                        'name'    => $row['override_group.name'],
                        'members' => [],
                    ];
                }

                // Is this a override group member?
                if (($overrideGroupMemberCanvasId !== null) && !array_key_exists($overrideGroupMemberCanvasId, $data[$accountCanvasId][$courseGroupCode][$courseCanvasId]['assignments'][$assignmentCanvasId]['overrides'][$overrideCanvasId]['groups'][$overrideGroupCanvasId]['members'])) {
                    $data[$accountCanvasId][$courseGroupCode][$courseCanvasId]['assignments'][$assignmentCanvasId]['overrides'][$overrideCanvasId]['groups'][$overrideGroupCanvasId]['members'][$overrideGroupMemberCanvasId] = [
                        'sis_user_id' => $row['group_pseudonym.sis_user_id'],
                    ];
                }

                // Is this an ad_hoc (individual users) override?
                if (($overrideAdHocUserCanvasId !== null) && !array_key_exists($overrideAdHocUserCanvasId, $data[$accountCanvasId][$courseGroupCode][$courseCanvasId]['assignments'][$assignmentCanvasId]['overrides'][$overrideCanvasId]['ad_hoc_users'])) {
                    $data[$accountCanvasId][$courseGroupCode][$courseCanvasId]['assignments'][$assignmentCanvasId]['overrides'][$overrideCanvasId]['ad_hoc_users'][$overrideAdHocUserCanvasId] = [
                        'sis_user_id' => $row['user_pseudonym.sis_user_id'],
                    ];
                }
            }
        }

        // Track synchronised entities so we can 1) avoid re-sync of sections when iterating an assignment over sections, and 2) delete any entities no longer in Canvas
        /**
         * Indexed by `section`.`sisSourceId`
         *
         * @var CourseImpl\Model[] $courseImplSynchronised
         */
        $courseImplSynchronised = [];
        /**
         * Indexed by `section`.`sisSourceId`
         *
         * @var UnitImpl\Model[] $unitImplSynchronised
         */
        $unitImplSynchronised = [];
        /**
         * Indexed by `submission_box`.`id`
         *
         * @var SubmissionBox[] $submissionBoxImplSynchronised
         */
        $submissionBoxImplSynchronised = [];

        /**
         * Indexed by plan.id
         *
         * @var Participant\Plan\Model[] $plansSynchronised
         */
        $plansSynchronised = [];

        /**
         * Indexed by `section`.`sisSourceId`
         *
         * @var User[] $classTeacherCache
         */
        $classTeacherCache = [];

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
                    foreach ($courseData['assignments'] as $assignmentCanvasId => $assignment) {
                        /* We need to examine the Canvas assignment and assignment-overrides to determine:
                         * - What class folders does this assignment need to be copied to?
                         * - What plans are required for the Schoolbox assessments?
                         *
                         * It is quite possible for a user in canvas to be associated with an assignment via multiple overrides. The precedence is:
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

                        $this->log->info("Processing Canvas Assignment '{$assignment['title']}' (CanvasId: $assignmentCanvasId)");

                        // Split overrides (particularly group+adHoc overrides, sections are trivial) over sections.
                        // Section override     => 1 section
                        // AdHoc/Group override => N sections (depending on which sections Group/AdHoc users are enrolled in)
                        /**
                         * Indexed by `section`.`sis_source_id`
                         *
                         * @var $overridesPerSection
                         */
                        $overridesPerSection = [];
                        foreach ($assignment['overrides'] as $overrideCanvasId => $override) {
                            $this->log->info("Processing Canvas Assignment Override (CanvasId: $overrideCanvasId)");
                            $this->countCanvasAssignmentOverridesSynchronised++;

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
                                foreach ($courseData['sections'] as $section) {
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


                        // Need to check we do not create assessments and static plans for group/adHoc students
                        // if those students are not present in Schoolbox. This implies that we don't create an
                        // assessment in Schoolbox if no students can participate in it.
                        // Build a map of students' externalIds => user.id
                        $studentExternalIdUserIdMap = $this->buildStudentExternalIdUserIdMap();
                        foreach ($overridesPerSection as $sectionSisSourceId => $splitOverrides) {
                            foreach ($splitOverrides as $overrideCanvasId => $splitOverride) {
                                if (count($splitOverride['sections'])) {
                                    // Ignore section overrides, they get static plans
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


                        // We can now sync the assignment + mutated override data with Schoolbox.

                        foreach ($courseData['sections'] as $sectionCanvasId => $section) {
                            $sectionSisSourceId = $section['sis_source_id'];
                            // Do we not have overrides for this section and assignment is not for everyone?
                            if (!array_key_exists($sectionSisSourceId, $overridesPerSection) && $assignment['visibility'] !== 'everyone') {
                                // No need to import this assignment into this class.
                                continue;
                            }
                            // We need to create the assessment, we will need a CourseImpl + UnitImpl
                            // Use a cache, because we may encounter the same class for multiple assignments.
                            $classFolder = $this->courseSectionBridge->getFolderByBySisSourceId($sectionSisSourceId);
                            if ($classFolder === null) {
                                throw new UnexpectedValueException("Unable to find folder for section.sis_source_id '$sectionSisSourceId'");
                            }
                            if (!array_key_exists($sectionSisSourceId, $courseImplSynchronised)) {
                                $courseImplSynchronised[$sectionSisSourceId] = $this->syncCourseSectionToCourseImpl($courseSpecFolder->getCourseSpecification(), $classFolder);
                            }
                            $courseImpl = $courseImplSynchronised[$sectionSisSourceId];
                            if (!array_key_exists($sectionSisSourceId, $unitImplSynchronised)) {
                                $unitImplSynchronised[$sectionSisSourceId] = $this->syncCourseSectionWithUnitImpl(
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
                            }
                            $unitImpl = $unitImplSynchronised[$sectionSisSourceId];

                            if (!array_key_exists($sectionSisSourceId, $classTeacherCache)) {
                                // Try to find a class teacher from SIS, this is likely more accurate that Course teacher user for Spec Assessment
                                $classTeacherCache[$sectionSisSourceId] = $this->getClassTeacher($sectionSisSourceId);
                            }
                            $classTeacher = $classTeacherCache[$sectionSisSourceId];

                            // If assignment has a section override, then use section dates as assignment dates, so section overrides dates can be NULL
                            $sectionOverride = null;
                            foreach($overridesPerSection[$sectionSisSourceId] ?? [] as $overrideCanvasId => $override){
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
                                $unlockAt   = $assignment['unlock_at'];
                                $lockAt     = $assignment['lock_at'];
                                $dueAt      = $assignment['due_at'];
                                $allDay     = $assignment['all_day'];
                                $allDayDate = $assignment['all_day_date'];
                            }

                            $submissionBoxImpl = $this->syncAssignmentWithSubmissionBoxImpl(
                                $assignmentCanvasId,
                                $unitImpl,
                                $classTeacher,
                                $unlockAt,
                                $lockAt,
                                $dueAt,
                                $allDay,
                                $allDayDate
                            );
                            $submissionBoxImplSynchronised[$submissionBoxImpl->getId()] = $submissionBoxImpl;
                            $this->countCanvasAssignmentsSynchronised++;

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
                                $plan                              = $this->syncOverrideWithPlan(
                                    $submissionBoxImpl,
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
                            if (!$hasSectionOverride && $assignment['visibility'] === 'everyone') {
                                $plan                              = $this->syncOverrideWithPlan(
                                    $submissionBoxImpl,
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
            }
        }

        $this->deleteUnSynchronisedSubmissionBoxImpl(array_keys($submissionBoxImplSynchronised));
        $this->deleteUnSynchronisedUnitImpl(array_map(static function (UnitImpl\Model $unitImpl) {
            return $unitImpl->getId();
        }, $unitImplSynchronised));
        $this->deleteUnSynchronisedCourseImpl(array_map(static function (CourseImpl\Model $courseImpl) {
            return $courseImpl->getId();
        }, $courseImplSynchronised));
        $this->deleteUnSynchronisedPlans(array_keys($plansSynchronised));

        $this->printCounts();
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
            $sql = "UPDATE course_implementation SET course_specification_id = :spec_id WHERE id = :impl_id";
            $this->schoolboxDb->executeQuery($sql, ['spec_id' => $courseSpec->getId(), 'impl_id' => $courseImpl->getId()]);
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
            $sql = "UPDATE course_unit_implementation SET course_unit_specification_id = :unit_spec_id WHERE id = :unit_impl_id";
            $this->schoolboxDb->executeQuery($sql, ['unit_spec_id' => $unitImpl->getSpecification()->getId(), 'unit_impl_id' => $unitImpl->getId()]);
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
            $sql = "
                UPDATE course_unit_implementation
                SET
                    title     = :title,
                    open_date = :open_date,
                    due_date  = :due_date
                WHERE id = :id
            ";
            $this->schoolboxDb->executeQuery($sql, [
                'title'     => $unitImpl->getTitle(),
                'open_date' => $unitImpl->getOpenDate()->getDatabaseDate(),
                'due_date'  => $unitImpl->getDueDate()->getDatabaseDate(),
            ]);
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
            ->normaliseDates($publishDate, $openDate, $dueDate, $closeDate, $this->log);

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
            $this->saveModel($this->assessmentMapper, $assessmentImpl);
        }

        if (
            $submissionBoxImpl->getMaxMark() !== $submissionBoxSpec->getMaxMark() ||
            $submissionBoxImpl->getAcceptOnline() !== $submissionBoxSpec->getAcceptOnline() ||
            $submissionBoxImpl->getAcceptMultiple() !== $submissionBoxSpec->getAcceptMultiple() ||
            $submissionBoxImpl->getMarkVisibility() !== $submissionBoxSpec->getMarkVisibility() ||
            $submissionBoxImpl->getSelfReviewability() !== $submissionBoxSpec->getSelfReviewability() ||
            (int)$submissionBoxImpl->getOwner()->getId() !== (int)$owner->getId() ||
            (int)$submissionBoxImpl->getCategory()->getId() !== (int)$unitImpl->getFolder()->getId() ||
            (int)$submissionBoxImpl->getMarkType()->getId() !== (int)$submissionBoxSpec->getMarkType()
                                                                                       ->getId()  // TODO remove hydration bug compensation in 19.1
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
            $this->saveModel($this->submissionBoxMapper, $submissionBoxImpl);
        }
        if ($updated) {
            $this->countSubmissionBoxUpdated++;
        }

        return $submissionBoxImpl;
    }


    /**
     * Synchronises a Canvas Override with a Schoolbox Participation Plan
     *
     * @param SubmissionBox $submissionBoxImpl
     * @param int|null      $canvasId
     * @param string|null   $title
     * @param string|null   $unlockAt
     * @param string|null   $lockAt
     * @param string|null   $dueAt
     * @param bool|null     $allDay
     * @param string|null   $allDayDate
     * @param string[]|null $participants Student's externalIds
     * @param int[]         $studentExternalIdUserIdMap
     * @return Participant\Plan\Model
     * @throws Database\Exception
     * @throws InvalidColumnException
     * @throws Mapper\Exception
     * @throws UnexpectedValueException
     */
    private function syncOverrideWithPlan(
        SubmissionBox $submissionBoxImpl,
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
                $submissionBoxImpl->getAssessmentModel()->getPublishDate()->getDatabase(),
                $submissionBoxImpl->getAssessmentModel()->getDueDate()->getDatabase()
            );
            [$publish, $open, $due, $close] = $this->assignmentDatesBridge->normaliseDates($publish, $open, $due, $close, $this->log);
        }

        $plan = $this->findPlan($submissionBoxImpl, $canvasId);
        if (!$plan) {
            $this->log->debug("Creating new $planType plan '$planName'");
            $this->countPlansCreated++;
            $plan = $this->createPlan($submissionBoxImpl, $isDynamic, $planName, $publish, $open, $due, $close);
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
                $this->saveModel($this->participantPlanMapper, $plan);
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
                $this->saveModel($this->participantMapper, $participant);
                $plan->getParticipants()->setOffset($userId, $participant);
            } elseif ($participant->isExcluded()) {
                $this->log->debug("Un-excluding participant (externalId: '$externalId', userId: $userId)");
                $this->countParticipantsUpdated++;
                $participant->setExcludedAt(null);
                $this->saveModel($this->participantMapper, $participant);
            }
        }

        return $plan;
    }


    /**
     * Loads and returns the a Plan for the specified override, returns NULL if none found.
     *
     * Loads any Participant records
     *
     * @param SubmissionBox $submissionBoxImpl
     * @param int           $overrideCanvasId
     * @return Participant\Plan\Model|null
     * @throws Database\Exception
     * @throws InvalidColumnException
     * @throws Mapper\Exception
     * @throws UnexpectedValueException
     */
    private function findPlan(SubmissionBox $submissionBoxImpl, int $overrideCanvasId): ?Participant\Plan\Model
    {
        $pb          = $this->assignmentOverrideBridge->equalsCanvasOverride(
            $overrideCanvasId,
            $submissionBoxImpl->getAssessmentModel()->getId(),
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
                "Multiple plans found for submission box (id: {$submissionBoxImpl->getId()}) and " .
                "override (canvasId: $overrideCanvasId): [$planIds]"
            );
        }
        return reset($plans);
    }


    /**
     * Create and return a participation plan
     *
     * @param SubmissionBox $submissionBoxImpl
     * @param bool          $isDynamic
     * @param string        $planName
     * @param string|null   $publish
     * @param string|null   $open
     * @param string|null   $due
     * @param string|null   $close
     * @return Participant\Plan\Model
     * @throws Database\Exception
     * @throws UnexpectedValueException
     */
    private function createPlan(
        SubmissionBox $submissionBoxImpl,
        bool $isDynamic,
        string $planName,
        ?string $publish,
        ?string $open,
        ?string $due,
        ?string $close
    ): Participant\Plan\Model {
        $plan = (new Participant\Plan\Model())
            ->setAssessment($submissionBoxImpl->getAssessmentModel())
            ->setName($planName)
            ->setIsDynamic($isDynamic)
            ->setParticipants(new GenericCollection(Participant\Model::class))
            ->setPublishDate($publish === null ? null : Alaress_Date_Format::newDatabaseDate($publish))
            ->setOpenDate($open === null ? null : Alaress_Date_Format::newDatabaseDate($open))
            ->setDueDate($due === null ? null : Alaress_Date_Format::newDatabaseDate($due))
            ->setCloseDate($close === null ? null : Alaress_Date_Format::newDatabaseDate($close));
        $this->saveModel($this->participantPlanMapper, $plan);
        $submissionBoxImpl->getAssessmentModel()->getPlans()->setOffset($plan->getId(), $plan);
        return $plan;
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
     * @throws Database\Exception
     */
    private function createCourseImpl(CourseSpec\Model $courseSpec, Folder\Model $classFolder): CourseImpl\Model
    {
        $courseImpl = (new CourseImpl\Model())
            ->setFolder($classFolder)
            ->setSpecification($courseSpec);
        $this->saveModel($this->courseImplMapper, $courseImpl);
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
     * @throws Database\Exception
     */
    private function createUnitImpl(CourseImpl\Model $courseImpl, UnitSpec\Model $unitSpec, string $startAt, string $endAt): UnitImpl\Model
    {
        $unitImpl = (new UnitImpl\Model())
            ->setCourse($courseImpl)
            ->setSpecification($unitSpec)
            ->setTitle($unitSpec->getFolder()->getName())
            ->setOpenDate(Alaress_Date_Format::newDatabaseDate($startAt))
            ->setDueDate(Alaress_Date_Format::newDatabaseDate($endAt));

        $this->saveModel($this->unitImplMapper, $unitImpl);
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
     * @throws Database\Exception
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
            ->setPlans(Plan\Model::createCollection())
            ->setCloseDate(Alaress_Date_Format::newDatabaseDate($closeDate))
            ->setCreatedAt(Alaress_Date_Format::now())
            ->setUpdatedAt(Alaress_Date_Format::now());

        // Insert AssessmentImpl
        $this->saveModel($this->assessmentMapper, $assessmentImpl);

        // Insert Assessment.context
        $sql = "INSERT INTO assessment_course_unit_implementation (assessment_id, course_unit_implementation_id) VALUES (:assessment_id, :unit_impl_id)";
        $this->schoolboxDb->executeQuery($sql, ['assessment_id' => $assessmentImpl->getId(), 'unit_impl_id' => $unitImpl->getId()]);

        // Insert Assessment.specification
        $sql = "INSERT INTO assessment_specification_implementation (specification_assessment_id, implementation_assessment_id) VALUES (:spec_id, :impl_id)";
        $this->schoolboxDb->executeQuery($sql, ['spec_id' => $assessmentSpec->getId(), 'impl_id' => $assessmentImpl->getId()]);

        // Insert SubmissionBox Impl
        $this->saveModel($this->submissionBoxMapper, $submissionBoxImpl);

        return $submissionBoxImpl;
    }


    /**
     * Saves (either INSERT or UPDATE) a model's attributes.
     * - Does not save joins.
     * - Populates the model's identifier if it has one, and this is an INSERT
     *
     * We need this, rather than the Mapper::save() method, because we don't want to use a transaction, and
     * we want to by-pass pre/post save hooks, eg auto-creation of Assessment's dynamic plan.
     *
     * @param IMapper $mapper
     * @param         $model
     * @return mixed
     * @throws Database\Exception
     */
    private function saveModel(IMapper $mapper, $model)
    {
        $hasId     = (bool)$mapper->getIdentifier();
        $currentId = $hasId ? $mapper->getModelProperty($model, $mapper->getIdentifierProperty()) : null;

        // These mapper methods are not public, so need to use a closure to bypass encapsulation.
        $collection       = $mapper->getModelAsCollection($model);
        $populateFunction = function () use ($collection) {
            /** @noinspection PhpUndefinedMethodInspection */
            return $this->populateUuids($this->populateSaveTimestamps($collection));
        };
        $populateFunction->call($mapper);

        $statement = $this->schoolboxDb->prepareQuery("
            INSERT INTO `{$mapper->getTable()}` ({$mapper->getColumnString()})
            VALUES ({$mapper->getPlaceholderString()})
            ON DUPLICATE KEY UPDATE {$mapper->getUpdateString()};
        ");
        $statement->execute($mapper->dehydrate($model));

        if ($hasId && !$currentId) {
            $mapper->setModelProperty($model, $mapper->getIdentifierProperty(), $this->schoolboxDb->lastInsertId());
        }
        return $model;
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
        $this->countCanvasSectionsSynchronised            = 0;
        $this->countCanvasAssignmentsSynchronised         = 0;
        $this->countCanvasAssignmentOverridesSynchronised = 0;

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

        $this->countPlansCreated = 0;
        $this->countPlansUpdated = 0;
        $this->countPlansDeleted = 0;

        $this->countParticipantsCreated = 0;
        $this->countParticipantsUpdated = 0;
        $this->countParticipantsDeleted = 0;
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
     * Deletes any Plan that appear to have been previously imported from Canvas but no longer require synchronisation.
     *
     * @param int[] $synchronisedPlanIds
     * @throws InvalidColumnException
     * @throws Mapper\Exception
     * @throws UnderflowException
     */
    private function deleteUnSynchronisedPlans(array $synchronisedPlanIds): void
    {
        $planTable = $this->participantPlanMapper->getTable();
        $pb        = $this->assignmentOverrideBridge->equalsAnyCanvasOverride($planTable);
        if (count($synchronisedPlanIds)) {
            $pb->notIn("{$planTable}.id", $synchronisedPlanIds);
        }
        $pb->isNull("{$planTable}.deleted_at");

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
     * Logs the results of this stage
     */
    private function printCounts(): void
    {
        $this->log->notice($this->countCanvasSectionsSynchronised . ' Canvas CourseSections synchronised');
        $this->log->notice($this->countCanvasAssignmentsSynchronised . ' Canvas CourseSections-Assignments synchronised');
        $this->log->notice($this->countCanvasAssignmentOverridesSynchronised . ' Canvas Assignment Overrides synchronised');

        $this->log->notice($this->countCourseImplCreated . ' Schoolbox CourseImpl created');
        $this->log->notice($this->countCourseImplMoved . ' Schoolbox CourseImpl moved');
        $this->log->notice($this->countCourseImplUpdated . ' Schoolbox CourseImpl updated');
        $this->log->notice($this->countCourseImplDeleted . ' Schoolbox CourseImpl deleted');


        $this->log->notice($this->countUnitImplCreated . ' Schoolbox UnitImpl created');
        $this->log->notice($this->countUnitImplMoved . ' Schoolbox UnitImpl moved');
        $this->log->notice($this->countUnitImplUpdated . ' Schoolbox UnitImpl updated');
        $this->log->notice($this->countUnitImplDeleted . ' Schoolbox UnitImpl deleted');

        $this->log->notice($this->countSubmissionBoxCreated . ' Schoolbox SubmissionBoxImpl created');
        $this->log->notice($this->countSubmissionBoxMoved . ' Schoolbox SubmissionBoxImpl moved');
        $this->log->notice($this->countSubmissionBoxUpdated . ' Schoolbox SubmissionBoxImpl updated');
        $this->log->notice($this->countSubmissionBoxDeleted . ' Schoolbox SubmissionBoxImpl deleted');

        $this->log->notice($this->countPlansCreated . ' Schoolbox Plans created');
        $this->log->notice($this->countPlansUpdated . ' Schoolbox Plans updated');
        $this->log->notice($this->countPlansDeleted . ' Schoolbox Plans deleted');

        $this->log->notice($this->countParticipantsCreated . ' Schoolbox Participants created');
        $this->log->notice($this->countParticipantsUpdated . ' Schoolbox Participants updated');
        $this->log->notice($this->countParticipantsDeleted . ' Schoolbox Participants deleted');
    }
}

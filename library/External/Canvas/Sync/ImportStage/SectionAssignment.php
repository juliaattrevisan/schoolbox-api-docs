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
use External\Canvas\Sync\Bridge;
use External\Canvas\Sync\ImportStage\Traits\ModelSaveTrait;
use External_ClassInterface;
use PDO;
use Psr\Log\LoggerInterface;
use Schoolbox\Assessment\Component\SubmissionBox;
use Schoolbox\Assessment\Component\SubmissionBoxMapper;
use Schoolbox\Learning\Assessment\DataModel as Assessment;
use Schoolbox\Assessment\Participant;
use Schoolbox\Learning\Assessment\DataModel\IAssessableDataModel;
use Schoolbox\Learning\Course\DataModel\Implementation\Unit as UnitImpl;
use Schoolbox\Learning\Course\DataModel\Specification\Unit as UnitSpec;
use Schoolbox\Mapper;
use Schoolbox\User;
use Schoolbox\UserMapper;
use UnexpectedValueException;
use function array_key_exists;
use function array_map;
use function array_merge;
use function count;
use function implode;
use function reset;

/**
 * Responsible for importing Class Assignments.
 *
 * Uses Canvas Section + Assignment + Override data to generate Schoolbox SubmissionBoxImpl, Plans + Participants
 */
class SectionAssignment extends SectionAssessmentAbstract
{
    use ModelSaveTrait;

    /** @var IConnector */
    private $canvasDb;

    /** @var IConnector */
    private $schoolboxDb;

    /** @var SubmissionBoxMapper */
    private $submissionBoxMapper;

    /** @var Assessment\Mapper */
    private $assessmentMapper;

    /** @var Participant\Plan\Mapper */
    private $participantPlanMapper;

    /** @var LoggerInterface */
    private $log;

    /** @var SubmissionBox[] $submissionBoxSpecCache Indexed by Canvas `assignment`.`canvas_id` */
    private $submissionBoxSpecCache = [];


    /**
     * @param IConnector                $canvasDb
     * @param IConnector                $schoolboxDb
     * @param External_ClassInterface   $externalClass
     * @param string|null               $currentFileYear
     * @param UnitImpl\Mapper           $unitImplMapper
     * @param UnitSpec\Mapper           $unitSpecMapper
     * @param SubmissionBoxMapper       $submissionBoxMapper
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
        SubmissionBoxMapper $submissionBoxMapper,
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
        parent::__construct(
            $canvasDb,
            $schoolboxDb,
            $externalClass,
            $currentFileYear,
            $unitImplMapper,
            $unitSpecMapper,
            $assessmentMapper,
            $planMapper,
            $participantMapper,
            $userMapper,
            $log,
            $assignmentDatesBridge,
            $courseBridge,
            $courseSectionBridge,
            $assignmentOverrideBridge
        );
        $this->canvasDb              = $canvasDb;
        $this->schoolboxDb           = $schoolboxDb;
        $this->submissionBoxMapper   = $submissionBoxMapper;
        $this->assessmentMapper      = $assessmentMapper;
        $this->participantPlanMapper = $planMapper;
        $this->log                   = $log;
    }

    /** @inheritDoc */
    protected function getCanvasEntity(): string
    {
        return 'assignment';
    }


    /** @inheritDoc */
    protected function getSchoolboxEntity(): string
    {
        return 'submissionBox';
    }


    /** @inheritDoc */
    protected function loadCanvasAssessmentAndOverrideData(array $sectionSisSourceIds): array
    {
        if (!count($sectionSisSourceIds)) {
            return [];
        }
        $assignmentCanvasIds = $this->getAssignmentCanvasIds();
        if (!count($assignmentCanvasIds)) {
            return [];
        }

        $mySqlQuoter                = new Database\Quoter\Mysql\Quoter();
        $pb                         = (new Builder($mySqlQuoter, ''))->in('section.sis_source_id', $sectionSisSourceIds);
        $whereClause                = implode(' AND ', $pb->getWhere());
        $assignmentCanvasIdBindings = Builder::getBindings($assignmentCanvasIds, 'a_cid');
        $sql                        = "
            SELECT
                course.canvas_id                                    'course.canvas_id',
                assignment.canvas_id                                'assessable.canvas_id',
                assignment.title                                    'assessable.title',
                assignment.visibility                               'assessable.visibility',
                CONVERT_TZ(assignment.unlock_at, 'UTC', 'SYSTEM')   'assessable.unlock_at',
                CONVERT_TZ(assignment.lock_at, 'UTC', 'SYSTEM')     'assessable.lock_at',
                CONVERT_TZ(assignment.due_at, 'UTC', 'SYSTEM')      'assessable.due_at',
                assignment.all_day                                  'assessable.all_day',
                assignment.all_day_date                             'assessable.all_day_date',
                override.canvas_id                                  'override.canvas_id',
                override.title                                      'override.title',
                CONVERT_TZ(override.unlock_at, 'UTC', 'SYSTEM')     'override.unlock_at',
                CONVERT_TZ(override.lock_at, 'UTC', 'SYSTEM')       'override.lock_at',
                CONVERT_TZ(override.due_at, 'UTC', 'SYSTEM')        'override.due_at',
                override.all_day                                    'override.all_day',
                override.all_day_date                               'override.all_day_date',
                override_section.canvas_id                          'override_section.canvas_id',
                override_section.sis_source_id                      'override_section.sis_source_id',
                override_group.canvas_id                            'override_group.canvas_id',
                override_group.name                                 'override_group.name',
                group_pseudonym.canvas_id                           'group_pseudonym.canvas_id',
                group_pseudonym.sis_user_id                         'group_pseudonym.sis_user_id',
                user_pseudonym.canvas_id                            'user_pseudonym.canvas_id',
                user_pseudonym.sis_user_id                          'user_pseudonym.sis_user_id'
            FROM course_dim course
            JOIN course_section_dim section ON course.id = section.course_id
            LEFT JOIN assignment_dim assignment ON assignment.course_id = section.course_id AND assignment.canvas_id IN ({$assignmentCanvasIdBindings->getPlaceholder()})
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
            WHERE {$whereClause}
            ORDER BY course.code, course.canvas_id, section.canvas_id, assignment.canvas_id, override.canvas_id
        ";
        $statement                  = $this->canvasDb->executeQuery($sql, array_merge($pb->getParams(), $assignmentCanvasIdBindings->getParams()));
        return $this->buildCanvasOverrideData($statement);
    }


    /** @inheritDoc */
    protected function findAssessableSpec(int $assessmentCanvasId): IAssessableDataModel
    {
        // Because Canvas Assignments (SB SubBox) belong to a Course, rather than CourseSection (SB Class),
        // we either need to either loop assignment per class, or classes per assignment.
        // Eg, if fetching assignments inside a class, we don't want to hit the DB next time we process the same logical
        // assignment in a different class.
        // Therefore cache the assignment fetch so subsequent class iterations don't hit the db.
        if (!array_key_exists($assessmentCanvasId, $this->submissionBoxSpecCache)) {
            $submissionBox = $this->submissionBoxMapper->findOneByColumn('external_id', $assessmentCanvasId);
            $submissionBox->getAssessmentModel()->setDueWork($submissionBox);
            $this->submissionBoxSpecCache[$assessmentCanvasId] = $submissionBox;
        }

        return $this->submissionBoxSpecCache[$assessmentCanvasId];
    }


    /**
     * @inheritDoc
     * @param IAssessableDataModel|SubmissionBox $assessable
     */
    protected function getAssessableOwner(IAssessableDataModel $assessable): User
    {
        return $assessable->getOwner();
    }


    /**
     * @inheritDoc
     * @param IAssessableDataModel|SubmissionBox $assessableImpl
     * @param IAssessableDataModel|SubmissionBox $assessableSpec
     */
    protected function checkAssessableForUpdates(
        int $assessmentCanvasId,
        IAssessableDataModel $assessableImpl,
        IAssessableDataModel $assessableSpec,
        User $owner,
        UnitImpl\Model $unitImpl
    ): bool {
        if (
            $assessableImpl->getMaxMark() === $assessableSpec->getMaxMark() &&
            $assessableImpl->getAcceptOnline() === $assessableSpec->getAcceptOnline() &&
            $assessableImpl->getAcceptMultiple() === $assessableSpec->getAcceptMultiple() &&
            $assessableImpl->getMarkVisibility() === $assessableSpec->getMarkVisibility() &&
            $assessableImpl->getSelfReviewability() === $assessableSpec->getSelfReviewability() &&
            $assessableImpl->getOwner()->getId() === $owner->getId() &&
            $assessableImpl->getCategory()->getId() === $unitImpl->getFolder()->getId() &&
            $assessableImpl->getMarkType()->getId() === $assessableSpec->getMarkType()->getId()
        ) {
            return false;
        }
        $this->log->debug("Updating SubmissionBoxImpl for Canvas Assignment (canvasId: $assessmentCanvasId)");
        $assessableImpl
            ->setMaxMark($assessableSpec->getMaxMark())
            ->setAcceptOnline($assessableSpec->getAcceptOnline())
            ->setAcceptMultiple($assessableSpec->getAcceptMultiple())
            ->setMarkVisibility($assessableSpec->getMarkVisibility())
            ->setSelfReviewability($assessableSpec->getSelfReviewability())
            ->setOwner($owner)
            ->setCategory($unitImpl->getFolder())
            ->setMarkType($assessableSpec->getMarkType());
        $this->submissionBoxMapper->save($assessableImpl);
        return true;
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
    protected function findAssessableImpl(Assessment\Model $assessmentSpec, UnitImpl\Model $unitImpl): ?IAssessableDataModel
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
        $rows = $this->schoolboxDb->executeQuery($sql, [
            'aSpecId'    => $assessmentSpec->getId(),
            'unitImplId' => $unitImpl->getId(),
        ])->fetchAll();
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


    /** @inheritDoc */
    protected function createAssessableImpl(
        IAssessableDataModel $assessableSpec,
        UnitImpl\Model $unitImpl,
        User $owner,
        string $publishDate,
        string $openDate,
        string $dueDate,
        string $closeDate
    ): IAssessableDataModel {
        $assessmentImpl = $this->createAssessmentImplFromSpec(
            $assessableSpec->getAssessmentModel(),
            $unitImpl,
            $publishDate,
            $openDate,
            $dueDate,
            $closeDate
        );

        /** @var SubmissionBox $submissionBoxImpl */
        $submissionBoxImpl = $assessmentImpl->getDueWork();
        $submissionBoxImpl
            ->setAssessmentModel($assessmentImpl)            // TODO This line compensates for a core bug. Fix this properly in develop.
            ->setCategory($unitImpl->getFolder())
            ->setOwner($owner)
            ->setExternalId(null);

        // Insert SubmissionBox Impl
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


    /** @inheritDoc */
    protected function getAssessableAssessmentIds(): array
    {
        $sql = "
            SELECT aImpl.id
            FROM submission_box sbSpec
            JOIN assessment aSpec ON aSpec.id = sbSpec.assessment_id
            JOIN assessment_specification_implementation asi ON aSpec.id = asi.specification_assessment_id
            JOIN assessment aImpl ON aImpl.id = asi.implementation_assessment_id
            WHERE
                  sbSpec.external_id IS NOT NULL AND
                  aImpl.deleted_at IS NULL
        ";
        return array_map('intval', $this->schoolboxDb->executeQuery($sql)->fetchAll(PDO::FETCH_COLUMN));
    }


    /** @inheritDoc */
    protected function getCandidatePlanIds(): array
    {
        $sql = "
            SELECT pp.id
            FROM participant_plan pp
            JOIN assessment a ON a.id = pp.assessment_id
            JOIN assessment_specification_implementation asi ON asi.implementation_assessment_id = a.id
            JOIN submission_box sb ON sb.assessment_id = a.id
        ";
        return array_map('intval', $this->schoolboxDb->executeQuery($sql)->fetchAll(PDO::FETCH_COLUMN));
    }
}

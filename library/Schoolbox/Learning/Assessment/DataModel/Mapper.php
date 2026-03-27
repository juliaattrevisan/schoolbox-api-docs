<?php

/**
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

namespace Schoolbox\Learning\Assessment\DataModel;

use Alaress\Database\Predicate\Builder;
use Alaress\Database\Predicate\InvalidColumnException;
use Alaress\Database\Predicate\SqlFunc\Coalesce;
use Alaress_Date_Format;
use PDO;
use PDOException;
use PDOStatement;
use Schoolbox\Assessment\Component\SubmissionBox;
use Schoolbox\Assessment\Component\SubmissionBoxMapper;
use Schoolbox\Assessment\Course\Task;
use Schoolbox\Assessment\Course\TaskMapper;
use Schoolbox\Assessment\Participant\Plan;
use Schoolbox\Collection;
use Schoolbox\Curriculum\Node\Mapper as CurriculumNodeMapper;
use Schoolbox\GenericCollection;
use Schoolbox\Learning\Assessment\AssessmentType;
use Schoolbox\Learning\Assessment\IAssessable;
use Schoolbox\Learning\Assessment\WorkType\Mapper as WorkTypeMapper;
use Schoolbox\Learning\Course\DataModel\Implementation as CourseImpl;
use Schoolbox\Learning\Course\DataModel\Implementation\Unit as UnitImpl;
use Schoolbox\Learning\Course\DataModel\Specification as CourseSpec;
use Schoolbox\Learning\Course\DataModel\Specification\Unit as UnitSpec;
use Schoolbox\Learning\InlineGrader\Kami\Model as KamiModel;
use Schoolbox\Learning\Quiz;
use Schoolbox\LTI\Assessment as LtiAssessment;
use Schoolbox\Mapper as BaseMapper;
use Schoolbox\Mapper\Exception;
use Schoolbox\Mapper\Exception\Deleted;
use Schoolbox\Resource\Folder;
use Schoolbox\User;
use UnderflowException;
use Schoolbox\Assessment\Participant\Plan\Mapper as PlanMapper;

/**
 * Class Mapper
 *
 * @method Model createModel($id=null)
 * @method Model findById($id)
 * @method Model[]|GenericCollection findByColumn($columnName, $columnValue, $options = [])
 * @method Model findOneByColumnList($columnList, array $options = [])
 * @method Model hydrate(array $row, $alias = '', $callback = null)
 */
class Mapper extends BaseMapper
{
    /**
     * Temporary internal flag for setting whether project sub-assessments are
     * hydrated.
     *
     * This is switched off when loading projects as a context for other
     * assessments: it's unlikely that an assessment within a project will need
     * to know directly about its siblings.
     */
    private bool $hydrateProjectSubAssessments = true;

    /**
     * @inheritdoc
     * @throws BaseMapper\Exception If unable to derive an ID for mapped joins
     */
    protected function mapping()
    {
        $this->hasTable('assessment');
        $this->hasModel(Model::class);
        $this->uses('id', self::TYPE_INTEGER, self::COLUMN_IDENTIFIER);
        $this->uses('title', self::TYPE_STRING);
        $this->uses('description', self::TYPE_STRING);
        $this->uses('publish_date', self::TYPE_DATETIME);
        $this->uses('open_date', self::TYPE_DATETIME);
        $this->uses('due_date', self::TYPE_DATETIME);
        $this->uses('close_date', self::TYPE_DATETIME);
        $this->uses('weight', self::TYPE_DECIMAL);
        $this->uses('sequence', self::TYPE_INTEGER);
        $this->joins('work_type_id', WorkTypeMapper::class, 'id', self::TYPE_MODEL, null, 'workType', 'work_type');
        $this->uses('default_open_span', self::TYPE_INTEGER);
        $this->uses('course_managed', self::TYPE_BOOLEAN);
        $this->uses('seb_enabled', self::TYPE_BOOLEAN);
        $this->uses('original_assessment_id', self::TYPE_INTEGER);
        $this->uses('created_at', self::TYPE_DATETIME, self::COLUMN_CREATED_TIMESTAMP);
        $this->uses('updated_at', self::TYPE_DATETIME, self::COLUMN_UPDATED_TIMESTAMP);
        $this->uses('deleted_at', self::TYPE_DATETIME, self::COLUMN_SOFTDELETE);
        // Type joins (what type of assessment is this?)
        // * task
        // * due work
        // * quiz
        // * project
        // * LTI assessment
        // * Lesson Plan
        // * Dialog Agent
        // * Reporting
        $this->joinsReverseSingle('assessment_id', TaskMapper::class, 'task', 'task');
        $this->joinsReverseSingle('assessment_id', SubmissionBoxMapper::class, 'duework', 'duework');
        $this->joinsReverseSingle('assessment_id', Quiz\Mapper::class, 'quiz', 'quiz');
        $this->joinsReverseSingle('assessment_id', Project\Mapper::class, 'project', 'project');
        $this->joinsReverseSingle('assessment_id', LtiAssessment\Mapper::class, 'ltiAssessment', 'ltiAssessment');
        $this->joinsReverseSingle('assessment_id', LessonPlan\Mapper::class, 'lessonPlan', 'lessonPlan');
        $this->joinsReverseSingle('assessment_id', DialogAgent\Mapper::class, 'dialogAgent', 'dialogAgent');
        $this->joinsReverseSingle('assessment_id', Reporting\Mapper::class, 'reporting', 'reporting');

        // Participation plan join
        $this->joinsReverseMulti('assessment_id', Plan\Mapper::class, 'plans', 'plans');

        // Context joins (where does this assessment live?)
        // * folder (via assessment_folder)
        // * unit specification (via assessment_course_unit_specification)
        // * unit implementation (via assessment_course_unit_implementation)
        // * project (via project_assessment)
        $this->joinsVia(
            'assessment_folder',
            Folder\Mapper::class,
            'id',
            'folder_id',
            'assessment_id',
            'id',
            self::TYPE_MODEL,
            null,
            'contextFolder'
        );
        $this->joinsVia(
            'assessment_course_unit_specification',
            UnitSpec\Mapper::class,
            'id',
            'course_unit_specification_id',
            'assessment_id',
            'id',
            self::TYPE_MODEL,
            null,
            'contextUnitSpecification'
        );
        $this->joinsVia(
            'assessment_course_unit_implementation',
            UnitImpl\Mapper::class,
            'id',
            'course_unit_implementation_id',
            'assessment_id',
            'id',
            self::TYPE_MODEL,
            null,
            'contextUnitImplementation'
        );
        $this->joinsVia(
            'project_assessment',
            Project\Mapper::class,
            'id',
            'project_id',
            'assessment_id',
            'id',
            self::TYPE_MODEL,
            null,
            'contextProject'
        );
        // the specification of this assessment
        // (if this assessment is within a unit implementation)
        // An implementation may only have one specification,
        // a spec may have multiple implementations.
        $this->joinsVia(
            'assessment_specification_implementation',
            static::class,
            'id',
            'specification_assessment_id',
            'implementation_assessment_id',
            'id',
            self::TYPE_MODEL,
            null,
            'specificationAssessment'
        );

        /* Curriculum Nodes
         */
        $this->joinsVia(
            'curriculum_node_for_assessment',
            CurriculumNodeMapper::class,
            'id',
            'curriculum_node_id',
            'assessment_id',
            null,
            null,
            null,
            'curriculumNodes'
        );


        $this->registerCallback(self::CALLBACK_PRESAVE, $this->setSequence(...));
        $this->registerCallback(self::CALLBACK_PRESAVE, $this->validateOpenSpan(...));
        $this->registerCallback(self::CALLBACK_PRESAVE, $this->validateDatePresence(...));
        $this->registerCallback(self::CALLBACK_PRESAVE, $this->validateDateOrder(...));
        $this->registerCallback(self::CALLBACK_POSTSAVE, $this->createDynamicPlanIfNew(...));
        $this->registerCallback(self::CALLBACK_POSTSAVE, $this->updateAssessmentRelationView(...));
        $this->registerCallback(self::CALLBACK_POSTSAVE, $this->reorderSequenceIfCustomised(...));

        $this->registerCallback(self::CALLBACK_POSTSOFTDELETE, $this->softDeleteQuiz(...));
        $this->registerCallback(self::CALLBACK_POSTSOFTDELETE, $this->softDeleteTask(...));
        $this->registerCallback(self::CALLBACK_POSTSOFTDELETE, $this->softDeleteProject(...));
        $this->registerCallback(self::CALLBACK_POSTSOFTDELETE, $this->softDeleteDueWork(...));
        $this->registerCallback(self::CALLBACK_POSTSOFTDELETE, $this->softDeleteLtiAssessment(...));
        $this->registerCallback(self::CALLBACK_POSTSOFTDELETE, $this->softDeleteLessonPlan(...));
        $this->registerCallback(self::CALLBACK_POSTSOFTDELETE, $this->softDeleteDialogAgent(...));
        $this->registerCallback(self::CALLBACK_POSTSOFTDELETE, $this->softDeleteReporting(...));
        $this->registerCallback(self::CALLBACK_POSTSOFTDELETE, $this->softDeleteCustomisation(...));

        $this->registerPersistSearchIndexCallbacks();
    }

    /**
     * Override findByColumnList to always order by sequence.
     *
     * If another order is defined, the sequence ordering will apply as a lower
     * priority to that order.
     *
     * @param array $columnList
     * @param array $options
     * @return Collection
     * @throws BaseMapper\Exception If invalid column names specified
     */
    public function findByColumnList($columnList, $options = [])
    {
        if (empty($options['order']['sequence'])) {
            $options['order']['sequence'] = 'asc';
        }
        return parent::findByColumnList($columnList, $options);
    }

    /**
     * Override findByColumnList to always order by sequence.
     *
     * If another order is defined, the sequence ordering will apply as a lower
     * priority to that order.
     *
     * @param Builder $pb
     * @param array $options
     * @return GenericCollection|IAssessable[]
     */
    public function findByPredicates(Builder $pb, array $options = [])
    {
        if (empty($options['order']['sequence'])) {
            $options['order']['sequence'] = 'asc';
        }
        return parent::findByPredicates($pb, $options);
    }


    /**
     * Enables predicates built for a specific participant
     *
     * @param Builder $pb
     * @param User    $student
     * @param array   $options
     * @return GenericCollection|IAssessable[] Indexed by assessment.id
     * @throws BaseMapper\DefinitionException
     */
    public function findByPredicatesForParticipant(Builder $pb, User $student, array $options = [])
    {
        if (empty($options['order']['sequence'])) {
            $options['order']['sequence'] = 'asc';
        }
        return $this->executeSelect($this->getSelectString(), $this->getFromStringForParticipant($student), $pb, [], $options)->indexBy('id');
    }

    /**
     * @param Model      $assessment
     * @param Model|null $previous
     * @return bool
     *@throws PDOException
     */
    protected function createDynamicPlanIfNew(Model $assessment, ?Model $previous = null)
    {
        if ($previous || $assessment->hasProject() || ($assessment->getPlans() && $assessment->getPlans()->count())) {
            // this is a modify, or a project, or already has plan[s]; do nothing
            return true;
        }

        // I am aware that I've singletonned this in, but this will shortly be updated when a later ticket exposes the
        // ability for teachers to customise participation. This is purely a stop-gap to retain existing functionality.
        try {
            // create a dyna-plan so class enrolments are still picked up
            $planMap = \Schoolbox\Assessment\Participant\Plan\Mapper::getInstance();
            $planMap->save(
                $planMap->createModel()
                    ->setAssessment($assessment)
                    ->setIsDynamic(null === $assessment->getOriginalAssessmentId() ?
                                       PlanMapper::PLAN_TYPE_DYNAMIC : PlanMapper::PLAN_TYPE_CUSTOM_PARTICIPATION)
            );
        } catch (\Schoolbox\Mapper\Exception $e) {
            return false;
        } catch (\UnexpectedValueException $e) {
            return false;
        }

        return true;
    }

    protected function reorderSequenceIfCustomised(Model $assessment): bool
    {
        return true;
    }

    /**
     * When deleting an Assessment that is a Quiz, we need to delete all sub-objects of the Quiz
     * @param Model $assessment
     * @throws BaseMapper\DefinitionException If join name is not on this mapper
     */
    public function softDeleteQuiz(Model $assessment)
    {
        if ($assessment->getQuiz() instanceof Quiz\Model) {
            $quizMapper = $this->getJoinMapper('quiz');
            $quiz = $quizMapper->findByColumn('assessment_id', $assessment->getId(), ['deleted' => true]);
            $quizMapper->delete($quiz);
        }
    }


    /**
     * When deleting an Assessment that is a Task, we need to delete all sub-objects of the Task
     * @param Model $assessment
     * @throws BaseMapper\DefinitionException If join name is not on this mapper
     */
    public function softDeleteTask(Model $assessment)
    {
        if ($assessment->getTask() instanceof Task) {
            $taskMapper = $this->getJoinMapper('task');
            $task = $taskMapper->findByColumn('assessment_id', $assessment->getId(), ['deleted' => true]);
            $taskMapper->delete($task);
        }
    }


    /**
     * When deleting an Assessment that is a Project, we need to delete all sub-objects of the Project
     * @param Model $assessment
     * @throws BaseMapper\DefinitionException If join name is not on this mapper
     */
    public function softDeleteProject(Model $assessment)
    {
        if ($assessment->getProject() instanceof Project\Model) {
            $projectMapper = $this->getJoinMapper('project');
            $project = $projectMapper->findByColumn('assessment_id', $assessment->getId(), ['deleted' => true]);
            $projectMapper->delete($project);
        }
    }


    /**
     * When deleting an Assessment that is a Project, we need to delete all sub-objects of the Project
     *
     * @param Model $assessment
     * @return bool
     * @throws BaseMapper\DefinitionException If join name is not on this mapper
     * @throws \UnexpectedValueException      If a callback fails and we need to halt
     */
    public function softDeleteDueWork(Model $assessment): bool
    {
        if ($assessment->getDueWork() instanceof SubmissionBox) {
            /** @var SubmissionBoxMapper $submissionBoxMapper */
            $submissionBoxMapper = $this->getJoinMapper('duework');
            $submissionBoxes     = $submissionBoxMapper->findByColumn('assessment_id', $assessment->getId());
            $submissionBoxMapper->softDelete($submissionBoxes);
        }
        return true;
    }


    /**
     * When deleting an LTI assessment, delete all of its sub-objects.
     *
     * @param Model $assessment
     * @return bool
     * @throws BaseMapper\DefinitionException If join name is not on this mapper
     * @throws \UnexpectedValueException      If a callback fails and we need to halt
     */
    public function softDeleteLtiAssessment(Model $assessment): bool
    {
        if ($assessment->getLtiAssessment() instanceof LtiAssessment\Model) {
            /** @var LtiAssessment\Mapper $ltiAssessmentMapper */
            $ltiAssessmentMapper = $this->getJoinMapper('ltiAssessment');
            $ltiAssessments      = $ltiAssessmentMapper->findByColumn('assessment_id', $assessment->getId());
            $ltiAssessmentMapper->softDelete($ltiAssessments);

            // Soft deleting scores here instead of LTI/Assessment/Mapper, which doesn't have deleted_at field
            /** @var LtiAssessment\Score\Mapper $scoreMapper */
            $scoreMapper = $ltiAssessmentMapper->getJoinMapper('scores');
            $scoreMapper->deleteByColumn('lti_assessment_id', $ltiAssessments->getPropertyValues('id'));
        }
        return true;
    }


    /**
     * Delete all of a Lesson Plan's subobjects
     *
     * @param Model $assessment
     * @return bool
     * @throws BaseMapper\DefinitionException
     */
    public function softDeleteLessonPlan(Model $assessment): bool
    {
        if ($assessment->getLessonPlan() instanceof LessonPlan\Model) {
            $lessonPlanMapper = $this->getJoinMapper('lessonPlan');
            $lessonPlans      = $lessonPlanMapper->findByColumn('assessment_id', $assessment->getId());
            $lessonPlanMapper->softDelete($lessonPlans);
        }

        return true;
    }


    /**
     * Delete all of a Dialog Agent's subobjects
     *
     * @param Model $assessment
     * @return bool
     */
    public function softDeleteDialogAgent(Model $assessment): bool
    {
        if ($assessment->getDialogAgent() instanceof DialogAgent\Model) {
            $dialogAgentMapper = $this->getJoinMapper('dialogAgent');
            $dialogAgents = $dialogAgentMapper->findByColumn('assessment_id', $assessment->getId());
            $dialogAgentMapper->softDelete($dialogAgents);
        }

        return true;
    }


    /**
     * Delete all of a Reporting's subobjects
     *
     * @param Model $assessment
     * @return bool
     */
    public function softDeleteReporting(Model $assessment): bool
    {
        if ($assessment->getReporting() instanceof Reporting\Model) {
            $reportingMapper = $this->getJoinMapper('reporting');
            $reporting = $reportingMapper->findByColumn('assessment_id', $assessment->getId());
            $reportingMapper->softDelete($reporting);
        }

        return true;
    }


    /**
     * Cascades soft-delete to any customisation of this assessment.
     *
     * @param Model $assessment
     * @return bool
     */
    public function softDeleteCustomisation(Model $assessment): bool
    {
        try {
            $customisation = $this->findByColumn('original_assessment_id', $assessment->getId());
            if ($customisation->count()) {
                $this->delete($customisation);
            }
        } catch (\Schoolbox\Mapper\Exception | \Exception $e) {
            return false;
        }
        return true;
    }


    /**
     * Somewhat efficient query for retrieving the assessments which this student has participated in.
     *
     * @param User                     $student
     * @param bool                     $usePlanDates Should we try to filter on plan dates instead of assessment dates?
     * @param Alaress_Date_Format|null $from
     * @param Alaress_Date_Format|null $to
     * @return array|int[]
     * @throws BaseMapper\DefinitionException If join name is not on this mapper
     * @throws \Alaress\Database\Predicate\InvalidColumnException
     */
    public function findAssessmentsWithInteractions(
        User $student,
        bool $usePlanDates,
        ?Alaress_Date_Format $from = null,
        ?Alaress_Date_Format $to = null
    ): array {
        // First, fetch the assessments which the student is participating in
        $pb = $this->getPredicateBuilder();
        $pb->isNull('a.deleted_at');
        // Filter task/dueWork/quiz by term dates
        $dueDateCol = $usePlanDates ? new Coalesce(['pp.due_date', 'a.due_date']) : 'a.due_date';
        $pb->betweenDates($dueDateCol, $from, $to);

        $planJoin = '';
        if ($usePlanDates) {
            $planJoin = "
                LEFT JOIN {$this->getJoinSql('plans', 'a', 'pp')}
                JOIN {$this->getJoinSql('plans.participants', 'pp', 'p')} AND p.user_id = :userId
            ";
            $pb->putParam('userId', $student->getId());
        }

        $where = implode(' AND ', $pb->getWhere());
        $query = "
            SELECT DISTINCT a.id
            FROM  {$this->getDatabase()->getQuoter()->quoteIdentifier($this->getTable())} a
                  {$planJoin}
            WHERE {$where}
        ";
        $statement = $this->getDatabase()->prepare($query);
        $statement->execute($pb->getParams());
        $assessmentIds = array_column($statement->fetchAll(\PDO::FETCH_ASSOC), 'id');
        if (empty($assessmentIds)) {
            return [];
        }

        // Then, find the interactions within those assessments:
        // search by chunks to avoid issues with large numbers of IN parameters
        $finalAssessmentIds = [];
        foreach (array_chunk($assessmentIds, 100) as $chunk) {
            $pb    = $this->getPredicateBuilder()
                          ->in('a.id', $chunk)
                          ->putParam('userId', $student->getId());
            $where = implode(' AND ', $pb->getWhere());
            $query = "
                SELECT DISTINCT a.id
                FROM  {$this->getDatabase()->getQuoter()->quoteIdentifier($this->getTable())} a
                JOIN  {$this->getJoinSql('task', 'a', 't', true)}
                JOIN  {$this->getJoinSql('task.completion', 't', 'tc')} AND tc.student_id = :userId
                WHERE {$where}

                UNION

                SELECT DISTINCT a.id
                FROM  {$this->getDatabase()->getQuoter()->quoteIdentifier($this->getTable())} a
                JOIN  {$this->getJoinSql('duework', 'a', 'd', true)}
                JOIN  {$this->getJoinSql('duework.submission', 'd', 'ds')} AND ds.owner = :userId
                WHERE {$where}

                UNION

                SELECT DISTINCT a.id
                FROM  {$this->getDatabase()->getQuoter()->quoteIdentifier($this->getTable())} a
                JOIN  {$this->getJoinSql('duework', 'a', 'd', true)}
                JOIN  {$this->getJoinSql('duework.return', 'd', 'dr')} AND dr.owner = :userId
                WHERE {$where}

                UNION

                SELECT DISTINCT a.id
                FROM  {$this->getDatabase()->getQuoter()->quoteIdentifier($this->getTable())} a
                JOIN  {$this->getJoinSql('quiz', 'a', 'q', true)}
                JOIN  {$this->getJoinSql('quiz.attempt', 'q', 'qa')} AND qa.user_id = :userId
                WHERE {$where}

                UNION

                SELECT DISTINCT a.id
                FROM  {$this->getDatabase()->getQuoter()->quoteIdentifier($this->getTable())} a
                JOIN  {$this->getJoinSql('ltiAssessment', 'a', 'l', true)}
                JOIN  {$this->getJoinSql('ltiAssessment.scores', 'l', 'ls')} AND ls.student_id = :userId
                WHERE {$where}

                UNION

                SELECT DISTINCT a.id
                FROM  {$this->getDatabase()->getQuoter()->quoteIdentifier($this->getTable())} a
                JOIN  {$this->getJoinSql('dialogAgent', 'a', 'da', true)}
                JOIN  {$this->getJoinSql('dialogAgent.responses', 'da', 'dr')} AND dr.learner_id = :userId
                WHERE {$where}

                -- Reporting activity doesn't have any responses
                
                ORDER BY id ASC
            ";

            $statement = $this->getDatabase()->prepare($query);
            $statement->execute($pb->getParams());
            $finalAssessmentIds = array_merge(
                $finalAssessmentIds,
                array_column($statement->fetchAll(\PDO::FETCH_ASSOC), 'id'),
            );
        }

        // Preserve uniqueness and ordering of IDs
        $finalAssessmentIds = array_unique($finalAssessmentIds);
        sort($finalAssessmentIds);
        return $finalAssessmentIds;
    }

    /**
     * Selecting from the assessment table also returns:
     * * whatever actual activity (due work, quiz, etc.) is joined onto it
     * * the assessment's context
     *
     * `hydrateSelect` will then hydrate the activity's and context's
     * sub-objects.
     *
     * @return string
     * @throws BaseMapper\DefinitionException If join name is not on this mapper
     */
    protected function getSelectString()
    {
        // This select string should not be used to fetch 1-M relations to assessments.
        return "
            SELECT
                {$this->getColumnString()},
                {$this->getJoinColumnString('task', 't')},
                {$this->getJoinColumnString('duework', 'd')},
                {$this->getJoinColumnString('work_type', 'wt')},
                {$this->getJoinColumnString('quiz', 'q')},
                {$this->getJoinColumnString('ltiAssessment', 'l')},
                {$this->getJoinColumnString('lessonPlan', 'lessonPlan')},
                {$this->getJoinColumnString('dialogAgent', 'dialogAgent')},
                {$this->getJoinColumnString('reporting', 'reporting')},
                {$this->getJoinColumnString('project', 'p')},
                {$this->getJoinColumnString('assessment_folder', 'f')},
                {$this->getJoinColumnString('assessment_course_unit_specification', 'us')},
                {$this->getJoinColumnString('assessment_course_unit_implementation', 'ui')},
                {$this->getJoinColumnString('project_assessment', 'pa')},
                {$this->getJoinColumnString('assessment_specification_implementation', 'spec')}
        ";
    }

    /**
     * @return string
     */
    public function getFromString(): string
    {
        return "
            FROM {$this->getDatabase()->getQuoter()->quoteIdentifier($this->getTable())}
            -- Allow join onto deleted sub-objects
            LEFT JOIN {$this->getJoinSql('task', '', 't', true)}
            LEFT JOIN {$this->getJoinSql('duework', '', 'd', true)}
            LEFT JOIN {$this->getJoinSql('work_type', '', 'wt')}
            LEFT JOIN {$this->getJoinSql('quiz', '', 'q', true)}
            LEFT JOIN {$this->getJoinSql('ltiAssessment', '', 'l', true)}
            LEFT JOIN {$this->getJoinSql('lessonPlan', '', 'lessonPlan', true)}
            LEFT JOIN {$this->getJoinSql('dialogAgent', '', 'dialogAgent', true)}
            LEFT JOIN {$this->getJoinSql('reporting', '', 'reporting', true)}
            LEFT JOIN {$this->getJoinSql('project', '', 'p', true)}
            LEFT JOIN {$this->getJoinSql('assessment_folder', '', 'f', true)}
            LEFT JOIN {$this->getJoinSql('assessment_course_unit_specification', '', 'us', true)}
            LEFT JOIN {$this->getJoinSql('assessment_course_unit_implementation', '', 'ui', true)}
            LEFT JOIN {$this->getJoinSql('project_assessment', '', 'pa', true)}
            LEFT JOIN {$this->getJoinSql('assessment_specification_implementation', '', 'spec', true)}
        ";
    }


    /**
     * Optimised method for finding all assessments for a course outline
     * component.
     *
     * A course outline component always contains assessments from a set of
     * unit implementation IDs.
     *
     * @param array<int>                         $uiIds Unit implementation IDs in which to find assessments.
     * @param array<AssessmentType::TYPE_*>|null $types If provided: find assessments of these types only.
     *
     * @return Collection<Model>
     */
    public function findAllByUnitImplementationIdsForCourseOutline(array $uiIds, ?array $types): Collection
    {
        $modelList = $this->createCollection();
        if (empty($uiIds)) {
            return $modelList;
        }

        // Exclude:
        // * all assessment types (due work, task, etc.)
        // * all contexts, except for unit implementation
        // * spec for implementation assessments
        $query = "
            SELECT
                {$this->getColumnString()},
                {$this->getJoinColumnString('work_type', 'wt')},
                {$this->getJoinColumnString('assessment_course_unit_implementation', 'ui')},
                {$this->getJoinColumnString('assessment_course_unit_implementation.specification', 'uis')},
                {$this->getJoinColumnString('assessment_course_unit_implementation.specification.folder', 'uisf')},
                {$this->getJoinColumnString('assessment_course_unit_implementation.course', 'uic')},
                {$this->getJoinColumnString('assessment_course_unit_implementation.course.folder', 'uicf')},
                {$this->getJoinColumnString('assessment_specification_implementation', 'spec')}
            FROM {$this->getDatabase()->getQuoter()->quoteIdentifier($this->getTable())}
            LEFT  JOIN {$this->getJoinSql('work_type', '', 'wt')}
            INNER JOIN {$this->getJoinSql('assessment_course_unit_implementation', '', 'ui', true)}
            INNER JOIN {$this->getJoinSql('assessment_course_unit_implementation.specification', 'ui', 'uis', true)}
            INNER JOIN {$this->getJoinSql('assessment_course_unit_implementation.specification.folder', 'uis', 'uisf', true)}
            INNER JOIN {$this->getJoinSql('assessment_course_unit_implementation.course', 'ui', 'uic', true)}
            INNER JOIN {$this->getJoinSql('assessment_course_unit_implementation.course.folder', 'uic', 'uicf', true)}
            LEFT  JOIN {$this->getJoinSql('assessment_specification_implementation', '', 'spec', true)}
        ";

        $pb = $this->getPredicateBuilder()
            ->in('ui.id', $uiIds)
            ->isNull('deleted_at');

        // Search on types of assessment
        $typeJoins = [];
        if (!empty($types)) {
            $typesPb = $this->getPredicateBuilder();
            foreach ($types as $type) {
                switch ($type) {
                    case AssessmentType::TYPE_TASK:
                        $typeJoins[] = " LEFT JOIN {$this->getJoinSql('task', '', 't', true)}";
                        $typesPb     = $typesPb->notNull('t.id');
                        break;
                    case AssessmentType::TYPE_DUE_WORK:
                        $typeJoins[] = " LEFT JOIN {$this->getJoinSql('duework', '', 'd', true)}";
                        $typesPb     = $typesPb->notNull('d.id');
                        break;
                    case AssessmentType::TYPE_QUIZ:
                        $typeJoins[] = " LEFT JOIN {$this->getJoinSql('quiz', '', 'q', true)}";
                        $typesPb     = $typesPb->notNull('q.assess_id');
                        break;
                    case AssessmentType::TYPE_LTI_ASSESSMENT:
                        $typeJoins[] = " LEFT JOIN {$this->getJoinSql('ltiAssessment', '', 'l', true)}";
                        $typesPb     = $typesPb->notNull('l.id');
                        break;
                    case AssessmentType::TYPE_PROJECT:
                        $typeJoins[] = " LEFT JOIN {$this->getJoinSql('project', '', 'p', true)}";
                        $typesPb     = $typesPb->notNull('p.id');
                        break;
                    case AssessmentType::TYPE_LESSON_PLAN:
                        $typeJoins[] = " LEFT JOIN {$this->getJoinSql('lessonPlan', '', 'lessonPlan', true)}";
                        $typesPb     = $typesPb->notNull('lessonPlan.id');
                        break;
                    case AssessmentType::TYPE_DIALOG_AGENT:
                        $typeJoins[] = " LEFT JOIN {$this->getJoinSql('dialogAgent', '', 'dialogAgent', true)}";
                        $typesPb     = $typesPb->notNull('dialogAgent.id');
                        break;
                    case AssessmentType::TYPE_REPORTING:
                        $typeJoins[] = " LEFT JOIN {$this->getJoinSql('reporting', '', 'reporting', true)}";
                        $typesPb     = $typesPb->notNull('reporting.id');
                        break;
                    default:
                        break;
                }
            }
            if (!empty($typeJoins)) {
                $pb = $pb->any($typesPb);
            }
        }
        $query = $this->getDatabase()->executeQuery(
            $query . implode(' ', $typeJoins) .
            ' WHERE ' . $pb->getWhereIntersect() .
            ' ORDER BY sequence ASC',
            $pb->getParams(),
        );
        foreach ($query as $row) {
            // Hydrate
            $model = $this->hydrate($row);
            $model->setWorkType($this->hydrateJoin($row, 'work_type', 'wt'));

            // Specification
            if ($row['spec.id']) {
                $model->setSpecificationAssessment($this->hydrateJoin($row, 'assessment_specification_implementation', 'spec'));
            } else {
                $model->setSpecificationAssessment(null);
            }

            // Unit impl context
            /** @var UnitImpl\Model $unitImpl */
            $unitImpl = $this->hydrateJoin($row, 'assessment_course_unit_implementation', 'ui');
            /** @var CourseImpl\Model $courseImpl */
            $courseImpl = $this->hydrateJoin($row, 'assessment_course_unit_implementation.course', 'uic')
                               ->setFolder(
                                   $this->hydrateJoin($row, 'assessment_course_unit_implementation.course.folder', 'uicf')
                               );
            $unitImpl->setCourse($courseImpl);
            $courseImpl->getUnits()[$unitImpl->getId()] = $unitImpl;
            /** @var UnitSpec\Model $unitSpec */
            $unitSpec = $this->hydrateJoin($row, 'assessment_course_unit_implementation.specification', 'uis')
                             ->setFolder(
                                 $this->hydrateJoin($row, 'assessment_course_unit_implementation.specification.folder', 'uisf')
                             );
            $unitImpl->setSpecification($unitSpec);
            $unitSpec->getUnitImplementations()[$unitImpl->getId()] = $unitImpl;
            $context = $unitImpl;

            $model->setAssessmentContext($context);

            // Fetch and hydrate using new augmentList methods
            $modelList->add($model);
        }

        // Augmentation for this only:
        // * assessment type (due work, task, etc.)
        $modelList = $this->augmentListWithTasks($modelList);
        $modelList = $this->augmentListWithDueWorks($modelList);
        $modelList = $this->augmentListWithQuizzes($modelList);
        $modelList = $this->augmentListWithLtiAssessments($modelList);
        $modelList = $this->augmentListWithLessonPlans($modelList);
        $modelList = $this->augmentListWithDialogAgents($modelList);
        $modelList = $this->augmentListWithReporting($modelList);
        $modelList = $this->augmentListWithProjects($modelList);

        // Augmentations for one-to-many fields, copied from standard hydrate.
        // Turns out only the following are required:
        // * Kami worksheet (for displaying link to class view)
        // * Folder codes (for determining if student is a participant, when
        //   filtering activity list)
        // * Specification (for determining save to course route)
        // * Project sub-assessments (for displaying in save to course dialog)

        // Due work files and Kami worksheet
//        $dueWorkIds = $modelList->map(fn (Model $model) => $model->getDueWork()?->getId(), false)->getAll();
//        $modelList  = $this->augmentListWithDueWorkFiles($modelList, $dueWorkIds);
//        $modelList  = $this->augmentListWithDueWorkKamiWorksheetTemplates($modelList, $dueWorkIds);

//        /* Task files
//         */
//        $taskIds   = $modelList->map(fn (Model $model) => $model->getTask()?->getId(), false)->getAll();
//        $modelList = $this->augmentListWithTaskFiles($modelList, $taskIds);
//
//        /* Augment with Lesson Plan Files and Lesson Plan Fields
//         */
//        $lessonPlanIds = $modelList->map(fn (Model $model) => $model->getLessonPlan()?->getId(), false)->getAll();
//        $modelList     = $this->augmentListWithLessonPlanFiles($modelList, $lessonPlanIds);
//        $modelList     = $this->augmentWithLessonPlanFields($modelList, $lessonPlanIds);

        // Folder codes for all contexts
        $modelList = $this->augmentListWithFolderCodes($modelList);
        // Specification assessments
        $modelList = $this->augmentListWithSpecifications($modelList);
//        // Grade groups
//        $modelList = $this->augmentListWithGradeGroups($modelList);
//        // Curriculum Nodes
//        $modelList = $this->augmentListWithCurriculumNodes($modelList);

        // Project sub-assessments
        $projectIds = $modelList->map(fn (Model $model) => $model->getProject()?->getId(), false)->getAll();
        if ($this->hydrateProjectSubAssessments) {
            $modelList = $this->augmentListWithProjectSubAssessments($modelList, $projectIds);
        }
//        // Lesson Plan Linked Assessments
//        $modelList = $this->augmentWithLessonPlanAssessments($modelList, $lessonPlanIds);
        return $modelList;
    }


    /**
     * Returns a FROM string suitable for predicates that query the participant or plan tables.
     *
     * @param User $user
     * @return string
     * @throws BaseMapper\DefinitionException
     */
    protected function getFromStringForParticipant(User $user): string
    {
        return $this->getFromString() . "
            LEFT JOIN participant_plan pp ON pp.assessment_id = assessment.id
            LEFT JOIN participant ON participant.participant_plan_id = pp.id AND participant.user_id = {$user->getId()}
        ";
    }


    /**
     * Retrieves all assessable objects that are in this folder, whether they
     * are attached directly to this folder or through a course implementation
     * that is attached to this folder.
     *
     * @param int|array $folderId  A folder ID or list thereof
     * @param Builder   $builder   A PredicateBuilder which contains columns to query on.
     * @param array     $uniqueIds IDs of any assessables that should also be included
     * @param array     $options   Any other options to pass to the final search to `findByColumnList`.
     *
     * @return Collection|Model[]
     */
    public function findAllForFolderId($folderId, Builder $builder, array $uniqueIds = [], array $options = []): Collection
    {
        // Support stuff for query
        $folderList = Builder::canTraverse($folderId) ? $folderId : [$folderId];
        if (0 === \count($folderList) && 0 === \count($uniqueIds)) {
            // Exit early if empty
            return $this->createCollection();
        }

        // Don't attempt to fetch from folders if no folder ids supplied
        $idList = [];
        if (\count($folderList)) {
            $params       = Builder::getArrayParameters($folderList, 'folder');
            $placeholders = Builder::getArrayPlaceholders($folderList, 'folder');

            $idQuery = "
                SELECT a.id
                FROM `folder` f
                INNER JOIN `assessment_folder` AS `v_f`
                    ON `f`.`id` = `v_f`.`folder_id`
                INNER JOIN `assessment` AS `a`
                    ON `a`.`id` = `v_f`.`assessment_id`
                WHERE f.id IN {$placeholders}

                UNION

                SELECT af.id
                FROM `folder` uicf
                INNER JOIN `course_implementation` AS `uic`
                    ON `uicf`.`id` = `uic`.`folder_id` AND `uic`.`deleted_at` IS NULL
                INNER JOIN `course_unit_implementation` AS `ui`
                    ON `uic`.`id` = `ui`.`course_implementation_id` AND `ui`.`deleted_at` IS NULL
                INNER JOIN `assessment_course_unit_implementation` AS `v_ui`
                    ON `ui`.`id` = `v_ui`.`course_unit_implementation_id`
                INNER JOIN `assessment` AS `af`
                    ON `af`.`id` = `v_ui`.`assessment_id`
                WHERE uicf.id IN {$placeholders}

                UNION

                SELECT `p_a`.`assessment_id`
                FROM project_assessment `p_a`
                INNER JOIN `project` AS `p`
                    ON `p`.`id` = `p_a`.`project_id`
                INNER JOIN assessment_course_unit_implementation AS `v_ui`
                    ON `v_ui`.`assessment_id` = `p`.assessment_id
                INNER JOIN `course_unit_implementation` AS `ui`
                    ON `ui`.`id` = `v_ui`.`course_unit_implementation_id`
                INNER JOIN course_implementation AS `uic`
                    ON `uic`.`id` = `ui`.course_implementation_id
                WHERE `uic`.`folder_id` IN {$placeholders}

                UNION

                SELECT `p_a`.`assessment_id`
                FROM project_assessment `p_a`
                INNER JOIN `project` AS `p`
                    ON `p`.`id` = `p_a`.`project_id`
                INNER JOIN assessment_folder AS `v_f`
                    ON `v_f`.`assessment_id` = `p`.`assessment_id`
                WHERE `v_f`.`folder_id` IN {$placeholders}
            ";

            $idStatement = $this->getDatabase()->prepare($idQuery);
            $idStatement->execute($params);
            $idList = array_column($idStatement->fetchAll(), 'id');
        }

        // Send IDs to findByColumnList
        $ids = array_merge($idList, $uniqueIds);
        if (0 === \count($ids)) {
            return $this->createCollection();
        }

        return $this->findByPredicates($builder->in('id', $ids), $options);
    }


    /**
     * Returns assessments that:
     * * are in the specified folders, or unitImpl/project belonging to the specified folders
     * * if the student is in a plan with custom dates, date clauses are applied to the plan dates
     *
     * @param int[]   $folderIds
     * @param User    $participantUser
     * @param Builder $builder    A PredicateBuilder which contains columns to query on.
     * @param array   $includeIds IDs of any assessables that should also be included
     * @param array   $options    Any other options to pass to the final search to `findByColumnList`.
     *
     * @return GenericCollection|Model[]
     * @throws BaseMapper\DefinitionException
     * @throws InvalidColumnException If Predicate builder caller with invalid column name
     */
    public function findForFolderAndParticipant(
        array $folderIds,
        User $participantUser,
        Builder $builder,
        array $includeIds = [],
        array $options = []
    ): GenericCollection {
        if (\count($folderIds) === 0) {
            return $this->createCollection();
        }

        $participantUserId = $participantUser->getId();
        $params            = array_merge(
            Builder::getArrayParameters($folderIds, 'folder'),
            [
                'user_1' => $participantUserId,
                'user_2' => $participantUserId,
                'user_3' => $participantUserId,
                'user_4' => $participantUserId,
            ]
        );
        $placeholders = Builder::getArrayPlaceholders($folderIds, 'folder');

        // Get assessments the student is participating in
        $idQuery = "
            SELECT assessment.id
            FROM `folder` f
            JOIN `assessment_folder` AS `v_f`  ON `f`.`id` = `v_f`.`folder_id`
            JOIN `assessment` ON `assessment`.`id` = `v_f`.`assessment_id`
            LEFT JOIN participant_plan pp ON assessment.id = pp.assessment_id
            LEFT JOIN participant pt ON pp.id = pt.participant_plan_id AND pt.user_id = :user_1
            WHERE f.id IN {$placeholders}

            UNION

            SELECT assessment.id
            FROM `folder` uicf
            JOIN `course_implementation` AS `uic` ON `uicf`.`id` = `uic`.`folder_id` AND `uic`.`deleted_at` IS NULL
            JOIN `course_unit_implementation` AS `ui` ON `uic`.`id` = `ui`.`course_implementation_id` AND `ui`.`deleted_at` IS NULL
            JOIN `assessment_course_unit_implementation` AS `v_ui` ON `ui`.`id` = `v_ui`.`course_unit_implementation_id`
            JOIN `assessment` ON `assessment`.`id` = `v_ui`.`assessment_id`
            LEFT JOIN participant_plan pp ON assessment.id = pp.assessment_id
            LEFT JOIN participant pt ON pp.id = pt.participant_plan_id AND pt.user_id = :user_2
            WHERE uicf.id IN {$placeholders}

            UNION

            SELECT `p_a`.`assessment_id`
            FROM project_assessment `p_a`
            JOIN `project` AS `p` ON `p`.`id` = `p_a`.`project_id`
            JOIN assessment ON assessment.id = p.assessment_id
            JOIN assessment_course_unit_implementation AS `v_ui` ON `v_ui`.`assessment_id` = `assessment`.id
            JOIN `course_unit_implementation` AS `ui` ON `ui`.`id` = `v_ui`.`course_unit_implementation_id`
            JOIN course_implementation AS `uic` ON `uic`.`id` = `ui`.course_implementation_id
            LEFT JOIN participant_plan pp ON `p_a`.`assessment_id` = pp.assessment_id
            LEFT JOIN participant pt ON pp.id = pt.participant_plan_id AND pt.user_id = :user_3
            WHERE `uic`.`folder_id` IN {$placeholders}

            UNION

            SELECT `p_a`.`assessment_id`
            FROM project_assessment `p_a`
            JOIN `project` AS `p` ON `p`.`id` = `p_a`.`project_id`
            JOIN assessment ON assessment.id = p.assessment_id
            JOIN assessment_folder AS `v_f`ON `v_f`.`assessment_id` = `assessment`.`id`
            LEFT JOIN participant_plan pp ON `p_a`.`assessment_id` = pp.assessment_id
            LEFT JOIN participant pt ON pp.id = pt.participant_plan_id AND pt.user_id = :user_4
            WHERE `v_f`.`folder_id` IN {$placeholders}
        ";

        $idStatement = $this->getDatabase()->prepare($idQuery);
        $idStatement->execute($params);
        $idList = array_merge(array_column($idStatement->fetchAll(), 'id'), $includeIds);

        if (0 === \count($idList)) {
            return $this->createCollection();
        }
        try {
            $builder->in('id', $idList);
        } catch (UnderflowException $e) {
            // Should be impossible to trigger as we have checked for empty array. But handle here to simplify calling code.
            return $this->createCollection();
        }

        return $this->findByPredicatesForParticipant($builder, $participantUser, $options);
    }

    /**
     * Retrieves a list of assessments, along with their joined objects.
     *
     * @param PDOStatement $statement
     * @return GenericCollection<Model>
     * @throws BaseMapper\DefinitionException If join name is not on this mapper
     * @throws BaseMapper\Exception  If join $name isn't on this mapper
     */
    protected function hydrateSelect(PDOStatement $statement)
    {
        return $this->hydrateSelectRecursive($statement);
    }

    /**
     * @param PDOStatement $statement
     * @param bool         $hydrateProjectContext
     * @return GenericCollection<Model>
     * @throws BaseMapper\DefinitionException
     * @throws BaseMapper\Exception
     */
    private function hydrateSelectRecursive(PDOStatement $statement, bool $hydrateProjectContext = true)
    {
        /** @var GenericCollection<Model> $modelList */
        $modelList         = $this->createCollection();
        $dueWorkIds        = [];
        $projectIds        = [];
        $taskIds           = [];
        $unitSpecIds       = [];
        $unitImplIds       = [];
        $contextProjectIds = [];
        $lessonPlanIds     = [];

        /** @noinspection PhpAssignmentInConditionInspection */
        while ($row = $statement->fetch(PDO::FETCH_ASSOC)) {
            $model = $this->hydrate($row);

            $model->setWorkType($this->hydrateJoin($row, 'work_type', 'wt'));

            // Task?
            if ($row['t.id']) {
                $model->setTask($this->hydrateJoin($row, 'task', 't'));

                $taskIds[] = $row['t.id'];
            } else {
                $model->setTask(null);
            }
            // Due work?
            if ($row['d.id']) {
                $model->setDueWork($this->hydrateJoin($row, 'duework', 'd'));
                $dueWorkIds[] = $row['d.id'];
            } else {
                $model->setDueWork(null);
            }
            // Quiz?
            if ($row['q.assess_id']) {
                $model->setQuiz($this->hydrateJoin($row, 'quiz', 'q'));
            } else {
                $model->setQuiz(null);
            }
            // LTI assessment?
            if ($row['l.id']) {
                $model->setLtiAssessment($this->hydrateJoin($row, 'ltiAssessment', 'l'));
            } else {
                $model->setLtiAssessment(null);
            }
            // Lesson Plan?
            if ($row['lessonPlan.id']) {
                $lessonPlanIds[] = $row['lessonPlan.id'];

                $lessonPlan = $this->hydrateJoin($row, 'lessonPlan', 'lessonPlan');

                $model->setLessonPlan($lessonPlan);
            } else {
                $model->setLessonPlan(null);
            }

            /* Dialog Agents
             */
            if ($row['dialogAgent.id']) {
                $dialogAgent = $this->hydrateJoin($row, 'dialogAgent', 'dialogAgent');

                $model->setDialogAgent($dialogAgent);
            } else {
                $model->setDialogAgent(null);
            }

            /* Reporting
             */
            if ($row['reporting.id']) {
                $reporting = $this->hydrateJoin($row, 'reporting', 'reporting');

                $model->setReporting($reporting);

                /* We need this to add joined relationships to each Reporting activity in the set in a convenient manner
                 * that doesn't result in millions of rows LEFT JOINed onto everything
                 */
                $reportingIds[] = $row['reporting.id'];
            } else {
                $model->setReporting(null);
            }

            // Project?
            if ($row['p.id']) {
                $projectIds[$row['p.id']] = true;
                $model->setProject($this->hydrateJoin($row, 'project', 'p'));
            } else {
                $model->setProject(null);
            }
            // Specification
            if ($row['spec.id']) {
                $model->setSpecificationAssessment($this->hydrateJoin($row, 'assessment_specification_implementation', 'spec'));
            } else {
                $model->setSpecificationAssessment(null);
            }
            // Context (whatever it might be)
            $context = null;
            if ($row['f.id']) {
                // Folder
                $context = $this->hydrateJoin($row, 'assessment_folder', 'f');
            } elseif ($row['us.id']) {
                // Unit spec: deeper joins will be hydrated later
                /** @var UnitSpec\Model $unitSpec */
                $unitSpec      = $this->hydrateJoin($row, 'assessment_course_unit_specification', 'us');
                $unitSpecIds[] = $row['us.id'];
                $context       = $unitSpec;
            } elseif ($row['ui.id']) {
                // Unit impl: deeper joins will be hydrated later
                /** @var UnitImpl\Model $unitImpl */
                $unitImpl      = $this->hydrateJoin($row, 'assessment_course_unit_implementation', 'ui');
                $unitImplIds[] = $row['ui.id'];
                $context       = $unitImpl;
            } elseif ($row['pa.id']) {
                // Project: this project's assessments will be hydrated later
                $context             = $this->hydrateJoin($row, 'project_assessment', 'pa');
                $contextProjectIds[] = $row['pa.assessment_id'];
            }

            $model->setAssessmentContext($context);

            // Set assessment model.
            // The null-safe operator '?->' has been used below to protect against the following case:
            // If a previous assessment import has failed mid-way through, so that an assessment row has been created,
            // but the linked row for the sub-object (e.g. task row) has not been created, $model->getSubObject()
            // will cause an error that prevents the page from loading.  Using the null-safe operator allows the page
            // to load, which allows the user to re-do the import, which will fix the problem.
            // Note imports should no longer fail mid-way through due to database transactions being added in SA-19231.
            $model->getSubObject()?->setAssessmentModel($model);

            $modelList->add($model);
        }

        // Augment all activity sub-objects (due work, task, quiz, etc.),
        // only if their entire object structure hasn't already been hydrated:
        // this excludes tasks, lesson plans, dialog agents and projects.
        $modelList = $this->augmentListWithDueWorks($modelList);
        $modelList = $this->augmentListWithQuizzes($modelList);
        $modelList = $this->augmentListWithLtiAssessments($modelList);

        // Load related 1-M models
        // Due work files and Kami worksheet
        $modelList = $this->augmentListWithDueWorkFiles($modelList, $dueWorkIds);
        $modelList = $this->augmentListWithDueWorkKamiWorksheetTemplates($modelList, $dueWorkIds);

        /* Task files
         */
        $modelList = $this->augmentListWithTaskFiles($modelList, $taskIds);

        /* Augment with Lesson Plan Files and Lesson Plan Fields
         */
        $modelList = $this->augmentListWithLessonPlanFiles($modelList, $lessonPlanIds);
        $modelList = $this->augmentWithLessonPlanFields($modelList, $lessonPlanIds);

        /* Augment with Reporting Activities and their Supporting Data
         */
        $modelList = $this->augmentListWithReporting($modelList);
        $modelList = $this->augmentListWithReportingSupportingDataCurriculumNodes($modelList, $reportingIds ?? []);
        $modelList = $this->augmentListWithReportingSupportingDataWorkTypes($modelList, $reportingIds ?? []);

        // Augment all contexts
        $modelList = $this->augmentListWithUnitSpecContexts($modelList, $unitSpecIds);
        $modelList = $this->augmentListWithUnitImplContexts($modelList, $unitImplIds);

        if ($hydrateProjectContext) {
            // Infinite-cycle breakout.
            $modelList = $this->augmentListWithProjectContexts($modelList, $contextProjectIds);
        }

        // Folder codes for all contexts
        $modelList = $this->augmentListWithFolderCodes($modelList);
        // Specification assessments
        $modelList = $this->augmentListWithSpecifications($modelList);
        // Grade groups (note this requires subobjects to be hydrated FIRST
        $modelList = $this->augmentListWithGradeGroups($modelList);
        // Curriculum Nodes
        $modelList = $this->augmentListWithCurriculumNodes($modelList);
        // Project sub-assessments
        if ($this->hydrateProjectSubAssessments) {
            $modelList = $this->augmentListWithProjectSubAssessments($modelList, array_keys($projectIds));
        }
        // Lesson Plan Linked Assessments
        $modelList = $this->augmentWithLessonPlanAssessments($modelList, $lessonPlanIds);

        return $modelList;
    }

    /**
     * For any assessments in `$modelList`: fetch and add their corresponding
     * task, if there are any.
     *
     * @param GenericCollection<Model> $modelList
     * @return GenericCollection<Model>
     */
    private function augmentListWithTasks(GenericCollection $modelList): GenericCollection
    {
        // Would just use `findByColumn` here, but the joins it includes may
        // vary too greatly from what's in this mapper.
        /** @var TaskMapper $mapper */
        $mapper = $this->getJoinMapper('task');
        return $this->augmentListWithSubObjects(
            $modelList,
            $mapper,
            "
                SELECT {$mapper->getColumnString()}
                FROM {$mapper->getDatabase()->getQuoter()->quoteIdentifier($mapper->getTable())}
            ",
            fn ($row) => $mapper->hydrate($row),
        );
    }

    /**
     * For any assessments in `$modelList`: fetch and add their corresponding
     * due work, if there are any.
     *
     * @param GenericCollection<Model> $modelList
     * @return GenericCollection<Model>
     */
    private function augmentListWithDueWorks(GenericCollection $modelList): GenericCollection
    {
        /** @var SubmissionBoxMapper $mapper */
        $mapper = $this->getJoinMapper('duework');
        return $this->augmentListWithSubObjects(
            $modelList,
            $mapper,
            "
                SELECT
                    {$mapper->getColumnString()},
                    {$mapper->getJoinColumnString('mark_type', 'mt')},
                    {$mapper->getJoinColumnString('rubric_capability_mark_type_id', 'rmt')},
                    {$mapper->getJoinColumnString('rubric_capability_grade_group_id', 'rcgg')},
                    {$mapper->getJoinColumnString('worksheetTemplate', 'dueworkWorksheetTemplate')},
                    {$mapper->getJoinColumnString('worksheetTemplate.allowedFileExtension', 'dueworkWorksheetTemplateAllowedFileExtension')}
                FROM {$mapper->getDatabase()->getQuoter()->quoteIdentifier($mapper->getTable())}
                LEFT JOIN {$mapper->getJoinSql('worksheetTemplate', '', 'dueworkWorksheetTemplate', true)}
                LEFT JOIN {$mapper->getJoinSql('worksheetTemplate.allowedFileExtension', 'dueworkWorksheetTemplate', 'dueworkWorksheetTemplateAllowedFileExtension', true)}
                LEFT JOIN {$mapper->getJoinSql('mark_type', '', 'mt')}
                LEFT JOIN {$mapper->getJoinSql('rubric_capability_mark_type_id', '', 'rmt')}
                LEFT JOIN {$mapper->getJoinSql('rubric_capability_grade_group_id', '', 'rcgg', true)}
            ",
            $this->hydrateDueWork(...),
        );
    }

    /**
     * Hydrate method for `augmentListWithDueWorks`.
     *
     * @param array $row
     * @return SubmissionBox
     */
    private function hydrateDueWork(array $row): SubmissionBox
    {
        /** @var SubmissionBoxMapper $mapper */
        $mapper = $this->getJoinMapper('duework');
        return $mapper->hydrate($row)
            ->setMarkType($mapper->hydrateJoin($row, 'mark_type', 'mt'))
            ->setCapabilityMarkType($mapper->hydrateJoin($row, 'rubric_capability_mark_type_id', 'rmt'))
            ->setCapabilityGradeGroup($mapper->hydrateJoin($row, 'rubric_capability_grade_group_id', 'rcgg'))
            ->setWorksheetTemplate(
                $mapper->hydrateJoin($row, 'worksheetTemplate', 'dueworkWorksheetTemplate')
                    ?->setMimeType(
                        $mapper->hydrateJoin(
                            $row,
                            'worksheetTemplate.allowedFileExtension',
                            'dueworkWorksheetTemplateAllowedFileExtension',
                        )
                    )
            );
    }

    /**
     * For any assessments in `$modelList`: fetch and add their corresponding
     * quiz, if there are any.
     *
     * @param GenericCollection<Model> $modelList
     * @return GenericCollection<Model>
     */
    private function augmentListWithQuizzes(GenericCollection $modelList): GenericCollection
    {
        /** @var Quiz\Mapper $mapper */
        $mapper = $this->getJoinMapper('quiz');
        return $this->augmentListWithSubObjects(
            $modelList,
            $mapper,
            "
                SELECT
                    {$mapper->getColumnString()},
                    {$mapper->getJoinColumnString('mark_type_id', 'qmt')}
                FROM {$mapper->getDatabase()->getQuoter()->quoteIdentifier($mapper->getTable())}
                LEFT JOIN {$mapper->getJoinSql('mark_type_id', '', 'qmt')}
            ",
            $this->hydrateQuiz(...),
        );
    }

    /**
     * Hydrate method for `augmentListWithQuizzes`.
     *
     * @param array $row
     * @return Quiz\Model
     * @throws Exception
     */
    private function hydrateQuiz(array $row): Quiz\Model
    {
        /** @var Quiz\Mapper $mapper */
        $mapper = $this->getJoinMapper('quiz');
        return $mapper->hydrate($row)
            ->setMarkType($mapper->hydrateJoin($row, 'mark_type_id', 'qmt'));
    }


    /**
     * For any assessments in `$modelList`: fetch and add their corresponding
     * LTI assessment, if there are any.
     *
     * @param GenericCollection<Model> $modelList
     * @return GenericCollection<Model>
     */
    private function augmentListWithLtiAssessments(GenericCollection $modelList): GenericCollection
    {
        /** @var LtiAssessment\Mapper $mapper */
        $mapper = $this->getJoinMapper('ltiAssessment');
        return $this->augmentListWithSubObjects(
            $modelList,
            $mapper,
            "
                SELECT
                    {$mapper->getColumnString()},
                    {$mapper->getJoinColumnString('ltiTool', 'ltool')}
                FROM {$mapper->getDatabase()->getQuoter()->quoteIdentifier($mapper->getTable())}
                LEFT JOIN {$mapper->getJoinSql('ltiTool', '', 'ltool', true)}
            ",
            $this->hydrateLtiAssessment(...),
        );
    }

    /**
     * Hydrate method for `augmentListWithLtiAssessments`.
     *
     * @param array $row
     * @return LtiAssessment\Model
     */
    private function hydrateLtiAssessment(array $row): LtiAssessment\Model
    {
        /** @var LtiAssessment\Mapper $mapper */
        $mapper = $this->getJoinMapper('ltiAssessment');
        return $mapper->hydrate($row)
            ->setLtiTool($mapper->hydrateJoin($row, 'ltiTool', 'ltool'));
    }

    /**
     * For any assessments in `$modelList`: fetch and add their corresponding
     * lesson plan, if there are any.
     *
     * @param GenericCollection<Model> $modelList
     * @return GenericCollection<Model>
     */
    private function augmentListWithLessonPlans(GenericCollection $modelList): GenericCollection
    {
        /** @var LessonPlan\Mapper $mapper */
        $mapper = $this->getJoinMapper('lessonPlan');
        return $this->augmentListWithSubObjects(
            $modelList,
            $mapper,
            "
                SELECT {$mapper->getColumnString()}
                FROM {$mapper->getDatabase()->getQuoter()->quoteIdentifier($mapper->getTable())}
            ",
            fn ($row) => $mapper->hydrate($row),
        );
    }


    /**
     * For any assessments in `$modelList`: fetch and add their corresponding
     * Dialog Agent, if there are any.
     *
     * @param GenericCollection<Model> $modelList
     * @return GenericCollection<Model>
     */
    private function augmentListWithDialogAgents(GenericCollection $modelList): GenericCollection
    {
        /** @var DialogAgent\Mapper $mapper */
        $mapper = $this->getJoinMapper('dialogAgent');

        return $this->augmentListWithSubObjects(
            $modelList,
            $mapper,
            "
                SELECT {$mapper->getColumnString()}
                FROM {$mapper->getDatabase()->getQuoter()->quoteIdentifier($mapper->getTable())}
            ",
            fn ($row) => $mapper->hydrate($row),
        );
    }


    /**
     * For any assessments in `$modelList`: fetch and add their corresponding
     * Reporting, if there are any.
     *
     * @param GenericCollection<Model> $modelList
     * @return GenericCollection<Model>
     */
    private function augmentListWithReporting(GenericCollection $modelList): GenericCollection
    {
        /** @var Reporting\Mapper $mapper */
        $mapper = $this->getJoinMapper('reporting');

        return $this->augmentListWithSubObjects(
            $modelList,
            $mapper,
            "
                SELECT {$mapper->getColumnString()}
                FROM {$mapper->getDatabase()->getQuoter()->quoteIdentifier($mapper->getTable())}
            ",
            fn ($row) => $mapper->hydrate($row),
        );
    }


    /**
     * Hydrates Curriculum Nodes for each reporting activity's Supporting Data
     *
     * @param GenericCollection $modelList
     * @param array $reportingIds
     * @return GenericCollection
     * @throws Exception
     */
    private function augmentListWithReportingSupportingDataCurriculumNodes(GenericCollection $modelList, array $reportingIds): GenericCollection
    {
        /* No reporting activities? No trabajo
         * Exit early
         */
        if (!count($reportingIds)) {
            return $modelList;
        }

        /* Manually obtain supporting data curriculum nodes
         */
        $placeholders = Builder::getArrayPlaceholders($reportingIds, 'reportingIds');
        $parameters = Builder::getArrayParameters($reportingIds, 'reportingIds');

        /* $this->>getTable() is the assessment table
         */
        $query = "
            SELECT 
                rcn.reporting_id AS reporting_id,
                cn.*
            FROM reporting_curriculum_node rcn
            INNER JOIN curriculum_node cn ON rcn.curriculum_node_id = cn.id
            WHERE rcn.reporting_id IN {$placeholders}
        ";

        $statement = $this->getDatabase()->prepare($query);
        $statement->execute($parameters);

        /* This is a bit awkward since supporting data curric nodes are using a relationship table that doesn't actually
         * have a join name, nor does it have its own mapper, and it's not shared among all activities...which means I
         * kind of have to do it like this, afaict
         */
        $curriculumNodeMapper = CurriculumNodeMapper::getInstance();

        $curriculumNodes = [];

        foreach ($statement as $row) {
            $reportingId = $row['reporting_id'];

            if (!isset($curriculumNodes[$reportingId])) {
                $curriculumNodes[$reportingId] = $curriculumNodeMapper->createCollection();
            }

            $curriculumNodes[$reportingId]->add($curriculumNodeMapper->hydrate($row));
        }

        /* Assign them to each reporting subobject in the model list, replacing whatever was there before
         * This is pretty unique: nothing really exists like this outside of Reporting.
         */
        /** @var \Schoolbox\Learning\Assessment\DataModel\Model $model */
        foreach ($modelList as $model) {
            /* $modelList might contain non-reporting items, such as for the Pending Assessments screen: we don't care
             * about those ones and they won't return a meaningful value from getReporting() obviously
             */
            if (!$model->getSubObject() instanceof \Schoolbox\Learning\Assessment\DataModel\Reporting\Model) {
                continue;
            }

            /* Assign new nodes or an empty collection if you tried to clear them all
             */
            $model->getReporting()->setSupportingDataCurriculumNodes($curriculumNodes[$model->getReporting()->getId()] ?? $curriculumNodeMapper->createCollection());
        }

        return $modelList;
    }



    /**
     * Hydrates Work Types for each reporting activity's Supporting Data
     *
     * @param GenericCollection $modelList
     * @param array $reportingIds
     * @return GenericCollection
     * @throws Exception
     */
    private function augmentListWithReportingSupportingDataWorkTypes(GenericCollection $modelList, array $reportingIds): GenericCollection
    {
        /* No reporting activities? No trabajo
         * Exit early
         */
        if (!count($reportingIds)) {
            return $modelList;
        }

        /* Manually obtain supporting data curriculum nodes
         */
        $placeholders = Builder::getArrayPlaceholders($reportingIds, 'reportingIds');
        $parameters = Builder::getArrayParameters($reportingIds, 'reportingIds');

        /* $this->>getTable() is the assessment table
         */
        $query = "
            SELECT 
                rwt.reporting_id AS reporting_id,
                lwt.*
            FROM reporting_work_type rwt
            INNER JOIN lu_work_type lwt ON rwt.work_type_id = lwt.id
            WHERE rwt.reporting_id IN {$placeholders}
        ";

        $statement = $this->getDatabase()->prepare($query);
        $statement->execute($parameters);

        /* This is a bit awkward since supporting data work types are using a relationship table that doesn't actually
         * have a join name, nor does it have its own mapper, and it's not shared among all activities...which means I
         * kind of have to do it like this, afaict
         */
        $workTypeMapper = WorkTypeMapper::getInstance();

        $workTypes = [];

        foreach ($statement as $row) {
            $reportingId = $row['reporting_id'];

            if (!isset($workTypes[$reportingId])) {
                $workTypes[$reportingId] = $workTypeMapper->createCollection();
            }

            $workTypes[$reportingId]->add($workTypeMapper->hydrate($row));
        }

        /* Assign them to each reporting subobject in the model list, replacing whatever was there before
         * This is pretty unique: nothing really exists like this outside of Reporting.
         */
        /** @var \Schoolbox\Learning\Assessment\DataModel\Model $model */
        foreach ($modelList as $model) {
            /* $modelList might contain non-reporting items, such as for the Pending Assessments screen: we don't care
             * about those ones and they won't return a meaningful value from getReporting() obviously
             */
            if (!$model->getSubObject() instanceof \Schoolbox\Learning\Assessment\DataModel\Reporting\Model) {
                continue;
            }

            $model->getReporting()->setSupportingDataWorkTypes($workTypes[$model->getReporting()->getId()] ?? $workTypeMapper->createCollection());
        }

        return $modelList;
    }


    /**
     * For any assessments in `$modelList`: fetch and add their corresponding
     * project, if there are any.
     *
     * @param GenericCollection<Model> $modelList
     * @return GenericCollection<Model>
     */
    private function augmentListWithProjects(GenericCollection $modelList): GenericCollection
    {
        /** @var Project\Mapper $mapper */
        $mapper = $this->getJoinMapper('project');
        return $this->augmentListWithSubObjects(
            $modelList,
            $mapper,
            "
                SELECT {$mapper->getColumnString()}
                FROM {$mapper->getDatabase()->getQuoter()->quoteIdentifier($mapper->getTable())}
            ",
            fn ($row) => $mapper->hydrate($row),
        );
    }

    /**
     * For any assessments in `$modelList`: fetch and add their corresponding
     * sub-object (e.g. a due work, task, etc.).
     *
     * @param GenericCollection<Model>              $modelList
     * @param BaseMapper                            $subMapper
     * @param string                                $baseQuery
     * @param callable(array): IAssessableDataModel $hydrator
     * @return GenericCollection<Model>
     */
    private function augmentListWithSubObjects(
        GenericCollection $modelList,
        BaseMapper $subMapper,
        string $baseQuery,
        callable $hydrator,
    ): GenericCollection {
        // No models to augment: exit early
        if (count($modelList) === 0) {
            return $modelList;
        }

        // Would just use `findByColumn` here, but the joins it includes may
        // vary too greatly from what's in this mapper.
        // For now: assume that relation field is always `assessment_id`.
        $pb     = $subMapper->getPredicateBuilder()->in('assessment_id', $modelList->getPropertyValues('id'));
        $result = $subMapper->getDatabase()->executeQuery(
            $baseQuery . ' WHERE ' . $pb->getWhereIntersect(),
            $pb->getParams(),
        );

        $subModelList = $subMapper->createCollection();
        foreach ($result as $row) {
            $subModel = call_user_func($hydrator, $row);
            $subModelList[$subModel->getAssessmentModel()->getId()] = $subModel;
        }

        foreach ($modelList as $model) {
            $assessmentId = $model->getId();
            if (isset($subModelList[$assessmentId])) {
                $model->setSubObject($subModelList[$assessmentId]);
            }
        }

        return $modelList;
    }


    /**
     * For any projects found in $modelList, identified by $projectIds, find their sub-assessments
     * and load them into the projects.
     *
     * @param GenericCollection<Model> $modelList
     * @param array<int>               $projectIds
     * @return GenericCollection<Model>
     * @throws BaseMapper\DefinitionException
     * @throws BaseMapper\Exception
     * @throws UnderflowException
     */
    private function augmentListWithProjectSubAssessments(
        GenericCollection $modelList,
        array $projectIds,
    ): GenericCollection {
        // Project sub-assessments
        if (!\count($projectIds)) {
            return $modelList;
        }

        $statement = $this->prepareSelect(
            $this->getSelectString(),
            $this->getFromString(),
            new Builder($this->getDatabase()->getQuoter(), $this->getTable()),
            ['v_pa.project_id' => $projectIds],
            ['order' => ['sequence' => 'asc']]
        );
        $statement->execute();

        /** @var Project\Mapper $projectMapper */
        $projectMapper = $this->getJoinMapper('project');

        // $projects stores references to each $modelList item's project.
        // When a value in $projects gets updated (happens in last foreach):
        // the corresponding $modelList item's project gets updated.
        $projects = $projectMapper->createCollection();
        foreach ($modelList as $model) {
            $project = $model->getProject();
            if ($project) {
                if (!$project->getAssessments()) {
                    $project->setAssessments($this->createCollection());
                }
                $projects[$project->getId()] = $project;
            }
        }

        foreach ($this->hydrateSelectRecursive($statement, false) as $subAssessment) {
            $projectId                                                       = $subAssessment->getContextProject()->getId();
            $projects[$projectId]->getAssessments()[$subAssessment->getId()] = $subAssessment;
        }
        return $modelList;
    }


    /**
     * Augments the list from hydrateSelect() with Curriculum Nodes
     *
     * @param GenericCollection<Model> $modelList
     * @return GenericCollection<Model>
     * @throws BaseMapper\DefinitionException If join name is not on this mapper
     * @throws InvalidColumnException
     * @throws UnderflowException
     */
    protected function augmentListWithCurriculumNodes(GenericCollection $modelList): GenericCollection
    {
        if (count($modelList) === 0) {
            return $modelList;
        }

        // Fetch all curriculum nodes + supplementary fields
        $predicate = $this->getPredicateBuilder()
                          ->in('id', $modelList->getPropertyValues('id'))
                          ->notNull('cn.id');
        $where     = implode(' AND ', $predicate->getWhere());
        $query     = "
            SELECT
                {$this->getTable()}.id,
                {$this->getJoinColumnString('curriculum_node_for_assessment', 'cn')}
            FROM {$this->getTable()}
            INNER JOIN {$this->getJoinSql('curriculum_node_for_assessment', '', 'cn')}
            WHERE {$where}
        ";
        $statement = $this->getDatabase()->prepare($query);
        $statement->execute($predicate->getParams());

        // Group nodes by their assessment
        /** @var CurriculumNodeMapper $curriculumNodeMapper */
        $curriculumNodeMapper = $this->getJoinMapper('curriculum_node_for_assessment');
        $curriculumNodes      = [];
        foreach ($statement as $row) {
            $assessmentId = $row['id'];
            if (!isset($curriculumNodes[$assessmentId])) {
                $curriculumNodes[$assessmentId] = $curriculumNodeMapper->createCollection();
            }
            $curriculumNodes[$assessmentId]->add(
                $curriculumNodeMapper->hydrate($row, 'cn')
            );
        }

        // Set each assessment's curriculum nodes
        foreach ($modelList as $model) {
            if (isset($curriculumNodes[$model->getId()])) {
                $model->setCurriculumNodes($curriculumNodes[$model->getId()]);
            } else {
                $model->setCurriculumNodes($curriculumNodeMapper->createCollection());
            }
        }
        return $modelList;
    }

    /**
     * Augments the list from hydrateSelect() with due work files.
     *
     * @param GenericCollection<Model> $modelList
     * @param array<int>               $dueWorkIds
     * @return GenericCollection<Model>
     * @throws BaseMapper\DefinitionException If join name is not on this mapper
     * @throws BaseMapper\Exception  If join $name isn't on this mapper
     */
    protected function augmentListWithDueWorkFiles(GenericCollection $modelList, array $dueWorkIds): GenericCollection
    {
        if (!empty($dueWorkIds)) {
            // Retrieve and sort list of duework files
            $files = $this->getJoinMapper('duework.files')
                ->findByColumn('box_id', $dueWorkIds)
                ->groupByCallback(function (SubmissionBox\SingleFile\Model $file) {
                    return $file->getBox()->getId();
                });

            // Augment list with due work files
            foreach ($modelList as $model) {
                $submodel = $model->getSubObject();
                if ($submodel instanceof SubmissionBox) {
                    if (!empty($files[$submodel->getId()])) {
                        $submodel->setAttachments($files[$submodel->getId()]);
                    } else {
                        $submodel->setAttachments($this->getJoinMapper('duework.files')->createCollection());
                    }
                }
            }
        }
        return $modelList;
    }


    /**
     * Augments the list from hydrateSelect() with due work Kami worksheet
     * templates.
     *
     * @param GenericCollection<Model> $modelList
     * @param array<int>               $dueWorkIds
     * @return GenericCollection<Model>
     */
    protected function augmentListWithDueWorkKamiWorksheetTemplates(
        GenericCollection $modelList,
        array $dueWorkIds,
    ): GenericCollection {
        if (!empty($dueWorkIds)) {
            // Retrieve and sort list of Kami docs
            $kamiDocuments = $this->getJoinMapper('duework.kamiWorksheetTemplate')
                ->findByColumn('worksheet_template_submission_box_id', $dueWorkIds)
                ->indexByCallback(fn (KamiModel $kamiDocument) => $kamiDocument->getWorksheetTemplateSubmissionBox()->getId())
            ;

            // Augment list with Kami docs
            foreach ($modelList as $model) {
                $submodel = $model->getSubObject();
                if ($submodel instanceof SubmissionBox) {
                    $submodel->setKamiWorksheetTemplate($kamiDocuments[$submodel->getId()] ?? null);
                }
            }
        }
        return $modelList;
    }

    /**
     * Augments the list from hydrateSelect() with task files.
     *
     * @param GenericCollection<Model> $modelList
     * @param array<int>               $taskIds
     * @return GenericCollection<Model>
     * @throws BaseMapper\DefinitionException If join name is not on this mapper
     * @throws BaseMapper\Exception  If join $name isn't on this mapper
     */
    protected function augmentListWithTaskFiles(GenericCollection $modelList, array $taskIds): GenericCollection
    {
        if (!empty($taskIds)) {
            // Retrieve and sort list of duework files
            $files = $this->getJoinMapper('task.files')
                          ->findByColumn('task_id', $taskIds)
                          ->groupByCallback(function (Task\File\Model $file) {
                              return $file->getTask()->getId();
                          });

            // Augment list with due work files
            foreach ($modelList as $model) {
                $submodel = $model->getSubObject();

                if ($submodel instanceof \Schoolbox\Assessment\Course\Task) {
                    if (!empty($files[$submodel->getId()])) {
                        $submodel->setAttachments($files[$submodel->getId()]);
                    } else {
                        $submodel->setAttachments($this->getJoinMapper('task.files')->createCollection());
                    }
                }
            }
        }
        return $modelList;
    }


    /**
     * Augments the list from hydrateSelect() with Lesson Plan Files.
     *
     * @param GenericCollection<Model> $modelList
     * @param array<int>               $lessonPlanIds
     * @return GenericCollection<Model>
     * @throws BaseMapper\DefinitionException If join name is not on this mapper
     * @throws BaseMapper\Exception  If join $name isn't on this mapper
     */
    protected function augmentListWithLessonPlanFiles(
        GenericCollection $modelList,
        array $lessonPlanIds,
    ): GenericCollection {
        if (!empty($lessonPlanIds)) {
            /* Retrieve and sort list of Lesson Plan Files
             */
            $files = $this->getJoinMapper('lessonPlan.files')
                ->findByColumn('lesson_plan_id', $lessonPlanIds)
                ->groupByCallback(function (LessonPlan\File\Model $file) {
                    return $file->getLessonPlan()->getId();
                });

            // Augment list with due work files
            foreach ($modelList as $model) {
                $submodel = $model->getSubObject();

                if ($submodel instanceof LessonPlan\Model) {
                    if (!empty($files[$submodel->getId()])) {
                        $submodel->setAttachments($files[$submodel->getId()]);
                    } else {
                        $submodel->setAttachments($this->getJoinMapper('lessonPlan.files')->createCollection());
                    }
                }
            }
        }
        return $modelList;
    }

    /**
     * Augments the list from hydrateSelect() with context unit specs.
     *
     * @param GenericCollection<Model> $modelList
     * @param array<int>               $unitSpecIds
     * @return GenericCollection<Model>
     */
    private function augmentListWithUnitSpecContexts(
        GenericCollection $modelList,
        array $unitSpecIds,
    ): GenericCollection {
        if (!empty($unitSpecIds)) {
            /** @var UnitSpec\Mapper $contextUnitSpecMapper */
            $contextUnitSpecMapper = $this->getJoinMapper('assessment_course_unit_specification');
            $contextUnitSpecs      = $contextUnitSpecMapper->findByColumn(
                'id',
                $unitSpecIds,
                ['deleted' => true],
            )->indexBy('id');

            foreach ($modelList as $model) {
                $context = $model->getAssessmentContext();
                if ($context instanceof UnitSpec\Model) {
                    $hydratedContext = $contextUnitSpecs[$context->getId()] ?? null;
                    if ($hydratedContext instanceof UnitSpec\Model) {
                        $model->setAssessmentContext($hydratedContext);
                    }
                }
            }
        }
        return $modelList;
    }

    /**
     * Augments the list from hydrateSelect() with context unit implementations.
     *
     * @param GenericCollection<Model> $modelList
     * @param array<int>               $unitImplIds
     * @return GenericCollection<Model>
     * @throws Deleted
     * @throws Exception
     */
    private function augmentListWithUnitImplContexts(
        GenericCollection $modelList,
        array $unitImplIds,
    ): GenericCollection {
        if (!empty($unitImplIds)) {
            /** @var UnitImpl\Mapper $contextUnitImplMapper */
            $contextUnitImplMapper = $this->getJoinMapper('assessment_course_unit_implementation');
            $contextUnitImpls      = $contextUnitImplMapper->findByColumn(
                'id',
                $unitImplIds,
                ['deleted' => true],
            )->indexBy('id');

            /** @var CourseImpl\Mapper  $contextCourseImplMapper */
            $contextCourseImplMapper = $this->getJoinMapper('assessment_course_unit_implementation.course');
            $courseImplIds = [];
            foreach ($contextUnitImpls as $unitImpl) {
                $courseImplIds[] = $unitImpl->getCourse()->getId();
            }

            $courseImpls = !empty($courseImplIds)
                ? $contextCourseImplMapper->findAllByIds($courseImplIds)->indexBy('id')
                : [];

            foreach ($modelList as $model) {
                $context = $model->getAssessmentContext();
                if ($context instanceof UnitImpl\Model) {
                    $hydratedContext = $contextUnitImpls[$context->getId()] ?? null;
                    if ($hydratedContext instanceof UnitImpl\Model) {
                        $model->setAssessmentContext($hydratedContext);
                        $courseImplId = $hydratedContext->getCourse()->getId();
                        /* Fully hydrate the course implementation to include the unit
                         * implementations where it is possible
                         */
                        if (isset($courseImpls[$courseImplId])) {
                            $hydratedContext->setCourse($courseImpls[$courseImplId]);
                        }
                    }
                }
            }
        }
        return $modelList;
    }


    /**
     * Augments the list from hydrateSelect() with context projects.
     *
     * @param GenericCollection<Model> $modelList
     * @param array<int>        $contextProjectIds
     * @return GenericCollection<Model>
     */
    protected function augmentListWithProjectContexts(
        GenericCollection $modelList,
        array $contextProjectIds,
    ): GenericCollection {
        if (!empty($contextProjectIds)) {
            // A project loaded as a context should not contain sub-assessments.
            // It should also not be cached, so if I _do_ need to load the
            // project with its sub-assessments, I can do so.
            $this->hydrateProjectSubAssessments = false;
            try {
                $contextProjects = $this->findByColumn(
                    'id',
                    $contextProjectIds,
                    ['deleted' => true, 'cache' => false,],
                )->indexBy('id');
            } finally {
                $this->hydrateProjectSubAssessments = true;
            }
            foreach ($modelList as $model) {
                // This has to be a project
                $context = $model->getAssessmentContext();
                if ($context instanceof Project\Model) {
                    $contextId = $context->getAssessmentModel()->getId();
                    if (isset($contextProjects[$contextId])) {
                        $context->setAssessmentModel($contextProjects[$contextId]);
                        // Ensure project is linked to (potentially deleted) child
                        // Need to ensure that the assessment list is
                        // initialised, so that sub-assessments can be linked
                        if (!$context->getAssessments()) {
                            $context->setAssessments($this->createCollection());
                        }
                        $context->getAssessments()[$model->getId()] = $model;
                    }
                }
            }
        }
        return $modelList;
    }

    /**
     * Augments the list from hydrateSelect() with their context's folder codes.
     *
     * This is required for retrieving participants, but isn't viable to join
     * onto in getSelectString() due to the number of optional 1-M joins this
     * would require.
     *
     * @param GenericCollection<Model> $modelList
     * @return GenericCollection<Model>
     * @throws BaseMapper\DefinitionException If join name is not on this mapper
     */
    protected function augmentListWithFolderCodes(GenericCollection $modelList): GenericCollection
    {
        $folderIds = [];
        // Pass 1: load folder IDs for all non-projects
        // (Project context: folder was loaded in augmentListWithProjectContexts.)
        foreach ($modelList as $model) {
            $context = $model->getAssessmentContext();
            if (isset($context) && !$context instanceof Project\Model) {
                $folderIds[$context->getFolder()->getId()] = $context->getFolder()->getId();
            }
        }

        if (!\count($folderIds)) {
            return $modelList;
        }
        // Load folders in a single query
        $folders = $this->getJoinMapper('assessment_folder')->findByColumn('id', $folderIds)->indexBy('id');
        // Pass 2: assign folders to all non-project contexts
        foreach ($modelList as $model) {
            $context = $model->getAssessmentContext();
            if (isset($context) && !$context instanceof Project\Model) {
                $folderId = $context->getFolder()->getId();
                if (isset($folders[$folderId])) {
                    // Add the folder into the context
                    $model->setAssessmentContext($context->setFolder($folders[$folderId]));
                }
            }
        }
        return $modelList;
    }

    /**
     * Augments the list from hydrateSelect() with their specification assessments.
     *
     * @param GenericCollection<Model> $modelList
     * @return GenericCollection<Model>
     */
    protected function augmentListWithSpecifications(GenericCollection $modelList): GenericCollection
    {
        $specificationIds = [];

        // Pass 1: load spec IDs
        foreach ($modelList as $model) {
            if ($model->getSpecificationAssessment() instanceof Model) {
                $specificationIds[] = $model->getSpecificationAssessment()->getId();
            }
        }

        if (!\count($specificationIds)) {
            return $modelList;
        }

        // Fetch (even deleted specs are required)
        $specifications = $this->findByColumn('id', $specificationIds, ['deleted' => true])->indexBy('id');

        // Pass 2: hydrate
        foreach ($modelList as $model) {
            if ($model->getSpecificationAssessment() instanceof Model) {
                $specificationId = $model->getSpecificationAssessment()->getId();
                if (isset($specifications[$specificationId])) {
                    $model->setSpecificationAssessment($specifications[$specificationId]);
                }
            }
        }

        return $modelList;
    }

    /**
     * Augments the list from hydrateSelect() with their grade groups.
     *
     * @param GenericCollection<Model>$modelList
     * @return GenericCollection<Model>
     * @throws BaseMapper\DefinitionException If join name is not on this mapper
     * @throws BaseMapper\Exception
     */
    protected function augmentListWithGradeGroups(GenericCollection $modelList): GenericCollection
    {
        // Just load grade groups one by one - let the model cache do its thing
        /* Note that despite this being a duework.grade_group join, the Reporting activity also uses it...
         * TODO Amri one day we need to refactor the Grade Group and Mark Type stuff into a common area.
         */
        /** @var SubmissionBox\GradeLevel\Group\Mapper $gradeGroupMapper */
        $gradeGroupMapper = $this->getJoinMapper('duework.grade_group');

        foreach ($modelList as $model) {
            $submodel = $model->getSubObject();

            /* Grade groups (note this requires subobjects to be hydrated FIRST because of THIS hideous instanceof check
             */
            if ($submodel instanceof SubmissionBox || $submodel instanceof Project\Model || $submodel instanceof Quiz\Model || $submodel instanceof Reporting\Model) {
                if ($submodel->getGradeGroup() instanceof SubmissionBox\GradeLevel\Group\Model) {
                    $submodel->setGradeGroup(
                        $gradeGroupMapper->findByIdWithFallback($submodel->getGradeGroup()->getId())
                    );
                }
            }

            // Instructor grade group only applies to submission boxes (DueWork instances)
            if ($submodel instanceof SubmissionBox || $submodel instanceof Reporting\Model) {
                if ($submodel->getInstructorGradeGroup() instanceof SubmissionBox\GradeLevel\Group\Model) {
                    $submodel->setInstructorGradeGroup(
                        $gradeGroupMapper->findByIdWithFallback($submodel->getInstructorGradeGroup()->getId())
                    );
                }
            }

            /* Rubric Capability Grade Groups only apply to submission boxes (DueWork instances)
             */
            if ($submodel instanceof SubmissionBox) {
                if ($submodel->getCapabilityGradeGroup() instanceof SubmissionBox\GradeLevel\Group\Model) {
                    $submodel->setCapabilityGradeGroup(
                        $gradeGroupMapper->findByIdWithFallback($submodel->getCapabilityGradeGroup()->getId())
                    );
                }
            }
        }

        return $modelList;
    }


    /**
     * Assign Lesson Plan Fields to each Lesson Plan instance
     *
     * @param GenericCollection<Model> $modelList
     * @param array<int>               $lessonPlanIds
     * @return GenericCollection<Model>
     * @throws Exception
     * @throws Deleted
     */
    private function augmentWithLessonPlanFields(GenericCollection $modelList, array $lessonPlanIds): GenericCollection
    {
        /* Fetch all the Lesson Plan Fields used by this Lesson Plan
         */
        $lessonPlanFieldMapper = LessonPlan\Field\Mapper::getInstance();

        /* Collate and group all the Lesson Plan Fields by the Lesson Plan id to which they belong
         */
        $fields = $lessonPlanFieldMapper->findByColumn('lesson_plan_id', $lessonPlanIds, [
//            'deleted' => true,
            'order' => [
                'sequence' => 'asc'
            ]])->groupByCallback(
                function ($item) {
                    return $item->getLessonPlan()->getId();
                }
            );

        /* Assign Lesson Plan Fields and Lesson Plan Templates to each instance of a Lesson Plan
         * This is used when grabbing stuff out of the db via the custom query elsewhere in this
         * class so you have to do all these microhydrations manually
         */
        foreach ($modelList as $model) {
            if ($model->getSubObject() instanceof LessonPlan\Model) {
                $model->getLessonPlan()->setFields($fields->getOffset($model->getLessonPlan()->getId()));
            }
        }

        return $modelList;
    }

    /**
     * Assign Lesson Plan Linked assessments to each Lesson Plan instance
     *
     * @param GenericCollection<Model> $modelList
     * @param array<int>               $lessonPlanIds
     * @return GenericCollection<Model>
     * @throws Exception
     * @throws Deleted
     */
    private function augmentWithLessonPlanAssessments(
        GenericCollection $modelList,
        array $lessonPlanIds,
    ): GenericCollection {
        // Project sub-assessments
        if (!\count($lessonPlanIds)) {
            return $modelList;
        }

        /* Fetch all the Lesson Plan Fields used by this Lesson Plan
         */
        $linkedAssessmentsMapper = LessonPlan\LinkedAssessments\Mapper::getInstance();

        /* Collate and group all the Lesson Plan Fields by the Lesson Plan id to which they belong
         */
        $links = $linkedAssessmentsMapper->findByColumn('lesson_plan_id', $lessonPlanIds);

        // if we have any links hydrate them
        if ($links->count()) {
            // get the correct data object to work with
            $statement = $this->prepareSelect(
                $this->getSelectString(),
                $this->getFromString(),
                new Builder($this->getDatabase()->getQuoter(), $this->getTable()),
                ['assessment.id' => $links->map(function (LessonPlan\LinkedAssessments\Model $link) {
                    return $link->getAssessmentModel()->getId();
                })],
                ['order' => ['sequence' => 'asc']]
            );
            $statement->execute();

            /* Inject linked assessments into each instance of a Lesson Plan
             * This is used when grabbing stuff out of the db via the custom query elsewhere in this
             * class so you have to do all these microhydrations manually
             */
            $lessonPlanMapper = $this->getJoinMapper('lessonPlan');
            $lessonPlans      = $lessonPlanMapper->createCollection();
            foreach ($modelList as $model) {
                if ($model->getSubObject() instanceof LessonPlan\Model) {
                    $lessonPlan = $model->getLessonPlan();
                    if (!$lessonPlan->getLinkedAssessments()) {
                        $lessonPlan->setLinkedAssessments($this->createCollection());
                    }
                    $lessonPlans[$lessonPlan->getId()] = $lessonPlan;
                }
            }
            $links = $links->groupByCallback(function (LessonPlan\LinkedAssessments\Model $link) {
                return $link->getAssessmentModel()->getId();
            });

            // As of SA-16450 it should be safe to hydrate project contexts for
            // lesson plan linked assessments, because lesson plans may no
            // longer be linked to projects.
            foreach ($this->hydrateSelectRecursive($statement) as $subAssessment) {
                foreach ($links->offsetGet($subAssessment->getId()) as $lessonPlan) {
                    if ($lessonPlans[$lessonPlan->getLessonPlan()->getId()] instanceof LessonPlan\Model) {
                        $lessonPlans[$lessonPlan->getLessonPlan()->getId()]->getLinkedAssessments()[$subAssessment->getId()] = $subAssessment;
                    }
                }
            }
        }

        return $modelList;
    }


    /**
     * Sets `$specAssessmentId` as the spec assessment for `$implAssessmentId`,
     * bypassing the mapper and its cache problems in order to do so.
     *
     * @param int $implAssessmentId
     * @param int $specAssessmentId
     * @return bool
     */
    public function saveSpecificationAssessment(int $implAssessmentId, int $specAssessmentId): bool
    {
        $this->database->query(
            "
                REPLACE INTO assessment_specification_implementation
                (specification_assessment_id, implementation_assessment_id)
                VALUES (:specAssessmentId, :implAssessmentId)
            ",
            [
                'specAssessmentId' => $specAssessmentId,
                'implAssessmentId' => $implAssessmentId,
            ]
        );
        return true;
    }

    /**
     * Re-sequences specified Assessments
     *
     * @param array $assessmentIds
     * @return bool whether any change was made
     */
    public function sequenceAssessments(array $assessmentIds)
    {
        if (!count($assessmentIds)) {
            return false;
        }

        $query = "
            UPDATE {$this->getTable()}
            SET `sequence` = :seq
            WHERE `id` = :id
        ";

        $statement = $this->database->prepare($query);

        foreach (array_values($assessmentIds) as $sequence => $assessmentId) {
            $statement->execute([
                'seq' => $sequence + 1,
                'id'  => $assessmentId
            ]);
        }

        return true;
    }

    /**
     * Pre-save callback.
     *
     * If the model's sequence is 0 (either because new model, or explicitly cleared),
     * the system will allocate the next available integer in the series.
     *
     * @param Model $assessment
     * @throws \InvalidArgumentException If AssessmentContext is not recognised
     */
    protected function setSequence(Model $assessment)
    {
        // Ignore if this Assessment already had a sequence explicitly set
        if ($assessment->getSequence() !== null) {
            return;
        }

        $contextClass = get_class($assessment->getAssessmentContext());
        switch ($contextClass) {
            case Folder\Model::class:
                $joinName = 'contextFolder';
                break;
            case Project\Model::class:
                $joinName = 'contextProject';
                break;
            case UnitImpl\Model::class:
                $joinName = 'contextUnitImplementation';
                break;
            case UnitSpec\Model::class:
                $joinName = 'contextUnitSpecification';
                break;
            default:
                throw new \InvalidArgumentException('Unknown AssessmentContext: ' . $contextClass);
        }
        $multiJoin = $this->getMap()->getOffset($joinName)['join_def']['multi'];
        $joinTable = $multiJoin['via_table'];
        $thisKey   = $multiJoin['this_key'];
        $otherKey  = $multiJoin['other_key'];

        $pb    = new Builder($this->database->getQuoter(), $this->table);
        if (null === $assessment->getOriginalAssessmentId()) {
            $query = "
            SELECT COALESCE(max(`a`.`sequence`), 0) + 1
            FROM `{$this->getTable()}` `a`
            JOIN `{$joinTable}` `o` ON `o`.`{$thisKey}` = `a`.`id`
        ";

            // Ignore this model's db sequence if it exists.
            // This enables us to clear a model's sequence, and save it again to place it at the end
            if ($assessment->getId()) {
                $pb->notEquals("a.id", $assessment->getId());
            }
            $pb->equals("o.{$otherKey}", $assessment->getAssessmentContext()->getId());
            $pb->isNull('a.deleted_at');
            $query     .= 'WHERE ' . implode(' AND ', $pb->getWhere());
            $statement = $this->database->prepare($query);
            $statement->execute($pb->getParams());
            $newSequence = $statement->fetch(\PDO::FETCH_COLUMN);
        } else {
            /* If the assessment is a customisation of another assessment, we should insert the assessment right after
             * the original assessment instead
             */
            $query = "
                SELECT COALESCE(`sequence`, 0) + 1
                FROM `{$this->getTable()}` `a`
                JOIN `{$joinTable}` `o` ON `o`.`{$thisKey}` = `a`.`id`
            ";
            /* Insert it after the original assessment, or else after the last customisation of that assessment
             */
            $pb->any(
                (new Builder($this->database->getQuoter(), $this->table))
                    ->equals("a.id", $assessment->getOriginalAssessmentId())
                    ->equals('a.original_assessment_id', $assessment->getOriginalAssessmentId())
            );
            $pb->equals("o.{$otherKey}", $assessment->getAssessmentContext()->getId());
            $pb->isNull('a.deleted_at');
            $query     .= 'WHERE ' . implode(' AND ', $pb->getWhere());
            $query     .= ' ORDER BY a.sequence DESC LIMIT 1';
            $statement = $this->database->executeQuery($query, $pb->getParams());
            $newSequence = $statement->fetch(\PDO::FETCH_COLUMN);
            /* Reorder the other assessments to make space for this assessment
             */
            $updateQuery = "
                UPDATE `{$this->getTable()}` `a`
                JOIN `{$joinTable}` `o` ON `o`.`{$thisKey}` = `a`.`id`
                SET `a`.`sequence` = `a`.`sequence` + 1
            ";
            $pb    = new Builder($this->database->getQuoter(), $this->table);
            $pb->greaterThanOrEqualTo("a.sequence", $newSequence);
            $pb->equals("o.{$otherKey}", $assessment->getAssessmentContext()->getId());
            $pb->isNull('a.deleted_at');
            $updateQuery .= 'WHERE ' . implode(' AND ', $pb->getWhere());
            $statement = $this->database->executeQuery($updateQuery, $pb->getParams());
            $statement->fetch(\PDO::FETCH_COLUMN);
        }
        $assessment->setSequence($newSequence);
    }

    /**
     * Pre-save callback.
     *
     * @param Model $assessment
     */
    protected function validateOpenSpan(Model $assessment)
    {
        if ($assessment->getDefaultOpenSpan() < 0) {
            throw new \DomainException("Activity Duration cannot be less than zero");
        }
    }


    /**
     * Validation for whether this assessment has dates.
     *
     * Any type of spec assessment must not have any date: any type of
     * implementation assessment must have all dates.
     *
     * @param Model $assessment
     * @return bool
     */
    protected function validateDatePresence(Model $assessment): bool
    {
        $context = $assessment->getAssessmentContext();
        if (!$context) {
            throw new \InvalidArgumentException('Unknown assessment context: cannot determine if spec or impl');
        }
        $isSpec = $context->isSpecificationContext();
        $isImpl = $context->isImplementationContext();
        $dates  = [
            'publish' => $assessment->getPublishDate(),
            'open'    => $assessment->getOpenDate(),
            'due'     => $assessment->getDueDate(),
            'close'   => $assessment->getCloseDate(),
        ];
        foreach ($dates as $type => $date) {
            $isDate = $date instanceof Alaress_Date_Format;
            if ($isSpec && $isDate) {
                throw new \InvalidArgumentException(sprintf('Spec assessments may not have a %s date', $type));
            }
            if ($isImpl && !$isDate) {
                throw new \InvalidArgumentException(sprintf('Implementation assessments must have a %s date', $type));
            }
        }
        return true;
    }

    /**
     * Validate that the given dates are in order, if they are set:
     * * publish
     * * open
     * * due
     * * close
     *
     * @param Model $assessment
     * @return bool
     * @throws \InvalidArgumentException if dates are out of order
     */
    public function validateDateOrder(Model $assessment): bool
    {
        Validator\DateOrder::validate($assessment);
        return true;
    }

    /**
     * Update the materialized view table `mv_assessment_relation`.
     *
     * This will be more fragile than updating with table-level triggers, but
     * has the advantage of containing all updates within the application to be
     * debugged there.
     *
     * @param Model $assessment
     * @return bool
     */
    protected function updateAssessmentRelationView(Model $assessment): bool
    {
        // Impl and spec come from project context if there is one:
        // otherwise, directly from assessment
        $contextProject = $assessment->getContextProject();
        if ($contextProject) {
            $unitImplementation = $contextProject->getAssessmentModel()->getContextUnitImplementation();
            $unitSpecification  = $contextProject->getAssessmentModel()->getContextUnitSpecification();
        } else {
            $unitImplementation = $assessment->getContextUnitImplementation();
            $unitSpecification  = $assessment->getContextUnitSpecification();
        }

        // Spec assessments: relate to their course's folder, instead of their
        // usual context folder.
        // This replicates the possible bug documented in SA-15828, where the
        // original assessment relation view related specs to their course's
        // folder.
        if ($unitSpecification) {
            $folder = $unitSpecification->getCourse()->getFolder();
        } else {
            $folder = $assessment->getAssessmentContext()->getFolder();
        }
        $params = [
            'assessment_id'                 => $assessment->getId(),
            'folder_id'                     => $folder->getId(),
            'project_assessment_id'         => $contextProject?->getId(),
            'course_implementation_id'      => $unitImplementation?->getCourse()?->getId(),
            'course_unit_implementation_id' => $unitImplementation?->getId(),
            'course_specification_id'       => $unitSpecification?->getCourse()?->getId(),
            'course_unit_specification_id'  => $unitSpecification?->getId(),
        ];

        // Create a predicate builder for deleting the existing
        // `mv_assessment_relation`.
        // Cannot rely upon `REPLACE` with unique key across all columns,
        // because null in SQL is considered an unknown value
        // (e.g. null is not equal to null).
        $pb = new Builder($this->getDatabase()->getQuoter(), 'mv_assessment_relation');
        foreach ($params as $param => $value) {
            $pb = ($value === null)
                ? $pb->isNull($param)
                : $pb->equals($param, $value);
        }
        $deleteQuery = $this->database->prepare(
            "DELETE FROM mv_assessment_relation WHERE {$pb->getWhereIntersect()}"
        );
        $deleteQuery->execute($pb->getParams());

        // Insert the new `mv_assessment_relation` row
        $insertQuery = $this->database->prepare('
            INSERT INTO mv_assessment_relation (
                assessment_id,
                folder_id,
                project_assessment_id,
                course_implementation_id,
                course_unit_implementation_id,
                course_specification_id,
                course_unit_specification_id
            ) VALUES (
                :assessment_id,
                :folder_id,
                :project_assessment_id,
                :course_implementation_id,
                :course_unit_implementation_id,
                :course_specification_id,
                :course_unit_specification_id
            )
        ');
        $insertQuery->execute($params);
        return true;
    }


    /**
     * Any notifications related to assessments must be deferred until all
     * its plans and constituent participation records are saved.
     *
     * @param object      $object
     * @param string      $verb
     * @param object|null $previous
     * @return bool
     */
    protected function raiseNotification($object, $verb, $previous = null)
    {
        return true;
    }
}

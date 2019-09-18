<?php
/**
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */
namespace Schoolbox\Learning\Assessment\DataModel;
use Alaress\Database\Predicate\Builder;
use Alaress\Database\Predicate\SqlFunc\Coalesce;
use Alaress_Date_Format;
use PDOException;
use PDOStatement;
use Schoolbox\Assessment\Component\SubmissionBox;
use Schoolbox\Assessment\Component\SubmissionBoxMapper;
use Schoolbox\Assessment\Course\Task;
use Schoolbox\Assessment\Course\TaskMapper;
use Schoolbox\Collection;
use Schoolbox\Curriculum\Node\Mapper as CurriculumNodeMapper;
use Schoolbox\GenericCollection;
use Schoolbox\Learning\Assessment\DataModel\Project;
use Schoolbox\Learning\Assessment\IAssessable;
use Schoolbox\Learning\Course\DataModel\Implementation as CourseImpl;
use Schoolbox\Learning\Course\DataModel\Implementation\Unit as UnitImpl;
use Schoolbox\Learning\Course\DataModel\Specification\Unit as UnitSpec;
use Schoolbox\Learning\Quiz;
use Schoolbox\Mapper as BaseMapper;
use Schoolbox\Resource\Folder;
use Schoolbox\User;
use Schoolbox\Learning\Assessment\WorkType\Mapper as WorkTypeMapper;
use PDO;
use Schoolbox\Assessment\Participant\Plan;
use UnderflowException;
use Alaress\Database\Predicate\InvalidColumnException;

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

    public const COL_EXTERNAL_ID = 'external_id';


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
        $this->uses('weight', self::TYPE_INTEGER);
        $this->uses('sequence', self::TYPE_INTEGER);
        $this->joins('work_type_id', WorkTypeMapper::class, 'id', self::TYPE_MODEL, null, 'workType', 'work_type');
        $this->uses('default_open_span', self::TYPE_INTEGER);
        $this->uses(self::COL_EXTERNAL_ID, self::TYPE_STRING);
        $this->uses('created_at', self::TYPE_DATETIME, self::COLUMN_CREATED_TIMESTAMP);
        $this->uses('updated_at', self::TYPE_DATETIME, self::COLUMN_UPDATED_TIMESTAMP);
        $this->uses('deleted_at', self::TYPE_DATETIME, self::COLUMN_SOFTDELETE);
        // Type joins (what type of assessment is this?)
        // * task
        // * due work
        // * quiz
        // * project
        $this->joinsReverseSingle('assessment_id', TaskMapper::class, 'task', 'task');
        $this->joinsReverseSingle('assessment_id', SubmissionBoxMapper::class, 'duework', 'duework');
        $this->joinsReverseSingle('assessment_id', Quiz\Mapper::class, 'quiz', 'quiz');
        $this->joinsReverseSingle('assessment_id', Project\Mapper::class, 'project', 'project');
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


        $this->registerCallback(self::CALLBACK_PRESAVE, array($this, 'setSequence'));
        $this->registerCallback(self::CALLBACK_PRESAVE, array($this, 'validateOpenSpan'));
        $this->registerCallback(self::CALLBACK_POSTSAVE, [$this, 'createDynamicPlanIfNew']);

        $this->registerCallback(self::CALLBACK_POSTSOFTDELETE, array($this, 'softDeleteQuiz'));
        $this->registerCallback(self::CALLBACK_POSTSOFTDELETE, array($this, 'softDeleteTask'));
        $this->registerCallback(self::CALLBACK_POSTSOFTDELETE, array($this, 'softDeleteProject'));
        $this->registerCallback(self::CALLBACK_POSTSOFTDELETE, [$this, 'softDeleteDueWork']);
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
        return $this->executeSelect($this->getSelectStringForParticipant($student), $this->getFromString(), $pb, [], $options)->indexBy('id');
    }

    /**
     * @param Model      $assessment
     * @param Model|null $previous
     * @throws PDOException
     * @return bool
     */
    protected function createDynamicPlanIfNew(Model $assessment, Model $previous = null)
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
                    ->setIsDynamic(true)
            );
        } catch (\Schoolbox\Mapper\Exception $e) {
            return false;
        } catch (\UnexpectedValueException $e) {
            return false;
        }

        return true;
    }

    /**
     * When deleting an Assessment that is a Quiz, we need to delete all sub-objects of the Quiz
     * @param Model $assessment
     * @throws BaseMapper\DefinitionException If join name is not on this mapper
     */
    public function softDeleteQuiz(Model $assessment)
    {
        if($assessment->getQuiz() instanceof Quiz\Model){
            $quizMapper = $this->getJoinMapper('quiz');
            $quiz = $quizMapper->findByColumn('assessment_id',$assessment->getId(), ['deleted' => true]);
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
        if($assessment->getTask() instanceof Task){
            $taskMapper = $this->getJoinMapper('task');
            $task = $taskMapper->findByColumn('assessment_id',$assessment->getId(), ['deleted' => true]);
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
        if($assessment->getProject() instanceof Project\Model){
            $projectMapper = $this->getJoinMapper('project');
            $project = $projectMapper->findByColumn('assessment_id',$assessment->getId(), ['deleted' => true]);
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
     * Somewhat efficient query for retrieving the assessments which this student has participated in.
     *
     * @param User                     $student
     * @param bool                     $usePlanDates Should we try to filter on plan dates instead of assessment dates?
     * @param null|Alaress_Date_Format $from
     * @param null|Alaress_Date_Format $to
     * @return array|int[]
     * @throws BaseMapper\DefinitionException If join name is not on this mapper
     * @throws \Alaress\Database\Predicate\InvalidColumnException
     */
    public function findAssessmentsWithInteractions(
        User $student,
        bool $usePlanDates,
        Alaress_Date_Format $from = null,
        Alaress_Date_Format $to = null
    ): array {
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
        }
        $pb->putParam('userId', $student->getId()); // For join

        $where = implode(' AND ', $pb->getWhere());
        $query = "
            SELECT DISTINCT a.id
            FROM  {$this->getDatabase()->quoteIdentifier($this->getTable())} a
            JOIN  {$this->getJoinSql('task', 'a', 't', true)}
            JOIN  {$this->getJoinSql('task.completion', 't', 'tc')} AND tc.student_id = :userId
                  {$planJoin}
            WHERE {$where}
            
            UNION

            SELECT DISTINCT a.id
            FROM  {$this->getDatabase()->quoteIdentifier($this->getTable())} a
            JOIN  {$this->getJoinSql('duework', 'a', 'd', true)}
            JOIN  {$this->getJoinSql('duework.submission', 'd', 'ds')} AND ds.owner = :userId
                  {$planJoin}
            WHERE {$where}

            UNION

            SELECT DISTINCT a.id
            FROM  {$this->getDatabase()->quoteIdentifier($this->getTable())} a
            JOIN  {$this->getJoinSql('duework', 'a', 'd', true)}
            JOIN  {$this->getJoinSql('duework.return', 'd', 'dr')} AND dr.owner = :userId
                  {$planJoin}
            WHERE {$where}

            UNION

            SELECT DISTINCT a.id
            FROM  {$this->getDatabase()->quoteIdentifier($this->getTable())} a
            JOIN  {$this->getJoinSql('quiz', 'a', 'q', true)}
            JOIN  {$this->getJoinSql('quiz.attempt', 'q', 'qa')} AND qa.user_id = :userId
                  {$planJoin}
            WHERE {$where}
            
            ORDER BY id ASC
        ";

        $statement = $this->getDatabase()->prepare($query);
        $statement->execute($pb->getParams());
        return array_column($statement->fetchAll(\PDO::FETCH_ASSOC), 'id');
    }

    /**
     * Selecting from the assessment table also returns whatever assessable
     * object is joined onto it.
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
                {$this->getJoinColumnString('duework.mark_type', 'mt')},
                {$this->getJoinColumnString('quiz', 'q')},
                {$this->getJoinColumnString('project', 'p')},
                {$this->getJoinColumnString('assessment_folder', 'f')},
                {$this->getJoinColumnString('assessment_course_unit_specification', 'us')},
                {$this->getJoinColumnString('assessment_course_unit_specification.folder', 'usf')},
                {$this->getJoinColumnString('assessment_course_unit_implementation', 'ui')},
                {$this->getJoinColumnString('assessment_course_unit_implementation.specification', 'uis')},
                {$this->getJoinColumnString('assessment_course_unit_implementation.specification.folder', 'uisf')},
                {$this->getJoinColumnString('assessment_course_unit_implementation.course', 'uic')},
                {$this->getJoinColumnString('assessment_course_unit_implementation.course.folder', 'uicf')},
                {$this->getJoinColumnString('project_assessment', 'pa')},
                {$this->getJoinColumnString('assessment_specification_implementation', 'spec')}

            FROM {$this->getDatabase()->quoteIdentifier($this->getTable())}
            -- Allow join onto deleted sub-objects
            LEFT JOIN {$this->getJoinSql('task', '', 't', true)}
            LEFT JOIN {$this->getJoinSql('duework', '', 'd', true)}
            LEFT JOIN {$this->getJoinSql('work_type', '', 'wt')}
            LEFT JOIN {$this->getJoinSql('duework.mark_type', 'd', 'mt')}
            LEFT JOIN {$this->getJoinSql('quiz', '', 'q', true)}
            LEFT JOIN {$this->getJoinSql('project', '', 'p', true)}
            LEFT JOIN {$this->getJoinSql('assessment_folder', '', 'f', true)}
            LEFT JOIN {$this->getJoinSql('assessment_course_unit_specification', '', 'us', true)}
            LEFT JOIN {$this->getJoinSql('assessment_course_unit_specification.folder', 'us', 'usf', true)}
            LEFT JOIN {$this->getJoinSql('assessment_course_unit_implementation', '', 'ui', true)}
            LEFT JOIN {$this->getJoinSql('assessment_course_unit_implementation.specification', 'ui', 'uis', true)}
            LEFT JOIN {$this->getJoinSql('assessment_course_unit_implementation.specification.folder', 'uis', 'uisf', true)}
            LEFT JOIN {$this->getJoinSql('assessment_course_unit_implementation.course', 'ui', 'uic', true)}
            LEFT JOIN {$this->getJoinSql('assessment_course_unit_implementation.course.folder', 'uic', 'uicf', true)}
            LEFT JOIN {$this->getJoinSql('project_assessment', '', 'pa', true)}
            LEFT JOIN {$this->getJoinSql('assessment_specification_implementation', '', 'spec', true)}
        ";
    }


    /**
     * Returns a SELECT string suitable for predicates that query the participant or plan tables.
     *
     * @param User $user
     * @return string
     * @throws BaseMapper\DefinitionException
     */
    protected function getSelectStringForParticipant(User $user): string
    {
        return $this->getSelectString() . "
            LEFT JOIN participant_plan ON participant_plan.assessment_id = assessment.id
            LEFT JOIN participant ON participant.participant_plan_id = participant_plan.id AND participant.user_id = {$user->getId()}
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
        $params       = Builder::getArrayParameters($folderIds, 'folder');
        $placeholders = Builder::getArrayPlaceholders($folderIds, 'folder');

        // Get assessments the student is participating in
        $idQuery = "
            SELECT assessment.id
            FROM `folder` f
            JOIN `assessment_folder` AS `v_f`  ON `f`.`id` = `v_f`.`folder_id`
            JOIN `assessment` ON `assessment`.`id` = `v_f`.`assessment_id`
            LEFT JOIN participant_plan pp ON assessment.id = pp.assessment_id
            LEFT JOIN participant pt ON pp.id = pt.participant_plan_id AND pt.user_id = {$participantUser->getId()}
            WHERE f.id IN {$placeholders}

            UNION

            SELECT assessment.id
            FROM `folder` uicf
            JOIN `course_implementation` AS `uic` ON `uicf`.`id` = `uic`.`folder_id` AND `uic`.`deleted_at` IS NULL
            JOIN `course_unit_implementation` AS `ui` ON `uic`.`id` = `ui`.`course_implementation_id` AND `ui`.`deleted_at` IS NULL
            JOIN `assessment_course_unit_implementation` AS `v_ui` ON `ui`.`id` = `v_ui`.`course_unit_implementation_id`
            JOIN `assessment` ON `assessment`.`id` = `v_ui`.`assessment_id`
            LEFT JOIN participant_plan pp ON assessment.id = pp.assessment_id
            LEFT JOIN participant pt ON pp.id = pt.participant_plan_id AND pt.user_id = {$participantUser->getId()}
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
            LEFT JOIN participant pt ON pp.id = pt.participant_plan_id AND pt.user_id = {$participantUser->getId()}
            WHERE `uic`.`folder_id` IN {$placeholders}
            
            UNION
            
            SELECT `p_a`.`assessment_id`
            FROM project_assessment `p_a` 
            JOIN `project` AS `p` ON `p`.`id` = `p_a`.`project_id`
            JOIN assessment ON assessment.id = p.assessment_id
            JOIN assessment_folder AS `v_f`ON `v_f`.`assessment_id` = `assessment`.`id`
            LEFT JOIN participant_plan pp ON `p_a`.`assessment_id` = pp.assessment_id
            LEFT JOIN participant pt ON pp.id = pt.participant_plan_id AND pt.user_id = {$participantUser->getId()}
            WHERE `v_f`.`folder_id` IN {$placeholders}
        ";
        $idStatement = $this->getDatabase()->prepare($idQuery);
        $idStatement->execute(array_merge($params, $builder->getParams()));
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
     * @return Collection|Model[]
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
     * @return Collection|Model[]
     * @throws BaseMapper\DefinitionException
     * @throws BaseMapper\Exception
     */
    private function hydrateSelectRecursive(PDOStatement $statement, bool $hydrateProjectContext = true)
    {
        /** @var Collection|Model[] $modelList */
        $modelList         = $this->createCollection();
        $dueWorkIds        = [];
        $quizIds           = [];
        $projectIds        = [];
        $contextProjectIds = [];

        /** @noinspection PhpAssignmentInConditionInspection */
        while ($row = $statement->fetch(PDO::FETCH_ASSOC)) {
            /** @var Model $model */
            $model = $this->hydrate($row);
            $model->setWorkType($this->hydrateJoin($row, 'work_type', 'wt'));

            // Task?
            if ($row['t.id']) {
                $model->setTask($this->hydrateJoin($row, 'task', 't'));
            } else {
                $model->setTask(null);
            }
            // Due work?
            if ($row['d.id']) {
                $model->setDueWork(
                    $this->hydrateJoin($row, 'duework', 'd')
                        ->setMarkType($this->hydrateJoin($row, 'duework.mark_type', 'mt'))
                );
                $dueWorkIds[] = $row['d.id'];
            } else {
                $model->setDueWork(null);
            }
            // Quiz?
            if ($row['q.assess_id']) {
                $model->setQuiz($this->hydrateJoin($row, 'quiz', 'q'));
                $quizIds[] = $row['q.assess_id'];
            } else {
                $model->setQuiz(null);
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
                // Unit spec
                $context = $this->hydrateJoin($row, 'assessment_course_unit_specification', 'us')
                    ->setFolder($this->hydrateJoin($row, 'assessment_course_unit_specification.folder', 'usf'));
            } elseif ($row['ui.id']) {
                // Unit impl
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
            } elseif ($row['pa.id']) {
                // Project
                $context = $this->hydrateJoin($row, 'project_assessment', 'pa');
                // Load context for this project itself
                $contextProjectIds[] = $row['pa.assessment_id'];
            }

            $model->setAssessmentContext($context);
            $modelList->add($model);
        }
        // Load related 1-M models
        // Due work files
        $modelList = $this->augmentListWithDueWorkFiles($modelList, $dueWorkIds);
        // Project contexts
        if ($hydrateProjectContext) {
            // Infinite-cycle breakout.
            $modelList = $this->augmentListWithProjectContexts($modelList, $contextProjectIds);
        }
        // Folder codes for all contexts
        $modelList = $this->augmentListWithFolderCodes($modelList);
        // Specification assessments
        $modelList = $this->augmentListWithSpecifications($modelList);
        // Grade groups
        $modelList = $this->augmentListWithGradeGroups($modelList);
        // Curriculum Nodes
        $modelList = $this->augmentListWithCurriculumNodes($modelList);
        // Project sub-assessments
        $modelList = $this->augmentListWithProjectSubAssessments($modelList, array_keys($projectIds));
        return $modelList;
    }


    /**
     * For any projects found in $modelList, identified by $projectIds, find their sub-assessments
     * and load them into the projects.
     *
     * @param Collection|Model[] $modelList
     * @param array      $projectIds
     * @return Collection|Model[]
     * @throws BaseMapper\DefinitionException
     * @throws BaseMapper\Exception
     * @throws UnderflowException
     */
    private function augmentListWithProjectSubAssessments(Collection $modelList, array $projectIds)
    {
        // Project sub-assessments
        if (!\count($projectIds)) {
            return $modelList;
        }

        $statement = $this->prepareSelect(
            $this->getSelectString(),
            $this->getFromString(),
            new Builder($this->getDatabase(), $this->getTable()),
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
                $project->setAssessments($this->createCollection());
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
     * @param Collection|Model[] $modelList
     * @return Collection|Model[]
     * @throws BaseMapper\DefinitionException If join name is not on this mapper
     * @throws InvalidColumnException
     * @throws UnderflowException
     */
    protected function augmentListWithCurriculumNodes($modelList)
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
                {$this->getJoinColumnString('curriculum_node_for_assessment', 'cn')},
                {$this->getJoinColumnString('curriculum_node_for_assessment.level', 'cnl')},
                {$this->getJoinColumnString('curriculum_node_for_assessment.level.source', 'cnls')},
                {$this->getJoinColumnString('curriculum_node_for_assessment.area', 'cna')},
                {$this->getJoinColumnString('curriculum_node_for_assessment.area.source', 'cnas')}
            FROM {$this->getTable()}
            INNER JOIN {$this->getJoinSql('curriculum_node_for_assessment', '', 'cn')}
            LEFT  JOIN {$this->getJoinSql('curriculum_node_for_assessment.level', 'cn', 'cnl')}
            LEFT  JOIN {$this->getJoinSql('curriculum_node_for_assessment.level.source', 'cnl', 'cnls')}
            LEFT  JOIN {$this->getJoinSql('curriculum_node_for_assessment.area', 'cn', 'cna')}
            LEFT  JOIN {$this->getJoinSql('curriculum_node_for_assessment.area.source', 'cna', 'cnas')}
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
                $curriculumNodeMapper->hydrateWithRelatedJoins($row, 'cn', 'cna', 'cnas', 'cnl', 'cnls')
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
     * @param Collection|Model[] $modelList
     * @param array|int[]        $dueWorkIds
     * @return Collection|Model[]
     * @throws BaseMapper\DefinitionException If join name is not on this mapper
     * @throws BaseMapper\Exception  If join $name isn't on this mapper
     */
    protected function augmentListWithDueWorkFiles($modelList, $dueWorkIds)
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
                try {
                    $submodel = $model->getSubObject();
                } catch (\UnexpectedValueException $e) {
                    // Broken assessment - not much I can do besides ignore
                    continue;
                }
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
     * Augments the list from hydrateSelect() with context projects.
     *
     * @param Collection|Model[] $modelList
     * @param array|int[]        $contextProjectIds
     * @return Collection|Model[]
     */
    protected function augmentListWithProjectContexts($modelList, $contextProjectIds)
    {
        if (!empty($contextProjectIds)) {
            $contextProjects = $this->findByColumn('id', $contextProjectIds, ['deleted' => true])->indexBy('id');
            foreach ($modelList as $model) {
                // This has to be a project
                $context = $model->getAssessmentContext();
                if ($context instanceof Project\Model) {
                    $contextId = $context->getAssessmentModel()->getId();
                    if (isset($contextProjects[$contextId])) {
                        $context->setAssessmentModel($contextProjects[$contextId]);
                        // Ensure project is linked to (potentially deleted) child
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
     * @param Collection|Model[] $modelList
     * @return Collection|Model[]
     * @throws BaseMapper\DefinitionException If join name is not on this mapper
     */
    protected function augmentListWithFolderCodes($modelList)
    {
        $folderIds = [];
        // Pass 1: load folder IDs for all non-projects
        // (Project context: folder was loaded in augmentListWithProjectContexts.)
        foreach ($modelList as $model) {
            $context = $model->getAssessmentContext();
            if (isset($context) && !$context instanceof Project\Model) {
                $folderIds[] = $context->getFolder()->getId();
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
     * @param Collection|Model[] $modelList
     * @return Collection|Model[]
     */
    protected function augmentListWithSpecifications($modelList)
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
     * @param Collection|Model[] $modelList
     * @return Collection|Model[]
     * @throws BaseMapper\DefinitionException If join name is not on this mapper
     */
    protected function augmentListWithGradeGroups($modelList)
    {
        // Just load grade groups one by one - let the model cache do its thing
        /** @var SubmissionBox\GradeLevel\Group\Mapper $gradeGroupMapper */
        $gradeGroupMapper = $this->getJoinMapper('duework.grade_group');
        foreach ($modelList as $model) {
            try {
                $submodel = $model->getSubObject();
            } catch (\UnexpectedValueException $e) {
                // Broken assessment - not much I can do besides ignore
                continue;
            }
            if ($submodel instanceof SubmissionBox || $submodel instanceof Project\Model) {
                if ($submodel->getGradeGroup() instanceof SubmissionBox\GradeLevel\Group\Model) {
                    $submodel->setGradeGroup(
                        $gradeGroupMapper->findByIdWithFallback($submodel->getGradeGroup()->getId())
                    );
                }
            }
        }

        return $modelList;
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
        if($assessment->getSequence()) {
            return;
        }

        $contextClass = get_class($assessment->getAssessmentContext());
        switch($contextClass){
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
                throw new \InvalidArgumentException('Unknown AssessmentContext: '.$contextClass);
        }
        $multiJoin = $this->getMap()->getOffset($joinName)['join_def']['multi'];
        $joinTable = $multiJoin['via_table'];
        $thisKey   = $multiJoin['this_key'];
        $otherKey  = $multiJoin['other_key'];

        $pb = new Builder($this->database, $this->table);
        $query = "
            SELECT COALESCE(max(`a`.`sequence`), 0) + 1
            FROM `{$this->getTable()}` `a`
            JOIN `{$joinTable}` `o` ON `o`.`{$thisKey}` = `a`.`id`
        ";

        // Ignore this model's db sequence if it exists.
        // This enables us to clear a model's sequence, and save it again to place it at the end
        if($assessment->getId()){
            $pb->notEquals("a.id", $assessment->getId());
        }
        $pb->equals("o.{$otherKey}", $assessment->getAssessmentContext()->getId());
        $pb->isNull('a.deleted_at');
        $query .= 'WHERE ' . implode(' AND ', $pb->getWhere());
        $statement = $this->database->prepare($query);
        $statement->execute($pb->getParams());
        $assessment->setSequence($statement->fetch(\PDO::FETCH_COLUMN));
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

<?php
/**
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2016, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */
namespace Schoolbox\Learning\Assessment\DataModel;
use PDOStatement;
use Schoolbox\Assessment\Term\Model as Term;
use Schoolbox\Learning\Assessment\Project;
use Schoolbox\Learning\AssessmentContext;
use Schoolbox\Learning\Quiz\Model as Quiz;
use Schoolbox\Learning\Quiz\Mapper as QuizMapper;
use Schoolbox\Assessment\Component\SubmissionBox;
use Schoolbox\Assessment\Component\SubmissionBoxMapper;
use Schoolbox\Assessment\Course\Task;
use Schoolbox\Assessment\Course\TaskMapper;
use Schoolbox\Collection;
use Schoolbox\Learning\Assessment\DataModel\Project\Mapper as ProjectMapper;
use Schoolbox\Learning\Assessment\DataModel\Project\Model as ProjectModel;
use Schoolbox\Learning\Course\DataModel\Implementation\Unit\Model as UnitImplementationModel;
use Schoolbox\Learning\Course\DataModel\Implementation\Unit\Mapper as UnitImplementationMapper;
use Schoolbox\Learning\Course\DataModel\Specification\Unit\Model as UnitSpecificationModel;
use Schoolbox\Learning\Course\DataModel\Specification\Unit\Mapper as UnitSpecificationMapper;
use Schoolbox\Mapper as BaseMapper;
use Schoolbox\Resource\Folder\Model as FolderModel;
use Schoolbox\Resource\Folder\Mapper as FolderMapper;
use Schoolbox\User;

/**
 * Class Mapper
 *
 * @method Model createModel($id=null)
 * @method Model findById($id)
 * @method Model findOneByColumnList($columnList, $order = [])
 */
class Mapper extends BaseMapper
{
    /**
     * @inheritdoc
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
        $this->uses('duration', self::TYPE_INTEGER);
        $this->uses('created_at', self::TYPE_DATETIME, self::COLUMN_CREATED_TIMESTAMP);
        $this->uses('updated_at', self::TYPE_DATETIME, self::COLUMN_UPDATED_TIMESTAMP);
        $this->uses('deleted_at', self::TYPE_DATETIME, self::COLUMN_SOFTDELETE);
        // Type joins (what type of assessment is this?)
        // * task
        // * due work
        // * quiz
        // * project
        // These are defined without a related field, because they don't have a matching key in this mapper
        // (that isn't this mapper's PK)
        $this->hasJoin(
            'task',
            [
                'mapper'    => TaskMapper::class,
                'this_key'  => 'id',
                'other_key' => 'assessment_id'
            ]
        );
        $this->hasJoin(
            'duework',
            [
                'mapper'    => SubmissionBoxMapper::class,
                'this_key'  => 'id',
                'other_key' => 'assessment_id'
            ]
        );
        $this->hasJoin(
            'quiz',
            [
                'mapper'    => QuizMapper::class,
                'this_key'  => 'id',
                'other_key' => 'assessment_id'
            ]
        );
        $this->hasJoin(
            'project',
            [
                'mapper'    => ProjectMapper::class,
                'this_key'  => 'id',
                'other_key' => 'assessment_id'
            ]
        );
        // Context joins (where does this assessment live?)
        // * folder (via assessment_folder)
        // * unit specification (via assessment_course_unit_specification)
        // * unit implementation (via assessment_course_unit_implementation)
        // * project (via project_assessment)
        $this->joinsVia(
            'assessment_folder',
            FolderMapper::class,
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
            UnitSpecificationMapper::class,
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
            UnitImplementationMapper::class,
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
            ProjectMapper::class,
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

        $this->registerCallback(self::CALLBACK_PRESAVE, array($this, 'setSequence'));

        $this->registerCallback(self::CALLBACK_POSTSOFTDELETE, array($this, 'softDeleteQuiz'));
        $this->registerCallback(self::CALLBACK_POSTSOFTDELETE, array($this, 'softDeleteTask'));
        $this->registerCallback(self::CALLBACK_POSTSOFTDELETE, array($this, 'softDeleteDueWork'));
        $this->registerCallback(self::CALLBACK_POSTSOFTDELETE, array($this, 'softDeleteProject'));
        $this->registerCallback(self::CALLBACK_POSTRESTORE, array($this, 'restoreQuiz'));
        $this->registerCallback(self::CALLBACK_POSTRESTORE, array($this, 'restoreTask'));
        $this->registerCallback(self::CALLBACK_POSTRESTORE, array($this, 'restoreDueWork'));
        $this->registerCallback(self::CALLBACK_POSTRESTORE, array($this, 'restoreProject'));
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
     */
    public function findByColumnList($columnList, $options = [])
    {
        if (empty($options['order']['sequence'])) {
            $options['order']['sequence'] = 'asc';
        }
        return parent::findByColumnList($columnList, $options);
    }

    /**
     * When deleting an Assessment that is a Quiz, we need to delete all sub-objects of the Quiz
     * @param Model $assessment
     */
    public function softDeleteQuiz(Model $assessment)
    {
        if($assessment->getQuiz() instanceof Quiz){
            $quizMapper = $this->getJoinMapper('quiz');
            $quiz = $quizMapper->findByColumn('assessment_id',$assessment->getId(), ['deleted' => true]);
            $quizMapper->delete($quiz);
        }
    }

    /**
     * When restoring an Assessment that is a Quiz, we need to restore all sub-objects of the Quiz
     * @param Model $assessment
     */
    public function restoreQuiz(Model $assessment)
    {
        if($assessment->getQuiz() instanceof Quiz){
            $quizMapper = $this->getJoinMapper('quiz');
            $quiz = $quizMapper->findByColumn('assessment_id',$assessment->getId(), ['deleted' => true]);
            $quizMapper->restoreSoftDeleted($quiz);
        }
    }

    /**
     * When deleting an Assessment that is a Task, we need to delete all sub-objects of the Task
     * @param Model $assessment
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
     * When restoring an Assessment that is a Task, we need to restore all sub-objects of the Task
     * @param Model $assessment
     */
    public function restoreTask(Model $assessment)
    {
        if($assessment->getTask() instanceof Task){
            $taskMapper = $this->getJoinMapper('task');
            $task = $taskMapper->findByColumn('assessment_id',$assessment->getId(), ['deleted' => true]);
            $taskMapper->restoreSoftDeleted($task);
        }
    }

    /**
     * When deleting an Assessment that is a DueWork, we need to delete all sub-objects of the DueWork
     * @param Model $assessment
     */
    public function softDeleteDueWork(Model $assessment)
    {
        if($assessment->getDueWork() instanceof SubmissionBox){
            $dueWorkMapper = $this->getJoinMapper('duework');
            $dueWork = $dueWorkMapper->findByColumn('assessment_id',$assessment->getId(), ['deleted' => true]);
            $dueWorkMapper->delete($dueWork);
        }
    }
    /**
     * When restoring an Assessment that is a DueWork, we need to restore all sub-objects of the DueWork
     * @param Model $assessment
     */
    public function restoreDueWork(Model $assessment)
    {
        if($assessment->getDueWork() instanceof SubmissionBox){
            $dueWorkMapper = $this->getJoinMapper('duework');
            $dueWork = $dueWorkMapper->findByColumn('assessment_id', $assessment->getId(), ['deleted' => true]);
            $dueWorkMapper->restoreSoftDeleted($dueWork);
        }
    }


    /**
     * When deleting an Assessment that is a Project, we need to delete all sub-objects of the Project
     * @param Model $assessment
     */
    public function softDeleteProject(Model $assessment)
    {
        if($assessment->getProject() instanceof ProjectModel){
            $projectMapper = $this->getJoinMapper('project');
            $project = $projectMapper->findByColumn('assessment_id',$assessment->getId(), ['deleted' => true]);
            $projectMapper->delete($project);
        }
    }
    /**
     * When restoring an Assessment that is a Project, we need to restore all sub-objects of the Project
     * @param Model $assessment
     */
    public function restoreProject(Model $assessment)
    {
        if($assessment->getProject() instanceof ProjectModel){
            $projectMapper = $this->getJoinMapper('project');
            $project = $projectMapper->findByColumn('assessment_id',$assessment->getId(), ['deleted' => true]);
            $projectMapper->restoreSoftDeleted($project);
        }
    }

    /**
     * Somewhat efficient query for retrieving the assessments which this student has participated in.
     *
     * @param User $student
     * @param Term $term
     * @return array|int[]
     */
    public function findUniqueAssessmentIdsForStudent(User $student, Term $term)
    {
        // Filter task/duework/quiz by term dates
        $params   = [
            'userId' => $student->getId(),
        ];
        $whereParts = [
            'a.deleted_at IS NULL',
            '(tc.student_id IS NOT NULL OR ds.owner IS NOT NULL OR dr.owner IS NOT NULL OR qa.user_id IS NOT NULL)',
        ];
        if ($term->getStart() instanceof \Alaress_Date_Format && !$term->getStart()->isNull()) {
            $params['from'] = $term->getStart()->getDatabaseDate();
            $whereParts[]   = 'DATE(a.due_date) >= :from';
        }
        if ($term->getActualFinish() instanceof \Alaress_Date_Format && !$term->getActualFinish()->isNull()) {
            $params['to'] = $term->getActualFinish()->getDatabaseDate();
            $whereParts[]   = 'DATE(a.due_date) <= :to';
        }

        $where = implode(' AND ', $whereParts);
        $query = "
            SELECT DISTINCT
                a.id
            FROM {$this->getDatabase()->quoteIdentifier($this->getTable())} a
            LEFT JOIN {$this->getJoinSql('task', 'a', 't', true)}
            LEFT JOIN {$this->getJoinSql('task.completion', 't', 'tc')} AND tc.student_id = :userId
            LEFT JOIN {$this->getJoinSql('duework', 'a', 'd', true)}
            LEFT JOIN {$this->getJoinSql('duework.submission', 'd', 'ds')} AND ds.owner = :userId
            LEFT JOIN {$this->getJoinSql('duework.return', 'd', 'dr')} AND dr.owner = :userId
            LEFT JOIN {$this->getJoinSql('quiz', 'a', 'q', true)}
            LEFT JOIN {$this->getJoinSql('quiz.attempt', 'q', 'qa')} AND qa.user_id = :userId
            WHERE {$where}
            ORDER BY a.id ASC
        ";
        $statement = $this->getDatabase()->prepare($query);
        $statement->execute($params);
        return array_column($statement->fetchAll(\PDO::FETCH_ASSOC), 'id');
    }

    /**
     * Selecting from the assessment table also returns whatever assessable
     * object is joined onto it.
     *
     * @return string
     */
    protected function getSelectString()
    {
        return "
            SELECT
                {$this->getColumnString()},
                {$this->getJoinColumnString('task', 't')},
                {$this->getJoinColumnString('duework', 'd')},
                {$this->getJoinColumnString('duework.work_type', 'dt')},
                {$this->getJoinColumnString('duework.mark_type', 'mt')},
                {$this->getJoinColumnString('duework.grade_group', 'gg')},
                {$this->getJoinColumnString('quiz', 'q')},
                {$this->getJoinColumnString('project', 'p')},
                {$this->getJoinColumnString('project.grade_group', 'pgg')},
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
            LEFT JOIN {$this->getJoinSql('duework.work_type', 'd', 'dt')}
            LEFT JOIN {$this->getJoinSql('duework.mark_type', 'd', 'mt')}
            LEFT JOIN {$this->getJoinSql('duework.grade_group', 'd', 'gg')}
            LEFT JOIN {$this->getJoinSql('quiz', '', 'q', true)}
            LEFT JOIN {$this->getJoinSql('project', '', 'p', true)}
            LEFT JOIN {$this->getJoinSql('project.grade_group', 'p', 'pgg')}
            LEFT JOIN {$this->getJoinSql('assessment_folder', '', 'f', true)}
            LEFT JOIN {$this->getJoinSql('assessment_course_unit_specification', '', 'us', true)}
            LEFT JOIN {$this->getJoinSql('assessment_course_unit_specification.folder', 'us', 'usf', true)}
            LEFT JOIN {$this->getJoinSql('assessment_course_unit_implementation', '', 'ui')}
            LEFT JOIN {$this->getJoinSql('assessment_course_unit_implementation.specification', 'ui', 'uis')}
            LEFT JOIN {$this->getJoinSql('assessment_course_unit_implementation.specification.folder', 'uis', 'uisf')}
            LEFT JOIN {$this->getJoinSql('assessment_course_unit_implementation.course', 'ui', 'uic')}
            LEFT JOIN {$this->getJoinSql('assessment_course_unit_implementation.course.folder', 'uic', 'uicf')}
            LEFT JOIN {$this->getJoinSql('project_assessment', '', 'pa')}
            LEFT JOIN {$this->getJoinSql('assessment_specification_implementation', '', 'spec')}
        ";
    }

    /**
     * Retrieves all assessable objects that are in this folder, whether they
     * are attached directly to this folder or through a course implementation
     * that is attached to this folder.
     *
     * @param int|array $folderId   A folder ID or list thereof
     * @param array     $columnList Any other columns to query on
     *                              If "id" is one, these assessments are added to the final search.
     * @param array     $options    Any other options to pass to the final search to `findByColumnList`.
     * @return Collection|Model[]
     */
    public function findAllForFolderId($folderId, $columnList, $options = [])
    {
        // Support stuff for query
        $folderList = BaseMapper\PredicateBuilder::canTraverse($folderId) ? $folderId : [$folderId];
        if (count($folderList) === 0) {
            // Exit early if empty
            return new Collection();
        }
        $params       = BaseMapper\PredicateBuilder::getArrayParameters($folderList, 'folder');
        $placeholders = BaseMapper\PredicateBuilder::getArrayPlaceholders($folderList, 'folder');

        // There's no OR support in the predicate builder.
        // The best I can do is to get all IDs and fetch a union.
        // TODO replace with an OR through the predicate builder
        $idQuery = "
            SELECT DISTINCT a.id
            FROM {$this->getDatabase()->quoteIdentifier($this->getTable())} a
            LEFT JOIN {$this->getJoinSql('assessment_folder', 'a', 'f')}
            LEFT JOIN {$this->getJoinSql('assessment_course_unit_implementation', 'a', 'ui')}
            LEFT JOIN {$this->getJoinSql('assessment_course_unit_implementation.course', 'ui', 'uic')}
            LEFT JOIN {$this->getJoinSql('assessment_course_unit_implementation.course.folder', 'uic', 'uicf')}
            WHERE f.id    IN {$placeholders}
            OR    uicf.id IN {$placeholders}
        ";
        $idStatement = $this->getDatabase()->prepare($idQuery);
        $idStatement->execute($params);
        $idList = array_column($idStatement->fetchAll(), 'id');

        // Send IDs to findByColumnList
        $params = $columnList;
        if (isset($params['id'])) {
            if (!is_array($params['id'])) {
                $params['id'] = [$params['id']];
            }
            $params['id'] = array_unique(array_merge($params['id'], $idList));
        } else {
            $params['id'] = $idList;
        }
        return $this->findByColumnList($params, $options);
    }

    /**
     * Retrieves a list of assessments, along with their joined objects.
     *
     * @param PDOStatement $statement
     * @return Collection|Model[]
     */
    protected function hydrateSelect(PDOStatement $statement)
    {
        /** @var Collection|Model[] $modelList */
        $modelList         = $this->createCollection();
        $dueWorkIds        = [];
        $quizIds           = [];
        $contextProjectIds = [];
        foreach ($statement as $row) {
            /** @var Model $model */
            $model = $this->hydrate($row);
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
                        ->setWorkType($this->hydrateJoin($row, 'duework.work_type', 'dt'))
                        ->setMarkType($this->hydrateJoin($row, 'duework.mark_type', 'mt'))
                );

                // apply grade group
                $model->getDueWork()->setGradeGroup(
                    $this->hydrateJoin($row, 'duework.grade_group', 'gg')
                         ->setGradeLevels(
                             (new SubmissionBox\GradeLevel\ListFactory())->buildGradeList($model->getDueWork())
                         )
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
                $model->setProject($this->hydrateJoin($row, 'project', 'p'));

                $model->getProject()->setGradeGroup(
                    $this->hydrateJoin($row, 'project.grade_group', 'pgg')
                        ->setGradeLevels(
                            (new SubmissionBox\GradeLevel\ListFactory())->buildGradeList($model->getProject())
                        )
                );
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
                $context = $this->hydrateJoin($row, 'assessment_course_unit_implementation', 'ui')
                    ->setCourse(
                        $this->hydrateJoin($row, 'assessment_course_unit_implementation.course', 'uic')
                            ->setFolder(
                                $this->hydrateJoin($row, 'assessment_course_unit_implementation.course.folder', 'uicf')
                            )
                    )
                    ->setSpecification(
                        $this->hydrateJoin($row, 'assessment_course_unit_implementation.specification', 'uis')
                             ->setFolder(
                                 $this->hydrateJoin($row, 'assessment_course_unit_implementation.specification.folder', 'uisf')
                             )
                    );
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
        // Due work grade levels
        $modelList = $this->augmentListWithDueWorkGradeLevels($modelList, $dueWorkIds);
        // Due work rubric grids
        // TODO don't auto-populate this
        $modelList = $this->augmentListWithDueWorkRubrics($modelList);
        // Project contexts
        $modelList = $this->augmentListWithProjectContexts($modelList, $contextProjectIds);
        // Folder codes for all contexts
        $modelList = $this->augmentListWithFolderCodes($modelList);
        // Specification assessments
        $modelList = $this->augmentListWithSpecifications($modelList);

        return $modelList;
    }
    /**
     * Augments the list from hydrateSelect() with due work files.
     *
     * @param Collection|Model[] $modelList
     * @param array|int[]        $dueWorkIds
     * @return Collection|Model[]
     */
    protected function augmentListWithDueWorkFiles($modelList, $dueWorkIds)
    {
        if (!empty($dueWorkIds)) {
            // Retrieve and sort list of duework files
            $files      = [];
            $filesQuery = "
                SELECT
                    d.id as duework_id,
                    {$this->getJoinColumnString('duework.submission_box_file', 's')},
                    {$this->getJoinColumnString('duework.submission_box_file.allowedFileExtension', 'm')}
                FROM {$this->getJoinMapper('duework', 'd')->getTable()} d
                INNER JOIN {$this->getJoinSql('duework.submission_box_file', 'd', 's')}
                LEFT JOIN {$this->getJoinSql('duework.submission_box_file.allowedFileExtension', 's', 'm')}
                WHERE d.id IN {$this->getArrayPlaceholders($dueWorkIds, 'id')}
                ORDER BY d.id ASC
            ";

            $filesStatement = $this->getDatabase()->prepare($filesQuery);
            $filesStatement->execute($this->getArrayParameters($dueWorkIds, 'id'));
            foreach ($filesStatement as $row) {
                if (!isset($files[$row['duework_id']])) {
                    $files[$row['duework_id']] = new Collection();
                }

                $mimetype = $this->getJoinMapper('duework.submission_box_file.allowedFileExtension')->hydrate($row, 'm');

                $file = $this->getJoinMapper('duework.submission_box_file')->hydrate($row, 's');
                $file->setMimeType($mimetype);

                $files[$row['duework_id']]->add($file);
            }
            // Augment list with due work files
            foreach ($modelList as $model) {
                try {
                    $submodel = $model->getSubObject();
                } catch (\UnexpectedValueException $e) {
                    // Broken assessment - not much I can do besides ignore
                    continue;
                }
                if ($submodel instanceof SubmissionBox && !empty($files[$submodel->getId()])) {
                    $submodel->setAttachments($files[$submodel->getId()]);
                }
            }
        }
        return $modelList;
    }
    /**
     * Augments the list from hydrateSelect() with the grade levels for their selected grade scheme.
     *
     * @param Collection|Model[] $modelList
     * @param array|int[]        $dueWorkIds
     * @return Collection|Model[]
     */
    protected function augmentListWithDueWorkGradeLevels($modelList, $dueWorkIds)
    {
        if (!empty($dueWorkIds)) {
            $gradeGroups = SubmissionBox\GradeLevel\Group\Mapper::getInstance()->findAllWithGradeLevels()->indexBy('id');
            // Augment list
            foreach ($modelList as $model) {
                try {
                    $submodel = $model->getSubObject();
                } catch (\UnexpectedValueException $e) {
                    // Broken assessment - not much I can do besides ignore
                    continue;
                }
                if ($submodel instanceof SubmissionBox) {
                    if ($submodel->getMarkType()->getId() == SubmissionBox\MarkType::TYPE_LETTER) {
                        $submodel->setGradeGroup($gradeGroups[$submodel->getGradeGroup()->getId()]);
                    }
                }
            }
        }
        return $modelList;
    }
    /**
     * Augments the list from hydrateSelect() with the rubric grid (if it has one).
     *
     * @param Collection|Model[] $modelList
     * @return Collection|Model[]
     */
    public function augmentListWithDueWorkRubrics($modelList)
    {
        foreach ($modelList as $model) {
            try {
                $submodel = $model->getSubObject();
            } catch (\UnexpectedValueException $e) {
                // Broken assessment - not much I can do besides ignore
                continue;
            }
            if ($submodel instanceof SubmissionBox) {
                $rubricMapper = SubmissionBox\Rubric\RubricMapper::getInstance();
                $submodel->setRubric($rubricMapper->findBySubmissionBox($submodel));
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
            $contextProjects = $this->findByColumn('id', $contextProjectIds)->indexBy('id');
            foreach ($modelList as $model) {
                // This has to be a project
                $context = $model->getAssessmentContext();
                if ($context instanceof ProjectModel) {
                    $contextId = $context->getAssessmentModel()->getId();
                    if (isset($contextProjects[$contextId])) {
                        $model->getAssessmentContext()->setAssessmentModel($contextProjects[$contextId]);
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
     */
    protected function augmentListWithFolderCodes($modelList)
    {
        $folderIds = [];
        // Pass 1: load folder IDs for all non-projects
        // (Project context: folder was loaded in augmentListWithProjectContexts.)
        foreach ($modelList as $model) {
            $context = $model->getAssessmentContext();
            if (isset($context) && !$context instanceof ProjectModel) {
                $folderIds[] = $context->getFolder()->getId();
            }
        }
        // Load folders in a single query
        $folders = $this->getJoinMapper('assessment_folder')->findByColumn('id', $folderIds)->indexBy('id');
        // Pass 2: assign folders to all non-project contexts
        foreach ($modelList as $model) {
            $context = $model->getAssessmentContext();
            if (isset($context) && !$context instanceof ProjectModel) {
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

        // Fetch
        $specifications = $this->findByColumn('id', $specificationIds)->indexBy('id');

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
     */
    protected function setSequence(Model $assessment)
    {
        // Ignore if this Assessment already had a sequence explicitly set
        if($assessment->getSequence()) {
            return;
        }

        $contextClass = get_class($assessment->getAssessmentContext());
        switch($contextClass){
            case FolderModel::class:
                $joinName = 'contextFolder';
                break;
            case ProjectModel::class:
                $joinName = 'contextProject';
                break;
            case UnitImplementationModel::class:
                $joinName = 'contextUnitImplementation';
                break;
            case UnitSpecificationModel::class:
                $joinName = 'contextUnitSpecification';
                break;
            default:
                throw new \InvalidArgumentException('Unknown AssessmentContext: '.$contextClass);
        }
        $multiJoin = $this->getMap()->getOffset($joinName)['join_def']['multi'];
        $joinTable = $multiJoin['via_table'];
        $thisKey   = $multiJoin['this_key'];
        $otherKey  = $multiJoin['other_key'];

        $pb = new BaseMapper\PredicateBuilder($this->database, $this->table);
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
        $pb->isNull("a.deleted_at");
        $query .= 'WHERE ' . implode(' AND ', $pb->getWhere());
        $statement = $this->database->prepare($query);
        $statement->execute($pb->getParams());
        $assessment->setSequence($statement->fetch(\PDO::FETCH_COLUMN));
    }
}

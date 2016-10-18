<?php
/**
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2016, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

namespace Schoolbox\Learning\Assessment\DataModel;

use PDOStatement;
use Schoolbox\Assessment\Component\OnlineAssessmentMapper;
use Schoolbox\Assessment\Component\SubmissionBox;
use Schoolbox\Assessment\Component\SubmissionBoxMapper;
use Schoolbox\Assessment\Course\TaskMapper;
use Schoolbox\Collection;
use Schoolbox\Learning\Assessment\DataModel\Project\Mapper as ProjectMapper;
use Schoolbox\Learning\Assessment\DataModel\Project\Model as ProjectModel;
use Schoolbox\Learning\Course\DataModel\Implementation\Unit\Mapper as UnitImplementationMapper;
use Schoolbox\Learning\Course\DataModel\Specification\Unit\Mapper as UnitSpecificationMapper;
use Schoolbox\Mapper as BaseMapper;
use Schoolbox\Resource\Folder\Mapper as FolderMapper;

/**
 * Class Mapper
 *
 * @method Model createModel($id=null)
 * @method Model findById($id)
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
                'mapper'    => OnlineAssessmentMapper::class,
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
                {$this->getJoinColumnString('quiz', 'q')},
                {$this->getJoinColumnString('project', 'p')},
                {$this->getJoinColumnString('assessment_folder', 'f')},
                {$this->getJoinColumnString('assessment_course_unit_specification', 'us')},
                {$this->getJoinColumnString('assessment_course_unit_specification.folder', 'usf')},
                {$this->getJoinColumnString('assessment_course_unit_implementation', 'ui')},
                {$this->getJoinColumnString('assessment_course_unit_implementation.course', 'uic')},
                {$this->getJoinColumnString('assessment_course_unit_implementation.course.folder', 'uicf')},
                {$this->getJoinColumnString('project_assessment', 'pa')},
                {$this->getJoinColumnString('assessment_specification_implementation', 'spec')}
                -- May need to hydrate project's context too!
            FROM {$this->getDatabase()->quoteIdentifier($this->getTable())}
            LEFT JOIN {$this->getJoinSql('task', '', 't')}
            LEFT JOIN {$this->getJoinSql('duework', '', 'd')}
            LEFT JOIN {$this->getJoinSql('quiz', '', 'q')}
            LEFT JOIN {$this->getJoinSql('project', '', 'p')}
            LEFT JOIN {$this->getJoinSql('assessment_folder', '', 'f')}
            LEFT JOIN {$this->getJoinSql('assessment_course_unit_specification', '', 'us')}
            LEFT JOIN {$this->getJoinSql('assessment_course_unit_specification.folder', 'us', 'usf')}
            LEFT JOIN {$this->getJoinSql('assessment_course_unit_implementation', '', 'ui')}
            LEFT JOIN {$this->getJoinSql('assessment_course_unit_implementation.course', 'ui', 'uic')}
            LEFT JOIN {$this->getJoinSql('assessment_course_unit_implementation.course.folder', 'uic', 'uicf')}
            LEFT JOIN {$this->getJoinSql('project_assessment', '', 'pa')}
            LEFT JOIN {$this->getJoinSql('assessment_specification_implementation', '', 'spec')}
        ";
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

            // Project?
            if ($row['p.id']) {
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
                $context = $this->hydrateJoin($row, 'assessment_course_unit_implementation', 'ui')
                    ->setCourse(
                        $this->hydrateJoin($row, 'assessment_course_unit_implementation.course', 'uic')
                             ->setFolder(
                                 $this->hydrateJoin($row, 'assessment_course_unit_implementation.course.folder', 'uicf')
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
        if (!empty($dueWorkIds)) {

            // Due work files
            $files      = [];
            $filesQuery = "
                SELECT
                    d.id as duework_id,
                    {$this->getJoinColumnString('duework.submission_box_file', 's')}
                FROM {$this->getJoinMapper('duework', 'd')->getTable()} d
                INNER JOIN {$this->getJoinSql('duework.submission_box_file', 'd', 's')}
                WHERE d.id IN {$this->getArrayPlaceholders($dueWorkIds, 'id')}
                ORDER BY d.id ASC
            ";
            $filesStatement = $this->getDatabase()->prepare($filesQuery);
            $filesStatement->execute($this->getArrayParameters($dueWorkIds, 'id'));
            foreach ($filesStatement as $row) {
                if (!isset($files[$row['duework_id']])) {
                    $files[$row['duework_id']] = new Collection();
                }
                $files[$row['duework_id']]->add($this->getJoinMapper('duework.submission_box_file')->hydrate($row, 's'));
            }
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

        // Load context for projects separately
        // Attempt to do it all in one query
        /** @var Model[] $contextProjects */
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
}

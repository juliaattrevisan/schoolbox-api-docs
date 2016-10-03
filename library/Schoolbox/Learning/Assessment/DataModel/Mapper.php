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
use Schoolbox\Mapper as BaseMapper;

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

        // Joins to:
        // * task
        // * due work
        // * quiz
        // * project
        // are defined without a related field, because they don't have a matching key in this mapper
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

        // folder (by assessment_folder table)
        // unit specification (by assessment_course_unit_specification table)
        // unit implementation (by assessment_course_unit_implementation table)
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
                {$this->getJoinColumnString('project', 'p')}
            FROM {$this->getDatabase()->quoteIdentifier($this->getTable())}
            LEFT JOIN {$this->getJoinSql('task', '', 't')}
            LEFT JOIN {$this->getJoinSql('duework', '', 'd')}
            LEFT JOIN {$this->getJoinSql('quiz', '', 'q')}
            LEFT JOIN {$this->getJoinSql('project', '', 'p')}
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
        $modelList  = $this->createCollection();
        $dueWorkIds = [];
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
                $submodel = $model->getSubObject();
                if ($submodel instanceof SubmissionBox && !empty($files[$submodel->getId()])) {
                    $submodel->setAttachments($files[$submodel->getId()]);
                }
            }
        }

        return $modelList;
    }
}

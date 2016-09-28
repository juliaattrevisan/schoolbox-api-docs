<?php
/**
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2016, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

namespace Schoolbox\Learning\Assessment\DataModel;

use PDOStatement;
use Schoolbox\Assessment\Component\OnlineAssessmentMapper;
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
        // task
        // duework
        // quiz
        // project
        $this->joins('id', TaskMapper::class, 'assessment_id', null, null, 'task', 'task');
        $this->joins('id', SubmissionBoxMapper::class, 'assessment_id', null, null, 'dueWork', 'duework');
        $this->joins('id', OnlineAssessmentMapper::class, 'assessment_id', null, null, 'quiz', 'quiz');
        $this->joins('id', ProjectMapper::class, 'assessment_id', null, null, 'project', 'project');

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
        assert(true);
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
        $modelList = $this->createCollection();
        foreach ($statement as $row) {
            /** @var Model $model */
            $model = $this->hydrate($row);
            $model
                ->setTask(
                    $row['t.id']
                        ? $this->hydrateJoin($row, 'task', 't')
                        : null
                )
                ->setDueWork(
                    $row['d.id']
                        ? $this->hydrateJoin($row, 'duework', 'd')
                        : null
                )
                ->setQuiz(
                    $row['q.assess_id']
                        ? $this->hydrateJoin($row, 'quiz', 'q')
                        : null
                )
                ->setProject(
                    $row['p.id']
                        ? $this->hydrateJoin($row, 'project', 'p')
                        : null
                );
            $modelList->add($model);
        }
        return $modelList;
    }
}

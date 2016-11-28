<?php
/**
 * Mapper for the Task model.
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2013, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

namespace Schoolbox\Assessment\Course;

use Schoolbox;
use Schoolbox\Learning\Assessment\DataModel\Mapper as AssessmentMapper;
use Schoolbox\Mapper\Exception as MapperException;
use Schoolbox\UserMapper;
use Schoolbox\Assessment\Course\Task\CompletionMapper;

/**
 * Task mapper
 *
 * @method Task createModel($id = null)
 */
class TaskMapper extends Schoolbox\Mapper
{
    /**
     * Setup method. Implementing classes to define their SQL map here.
     * @return void
     */
    protected function mapping()
    {
        $this->hasTable('task');
        $this->hasModel(Task::class);
        $this->uses('id', self::TYPE_INTEGER, self::COLUMN_IDENTIFIER);
        $this->uses('link', self::TYPE_STRING);
        $this->uses('requires_confirmation', self::TYPE_BOOLEAN);
        $this->joins('assessment_id', AssessmentMapper::class, 'id', null, null, 'assessmentModel');
        $this->joins('user_id', Schoolbox\UserMapper::class, 'id', null, null, 'owner');

        $this->hasJoin('completions', array(
            'multiple'  => true,
            'mapper'    => Task\CompletionMapper::class,
            'this_key'  => 'id',
            'other_key' => 'task_id',
        ));

        // This column is duplicate of assessment.deleted_at, but is required to ensure soft-deletion on this table.
        $this->uses('deleted_at', self::TYPE_TIMESTAMP, self::COLUMN_SOFTDELETE);

        $this->registerCallback(self::CALLBACK_POSTSOFTDELETE, [$this, 'softDeleteCompletions']);
        $this->registerCallback(self::CALLBACK_POSTRESTORE, [$this, 'restoreCompletions']);
    }

    /**
     * Soft deletes all Completions for the specified Task
     *
     * @param Task $task
     */
    public function softDeleteCompletions(Task $task)
    {
        $taskCompletionMapper = $this->getJoinMapper('completions');
        $completions = $taskCompletionMapper->findByColumn('task_id', $task->getId(), ['deleted' => true]);
        $taskCompletionMapper->delete($completions);
    }


    /**
     * Restores all soft-deleted Completions for the specified Task
     *
     * @param Task $task
     */
    public function restoreCompletions(Task $task)
    {
        $taskCompletionMapper = $this->getJoinMapper('completions');
        $completions = $taskCompletionMapper->findByColumn('task_id', $task->getId(), ['deleted' => true]);
        $taskCompletionMapper->restoreSoftDeleted($completions);
    }

    /**
     * Helper method for retrieving a sensible base task. It contains a join to
     * the category, submission box, assessment and owner of this task.
     *
     * @return string
     */
    protected function getBaseQuery()
    {
        return "
            SELECT
                {$this->getColumnString('t')},
                {$this->getJoinColumnString('owner',          'u')}
            FROM {$this->getTable()} AS t
            LEFT JOIN {$this->getJoinSql('owner',          't', 'u')}
        ";
    }

    /**
     * Hydrate callback for use with queries created with $this->getBaseQuery();
     *
     * @param TaskMapper $mapper
     * @param Task $model
     * @param array $row
     *
     * @return Task
     */
    public static function baseHydrateCallback($mapper, $model, $row)
    {
        /** @var Schoolbox\User $owner */
        $owner = $mapper->hydrateJoin($row, 'owner', 'u');
        $model->setOwner($owner);
        return $model;
    }

    /**
     * {@inheritdoc}
     *
     * @return Task
     */
    public function findById($id)
    {
        $query = $this->getBaseQuery() . " WHERE t.id=?";
        $statement = $this->database->prepare($query);
        $statement->execute(array($id));
        if (($row = $statement->fetch()) !== false) {
            $task = $this->hydrate($row, 't', array($this, 'baseHydrateCallback'));
            $this->throwIfDeleted($task);
            return $task;
        } else {
            throw new MapperException('Instance of object not found');
        }
    }
}

<?php

/**
 * Mapper for the Task model.
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

namespace Schoolbox\Assessment\Course;

use Schoolbox;
use Schoolbox\GenericCollection;
use Schoolbox\Learning\Assessment\DataModel\Mapper as AssessmentMapper;

/**
 * Task mapper
 *
 * @method Task createModel($id = null)
 * @method Task findById($identifierValue)
 */
class TaskMapper extends Schoolbox\Mapper
{

    /**
     * Setup method. Implementing classes to define their SQL map here.
     *
     * @return void
     * @throws Schoolbox\Mapper\DefinitionException
     */
    protected function mapping()
    {
        $this->hasTable('task');
        $this->hasModel(Task::class);
        $this->uses('id', self::TYPE_INTEGER, self::COLUMN_IDENTIFIER);
        $this->uses('link', self::TYPE_STRING);
        $this->uses('requires_confirmation', self::TYPE_BOOLEAN);
        $this->joins('assessment_id', AssessmentMapper::class, 'id', null, null, 'assessmentModel', 'assessmentModel');
        $this->joins('user_id', Schoolbox\UserMapper::class, 'id', null, null, 'owner');

        $this->hasJoin('completion', [
            'multiple'  => true,
            'mapper'    => Task\CompletionMapper::class,
            'this_key'  => 'id',
            'other_key' => 'task_id',
        ]);

        // Unmapped join onto individual files
        $this->hasJoin(
            'files',
            [
                'multiple'  => true,
                'mapper'    => Task\File\Mapper::class,
                'this_key'  => 'id',
                'other_key' => 'task_id',
            ]
        );

        // This column is duplicate of assessment.deleted_at, but is required to ensure soft-deletion on this table.
        $this->uses('deleted_at', self::TYPE_DATETIME, self::COLUMN_SOFTDELETE);

        $this->registerCallback(self::CALLBACK_POSTSOFTDELETE, [$this, 'softDeleteCompletions']);
    }

    /**
     * @inheritdoc
     * @throws Schoolbox\Mapper\DefinitionException
     */
    protected function getSelectString(): string
    {
        return "
            SELECT {$this->getColumnString()},
                   {$this->getJoinColumnString('assessmentModel', 'am')}
        ";
    }


    /**
     * @inheritdoc
     */
    protected function getFromString(): string
    {
        return "
            FROM       {$this->getTable()}
            INNER JOIN {$this->getJoinSql('assessmentModel', '', 'am', true)}
        ";
    }


    /**
     * @param \PDOStatement $statement
     * @return GenericCollection
     * @throws Schoolbox\Mapper\DefinitionException
     * @throws Schoolbox\Mapper\Exception
     */
    public function hydrateSelect(\PDOStatement $statement): GenericCollection
    {
        $collection = $this->createCollection();

        /** @noinspection PhpAssignmentInConditionInspection */
        while ($row = $statement->fetch()) {
            $task = $this->hydrate($row);

            $task->setAssessmentModel($this->hydrateJoin($row, 'assessmentModel', 'am'));
            $task->setAttachments($this->getJoinMapper('files')->findByColumn('task_id', $task->getId()));

            $collection[$task->getId()] = $task;
        }

        return $collection;
    }


    /**
     * Persist Task files.
     *
     * @param Task $task
     */
    public function saveFiles(Task $task): void
    {
        $this->cascadeJoin('files', ['task_id' => $task->getId()], $task->getAttachments());
    }


    /**
     * Soft deletes all Completions for the specified Task
     *
     * @param Task $task
     * @throws Schoolbox\Mapper\DefinitionException
     */
    public function softDeleteCompletions(Task $task)
    {
        $taskCompletionMapper = $this->getJoinMapper('completion');
        $completions          = $taskCompletionMapper->findByColumn('task_id', $task->getId(), ['deleted' => true]);
        $taskCompletionMapper->delete($completions);
    }
}

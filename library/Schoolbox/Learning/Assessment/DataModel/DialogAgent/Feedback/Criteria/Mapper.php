<?php

/**
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2025, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Learning\Assessment\DataModel\DialogAgent\Feedback\Criteria;

use Schoolbox\GenericCollection;
use Schoolbox\Mapper\Exception;

/**
 * @method Model createModel($idValue = null)
 * @method GenericCollection|Model[] findAll($options = [])
 * @method Model findById($identifierValue)
 * @method Model findOneByColumn($columnName, $columnValue, array $options = [])
 * @method Model findOneByColumnList($columnList, array $options = [])
 */
class Mapper extends \Schoolbox\Mapper
{
    /**
     * @inheritDoc
     */
    protected function mapping(): void
    {
        $this->hasTable('dialog_agent_feedback_criteria');
        $this->hasModel(Model::class);
        $this->uses('id', self::TYPE_INTEGER, self::COLUMN_IDENTIFIER);
        $this->uses('feedback_id', self::TYPE_INTEGER, null, 'feedback');
        $this->uses('criteria_id', self::TYPE_INTEGER, null, 'criteria');
        $this->uses('body', self::TYPE_STRING, null, 'body');
        $this->uses('created_at', self::TYPE_DATETIME, self::COLUMN_CREATED_TIMESTAMP);
        $this->uses('updated_at', self::TYPE_DATETIME, self::COLUMN_UPDATED_TIMESTAMP);
        $this->uses('deleted_at', self::TYPE_DATETIME, self::COLUMN_SOFTDELETE);

        $this->joins(
            'feedback_id',
            \Schoolbox\Learning\Assessment\DataModel\DialogAgent\Feedback\Mapper::class,
            'id',
            null,
            null,
            'feedback',
            'feedback',
        );

        /* We probably don't need all its details based on the way this worked out in the app itself
         * but you can probably consider converting this to a criteria instance if you needed that
         */
        $this->joins(
            'criteria_id',
            \Schoolbox\Admin\DialogAgent\Scenario\Criteria\Mapper::class,
            'id',
            null,
            null,
            'criteria',
            'criteria',
        );
    }


    /**
     * @inheritDoc
     *
     * @return string
     */
    protected function getSelectString()
    {
        return "
            SELECT
                {$this->getColumnString()},
                {$this->getJoinColumnString('feedback', 'feedback')}
            FROM {$this->getDatabase()->getQuoter()->quoteIdentifier($this->getTable())}
            INNER JOIN {$this->getJoinSql('feedback', '', 'feedback', true)}
        ";
    }


    /**
     * @param \PDOStatement $statement
     * @return GenericCollection
     * @throws Exception
     */
    protected function hydrateSelect(\PDOStatement $statement)
    {
        $stack = $this->createCollection();

        foreach ($statement as $row) {
            /** @var Model $feedbackCriterion */
            $feedbackCriterion = $this->hydrate($row);

            /** @var \Schoolbox\Learning\Assessment\DataModel\DialogAgent\Feedback\Model $feedback */
            $feedback = $this->createJoinModel('feedback', $row['feedback.id']);

            $feedbackCriterion->setFeedback($feedback);

            $stack->add($feedbackCriterion);
        }

        return $stack;
    }
}

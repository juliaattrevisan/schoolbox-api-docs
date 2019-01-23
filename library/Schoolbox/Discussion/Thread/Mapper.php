<?php
/**
 * @copyright Copyright 2019, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Discussion\Thread;

use PDOStatement;
use Schoolbox\GenericCollection;
use Schoolbox\Mapper as BaseMapper;

/**
 * Mapper for discussion threads.
 */
class Mapper extends BaseMapper
{
    /**
     * @inheritdoc
     */
    protected function mapping()
    {
        $this->hasTable('discussion_thread');
        $this->uses('id', BaseMapper::COLUMN_IDENTIFIER);
        $this->uses('open', BaseMapper::TYPE_BOOLEAN);
        $this->uses('created_at', BaseMapper::TYPE_DATETIME, BaseMapper::COLUMN_CREATED_TIMESTAMP);
        $this->uses('updated_at', BaseMapper::TYPE_DATETIME, BaseMapper::COLUMN_UPDATED_TIMESTAMP);
        $this->uses('deleted_at', BaseMapper::TYPE_DATETIME, BaseMapper::COLUMN_SOFTDELETE);
        $this->joinsReverseSingle('discussion_thread_id', Context\Mapper::class, 'context', 'context');
    }


    /**
     * Overridden to join on context.
     *
     * @return string
     */
    protected function getSelectString()
    {
        return "
            SELECT
                {$this->getColumnString()},
                {$this->getJoinColumnString('context', 'c')}
        ";
    }


    /**
     * Overridden to join on context.
     *
     * @return string
     */
    protected function getFromString()
    {
        return "
            FROM {$this->getTable()}
            LEFT JOIN {$this->getJoinSql('context', '', 'c')}
        ";
    }


    /**
     * Overridden to join on context.
     *
     * @param PDOStatement $statement
     * @return \Schoolbox\GenericCollection
     */
    protected function hydrateSelect(PDOStatement $statement)
    {
        $collection = $this->createCollection();
        foreach ($statement as $row) {
            /** @var Model $model */
            $model = $this->hydrate($row);

            /** @var Context\Model|null $context */
            $context = $this->hydrateJoin($row, 'context', 'c');
            if ($context) {
                $model->setContext($context);
            }

            $collection[$model->getId()] = $collection;
        }
        return $collection;
    }


    /**
     * Find threads for a particular context.
     *
     * @param string $contextType
     * @param int    $contextId
     * @param array  $options
     * @return GenericCollection
     * @throws BaseMapper\Exception
     */
    public function findForContext(string $contextType, int $contextId, array $options = []): GenericCollection
    {
        return $this->findByColumnList(
            [
                'c.context_type' => $contextType,
                'c.context_id'   => $contextId,
            ],
            $options
        );
    }
}

<?php
/**
 * @copyright Copyright 2019, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Discussion\Thread;

use PDOStatement;
use Schoolbox\Discussion\Post;
use Schoolbox\GenericCollection;
use Schoolbox\Mapper as BaseMapper;

/**
 * Mapper for discussion threads.
 *
 * @method Model createModel($idValue = null)
 */
class Mapper extends BaseMapper
{
    /**
     * @inheritdoc
     */
    protected function mapping()
    {
        $this->hasTable('discussion_thread');
        $this->hasModel(Model::class);
        $this->uses('id', self::TYPE_INTEGER, BaseMapper::COLUMN_IDENTIFIER);
        $this->uses('open', BaseMapper::TYPE_BOOLEAN);
        $this->uses('created_at', BaseMapper::TYPE_DATETIME, BaseMapper::COLUMN_CREATED_TIMESTAMP);
        $this->uses('updated_at', BaseMapper::TYPE_DATETIME, BaseMapper::COLUMN_UPDATED_TIMESTAMP);
        $this->uses('deleted_at', BaseMapper::TYPE_DATETIME, BaseMapper::COLUMN_SOFTDELETE);
        $this->joinsReverseMulti('discussion_thread_id', Post\Mapper::class, null, 'posts');
        $this->joinsReverseMulti('discussion_thread_id', Context\Mapper::class, null, 'context');
        $this->registerCallback(self::CALLBACK_POSTSOFTDELETE, [$this, 'deleteThreads']);
    }


    /**
     * Post-delete for threads: delete all posts within as well.
     *
     * @param Model $model
     */
    public function deleteThreads(Model $model): void
    {
        $this->getJoinMapper('posts')->deleteByColumn('discussion_thread_id', $model->getId());
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

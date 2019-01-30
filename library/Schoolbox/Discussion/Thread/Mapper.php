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
        $this->uses('id', self::TYPE_INTEGER, self::COLUMN_IDENTIFIER);
        $this->uses('open', self::TYPE_BOOLEAN);
        $this->uses('created_at', self::TYPE_DATETIME, self::COLUMN_CREATED_TIMESTAMP);
        $this->uses('updated_at', self::TYPE_DATETIME, self::COLUMN_UPDATED_TIMESTAMP);
        $this->uses('deleted_at', self::TYPE_DATETIME, self::COLUMN_SOFTDELETE);
        $this->hasJoin(
            'posts',
            [
                'mapper'    => Post\Mapper::class,
                'this_key'  => 'id',
                'other_key' => 'discussion_thread_id'
            ]
        );
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

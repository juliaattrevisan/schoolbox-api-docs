<?php

/**
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Discussion\Thread;

use Schoolbox\Discussion\Comment;
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
            'comments',
            [
                'mapper'    => Comment\Mapper::class,
                'this_key'  => 'id',
                'other_key' => 'discussion_thread_id',
            ]
        );
    }


    /**
     * Is the given thread already associated with a context?
     *
     * @param Model $thread
     * @return bool
     */
    public function isThreadAlreadyAssociated(Model $thread): bool
    {
        return $this->getJoinMapper('comments')->countByColumn('discussion_thread_id', $thread->getId()) > 0;
    }


    /**
     * @inheritDoc
     */
    public function raiseNotification($object, $verb, $previous = null)
    {
        return true;
    }
}

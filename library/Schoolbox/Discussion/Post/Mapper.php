<?php
/**
 * @copyright Copyright 2019, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Discussion\Post;

use PDOStatement;
use Schoolbox\Discussion\Thread;
use Schoolbox\Mapper as BaseMapper;
use Schoolbox\User;
use Schoolbox\UserMapper;

/**
 * Mapper for posts in discussion threads.
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
        $this->hasTable('discussion_post');
        $this->hasModel(Model::class);
        $this->uses('id', self::TYPE_INTEGER, self::COLUMN_IDENTIFIER);
        $this->joins('discussion_thread_id', Thread\Mapper::class, 'id', null, null, 'thread', 'thread');
        $this->joins('author_id', UserMapper::class, 'id', null, null, null, 'author');
        $this->uses('content', self::TYPE_STRING);
        $this->uses('created_at', self::TYPE_DATETIME, self::COLUMN_CREATED_TIMESTAMP);
        $this->uses('updated_at', self::TYPE_DATETIME, self::COLUMN_UPDATED_TIMESTAMP);
        $this->uses('deleted_at', self::TYPE_DATETIME, self::COLUMN_SOFTDELETE);
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
                {$this->getJoinColumnString('thread', 't')},
                {$this->getJoinColumnString('author', 'a')}
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
            INNER JOIN {$this->getJoinSql('thread', '', 't', true)}
            INNER JOIN {$this->getJoinSql('author', '', 'a', true)}
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

            /** @var Thread\Model|null $thread */
            $thread = $this->hydrateJoin($row, 'thread', 't');
            if ($thread) {
                $model->setThread($thread);
            }

            /** @var User|null $author */
            $author = $this->hydrateJoin($row, 'author', 'a');
            if ($author) {
                $model->setAuthor($author);
            }

            $collection[$model->getId()] = $model;
        }
        return $collection;
    }
}

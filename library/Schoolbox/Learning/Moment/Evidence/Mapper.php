<?php

/**
 * @copyright Copyright 2020, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Learning\Moment\Evidence;

use PDOStatement;
use Schoolbox\Discussion\Thread\Mapper as ThreadMapper;
use Schoolbox\GenericCollection;
use Schoolbox\Mapper as BaseMapper;
use Schoolbox\Resource\Storage\Mapper as StorageMapper;
use Schoolbox\UserMapper;

/**
 * Mapper for learning moment evidence.
 *
 * @method Model createModel($idValue = null)
 * @method Model hydrate(array $row, $alias = '', $callback = null)
 */
class Mapper extends BaseMapper
{
    /**
     * @inheritdoc
     */
    protected function mapping(): void
    {
        $this->hasTable('evidence');
        $this->hasModel(Model::class);
        $this->uses('id', self::TYPE_INTEGER, self::COLUMN_IDENTIFIER);
        $this->joins('hash', StorageMapper::class, 'hash', null, null, 'storage', 'storage');
        $this->uses('filename', self::TYPE_STRING);
        $this->uses('body', self::TYPE_STRING);
        $this->joins('discussion_thread_id', ThreadMapper::class, 'id', null, null, 'discussionThread');
        $this->joins('user_id', UserMapper::class, 'id', null, null, 'recipient', 'recipient');
        $this->joins('created_by', UserMapper::class, 'id', null, null, 'creator', 'creator');
        $this->uses('created_at', self::TYPE_DATETIME, self::COLUMN_CREATED_TIMESTAMP);
        $this->uses('updated_at', self::TYPE_DATETIME, self::COLUMN_UPDATED_TIMESTAMP);
        $this->uses('deleted_at', self::TYPE_DATETIME, self::COLUMN_SOFTDELETE);
    }


    /**
     * Override to select storage, recipient and creator.
     *
     * @return string
     */
    protected function getSelectString(): string
    {
        return "
            SELECT
                {$this->getColumnString()},
                {$this->getJoinColumnString('storage', 'storage')},
                {$this->getJoinColumnString('recipient', 'recipient')},
                {$this->getJoinColumnString('creator', 'creator')}
        ";
    }


    /**
     * Override to join on storage, recipient and creator.
     *
     * @return string
     */
    protected function getFromString(): string
    {
        return "
            FROM {$this->getTable()}
            LEFT JOIN {$this->getJoinSql('storage', '', 'storage')}
            LEFT JOIN {$this->getJoinSql('recipient', '', 'recipient', true)}
            LEFT JOIN {$this->getJoinSql('creator', '', 'creator', true)}
        ";
    }


    /**
     * Override to:
     * * hydrate storage, recipient and creator
     * * key the collection by ID
     *
     * @param PDOStatement $statement
     * @return GenericCollection|Model[]
     *
     * @noinspection PhpDocMissingThrowsInspection hydrate() may throw an error if the join is misdefined.
     *               It's fine here though.
     */
    protected function hydrateSelect(PDOStatement $statement): GenericCollection
    {
        $collection = $this->createCollection();
        foreach ($statement as $row) {
            /** @noinspection PhpUnhandledExceptionInspection */
            $model = $this->hydrate($row);
            $model->setStorage($this->hydrateJoin($row, 'storage', 'storage'));
            $model->setRecipient($this->hydrateJoin($row, 'recipient', 'recipient'));
            $model->setCreator($this->hydrateJoin($row, 'creator', 'creator'));
            $collection[$model->getId()] = $model;
        }
        return $collection;
    }
}

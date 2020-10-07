<?php
/**
 * @copyright Copyright 2020, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Learning\Moment\Evidence;

use Schoolbox\Discussion\Thread\Mapper as ThreadMapper;
use Schoolbox\Mapper as BaseMapper;
use Schoolbox\Resource\Storage\Mapper as StorageMapper;
use Schoolbox\UserMapper;

/**
 * Mapper for learning moment evidence.
 */
class Mapper extends BaseMapper
{
    protected function mapping(): void
    {
        $this->hasTable('evidence');
        $this->hasModel(Model::class);
        $this->uses('id', self::TYPE_INTEGER, self::COLUMN_IDENTIFIER);
        $this->joins('hash', StorageMapper::class, 'hash', null, null, 'storage', 'storage');
        $this->uses('filename', self::TYPE_STRING);
        $this->uses('body', self::TYPE_STRING);
        $this->joins('discussion_thread_id', ThreadMapper::class, 'id', null, null, 'discussionThread');
        $this->joins('user_id', UserMapper::class, 'id', null, null, 'recipient');
        $this->joins('created_by', UserMapper::class, 'id', null, null, 'creator');
        $this->uses('created_at', self::TYPE_DATETIME, self::COLUMN_CREATED_TIMESTAMP);
        $this->uses('updated_at', self::TYPE_DATETIME, self::COLUMN_UPDATED_TIMESTAMP);
        $this->uses('deleted_at', self::TYPE_DATETIME, self::COLUMN_SOFTDELETE);
    }
}

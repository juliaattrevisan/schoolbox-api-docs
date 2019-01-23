<?php
/**
 * @copyright Copyright 2019, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Discussion\Thread;

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
    }
}

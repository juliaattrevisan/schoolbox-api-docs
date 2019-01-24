<?php
/**
 * @copyright Copyright 2019, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Discussion\Thread\Context;

use Schoolbox\Discussion\Thread;
use Schoolbox\Mapper as BaseMapper;

/**
 * Mapper for discussion thread context.
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
        $this->hasTable('discussion_thread_context');
        $this->hasModel(Model::class);
        $this->uses('id', self::TYPE_INTEGER, self::COLUMN_IDENTIFIER);
        $this->joins('discussion_thread_id', Thread\Mapper::class, 'id', null, null, 'thread', 'thread');
        $this->uses('context_type', self::COLUMN_ENUM, null, [Model::CONTEXT_COMMS,]);
        $this->uses('context_id', self::TYPE_INTEGER);
    }
}

<?php

/**
 * @copyright Copyright 2026, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

namespace Schoolbox\User\Language;

use Schoolbox\Mapper as BaseMapper;
use Schoolbox\UserMapper;

class Mapper extends BaseMapper
{
    /**
     * @inheritdoc
     */
    protected function mapping()
    {
        $this->hasTable('user_language');
        $this->hasModel(Model::class);
        $this->uses('id', self::TYPE_INTEGER, self::COLUMN_IDENTIFIER);
        $this->joins(
            'user_id',
            UserMapper::class,
            'id',
            null,
            null,
            'user',
            'user'
        );
        $this->uses('language', self::TYPE_STRING);
    }
}

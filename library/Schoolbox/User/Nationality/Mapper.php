<?php

/**
 * @copyright Copyright 2026, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

namespace Schoolbox\User\Nationality;

use Schoolbox\Mapper as BaseMapper;
use Schoolbox\UserMapper;

class Mapper extends BaseMapper
{
    /**
     * @inheritdoc
     */
    protected function mapping()
    {
        $this->hasTable('user_nationality');
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
        $this->uses('nationality', self::TYPE_STRING);
    }
}

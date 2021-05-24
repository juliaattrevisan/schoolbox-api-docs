<?php

/**
 * @author      Damian Sloane <damian.sloane@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Schoolbox\User\Log;

use Schoolbox\Mapper as BaseMapper;
use Schoolbox\Mapper\Exception as MapperException;
use Schoolbox\User;

class Mapper extends BaseMapper
{
    /**
     * Setup mapper
     */
    protected function mapping()
    {
        $this->hasTable('login_log');
        $this->hasModel(Model::class);
        $this->uses('id', self::TYPE_INTEGER, self::COLUMN_IDENTIFIER);
        $this->uses('username', self::TYPE_STRING);
        $this->uses('date', self::TYPE_DATETIME);
        $this->uses('ip', self::TYPE_STRING);
        $this->uses('login', self::TYPE_BOOLEAN);
        $this->uses('logout', self::TYPE_BOOLEAN);
    }
}

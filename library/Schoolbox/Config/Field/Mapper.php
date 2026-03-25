<?php

/**
 * @copyright   Copyright 2026, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Config\Field;

use Schoolbox\Mapper as BaseMapper;

class Mapper extends BaseMapper
{
    protected function mapping()
    {
        $this->hasTable('lu_config_field');
        $this->hasModel(Model::class);
        $this->uses('id', self::TYPE_INTEGER, self::COLUMN_IDENTIFIER);
        $this->uses('field', self::TYPE_STRING);
    }
}

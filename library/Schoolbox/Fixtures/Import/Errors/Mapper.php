<?php

/**
 *
 * @author      Damian Sloane <damian.sloane@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Schoolbox\Fixtures\Import\Errors;

use Schoolbox\Mapper as BaseMapper;

class Mapper extends BaseMapper
{
    /**
     * Setup mapper
     */
    protected function mapping()
    {
        $this->hasTable('sportsfixtures_import_errors');
        $this->hasModel(Model::class);
        $this->uses('id', self::TYPE_INTEGER, self::COLUMN_IDENTIFIER);
        $this->uses('import_id', self::TYPE_INTEGER);
        $this->uses('result', self::TYPE_STRING);
        $this->uses('filename', self::TYPE_STRING);
        $this->uses('hash', self::TYPE_STRING);
        $this->uses('type', self::TYPE_STRING);
        $this->uses('line', self::TYPE_INTEGER);
        $this->uses('column', self::TYPE_INTEGER);
        $this->uses('updated_at', self::TYPE_DATETIME);
        $this->uses('created_at', self::TYPE_DATETIME);
    }
}

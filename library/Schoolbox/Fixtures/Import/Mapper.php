<?php

/**
 *
 * @author      Damian Sloane <damian.sloane@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Schoolbox\Fixtures\Import;

use Schoolbox\Mapper as BaseMapper;

class Mapper extends BaseMapper
{
    /**
     * Setup mapper
     */
    protected function mapping()
    {
        $this->hasTable('sportsfixtures_import');
        $this->hasModel(Model::class);
        $this->uses('id', self::TYPE_INTEGER, self::COLUMN_IDENTIFIER);
        $this->uses('owner_id', self::TYPE_INTEGER);
        $this->uses('status', self::TYPE_STRING);
        $this->uses('updated_at', self::TYPE_DATETIME);
        $this->uses('created_at', self::TYPE_DATETIME);
    }
}

<?php
/**
 * @author      Damian Sloane <damian.sloane@alaress.com.au>
 * @copyright   Copyright 2016, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */

namespace Schoolbox\Pastoral\Severity;

use Schoolbox\Mapper as BaseMapper;
use Schoolbox\Pastoral\Severity\Model as SeverityModel;

class Mapper extends BaseMapper
{
    /**
     * Setup mapper
     */
    protected function mapping()
    {
        $this->hasTable('lu_pastoral_severity');
        $this->hasModel(SeverityModel::class);
        $this->uses('id', self::TYPE_INTEGER, self::COLUMN_IDENTIFIER);
        $this->uses('order', self::TYPE_INTEGER);
        $this->uses('name', self::TYPE_STRING);
        $this->uses('dashboard', self::TYPE_BOOLEAN);
        $this->uses('deleted_at', self::TYPE_DATETIME);
        $this->hasSoftDeleteDateField('deleted_at');
    }
}

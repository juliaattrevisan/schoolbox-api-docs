<?php

/**
 * Mapper for default course and unit page permissions.
 *
 * When a user creates a new Course or Unit, the system creates a new Folder.
 * This table dictates what the permissions will be on this new folder.
 *
 * @author    Damian Sloane <damian.sloane@alaress.com.au>
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

namespace Schoolbox\Assessment\Course\Config;

use Schoolbox\Mapper as BaseMapper;
use Schoolbox\Collection;

/**
 * @method Model[]|Collection findByColumn($columnName, $columnValue, $options = array())
 */
class Mapper extends BaseMapper
{
    public function mapping()
    {
        $this->hasTable('course_builder_config');
        $this->hasModel(Model::class);

        $this->uses('id', self::TYPE_INTEGER, self::COLUMN_IDENTIFIER);
        $this->uses('role_id', self::TYPE_INTEGER);
        $this->uses('permission', self::TYPE_INTEGER);
        $this->uses('page', self::TYPE_STRING);
    }
}

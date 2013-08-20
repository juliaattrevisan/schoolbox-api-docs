<?php
/**
 * Mapper for the TaskType model.
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2013, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

namespace Schoolbox\Assessment\Course;

use Schoolbox\Mapper;

class TaskTypeMapper extends Mapper {

    protected function mapping() {
        $this->hasTable('lu_task_type');
        $this->hasModel('\\Schoolbox\\Assessment\\Course\\TaskType');
        $this->hasMap(array(
            'column'     => 'id',
            'property'   => 'id',
            'type'       => self::TYPE_INTEGER,
            'identifier' => true,
        ));
        $this->hasMap(array(
            'column'     => 'label',
            'property'   => 'label',
            'type'       => self::TYPE_STRING,
        ));
        $this->hasMap(array(
            'column'     => 'class',
            'property'   => 'class',
            'type'       => self::TYPE_STRING,
        ));
    }
}

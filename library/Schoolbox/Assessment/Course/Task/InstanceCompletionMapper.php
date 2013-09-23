<?php
/**
 * Mapper for a student's completion of a task.
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2013, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

namespace Schoolbox\Assessment\Course\Task;

use Schoolbox\Mapper;

class InstanceCompletionMapper extends Mapper {

    protected function mapping() {
        $this->hasTable('task_instance_completion');
        $this->hasModel('\\Schoolbox\\Assessment\\Course\\Task\\InstanceCompletion');
        $this->hasMap(array(
            'column'     => 'id',
            'property'   => 'id',
            'type'       => self::TYPE_INTEGER,
            'identifier' => true,
        ));
        $this->hasMap(array(
            'column'     => 'task_instance_id',
            'property'   => 'taskInstance',
            'type'       => self::TYPE_MODEL,
            'join'       => 'task_instance',
        ));
        $this->hasMap(array(
            'column'     => 'student_id',
            'property'   => 'student',
            'type'       => self::TYPE_MODEL,
            'join'       => 'student',
        ));
        $this->hasMap(array(
            'column'     => 'completion_date',
            'property'   => 'completionDate',
            'type'       => self::TYPE_DATETIME,
        ));
        $this->hasMap(array(
            'column'     => 'completion_status',
            'property'   => 'completionStatus',
            'type'       => self::TYPE_INTEGER,
        ));

        $this->hasJoin('task_instance', array(
            'mapper'    => 'Schoolbox\\Assessment\\Course\\Task\\InstanceMapper',
            'this_key'  => 'task_instance_id',
            'other_key' => 'id',
        ));
        $this->hasJoin('student', array(
            'mapper'    => 'Schoolbox\\UserMapper',
            'this_key'  => 'student_id',
            'other_key' => 'id',
        ));
    }
}

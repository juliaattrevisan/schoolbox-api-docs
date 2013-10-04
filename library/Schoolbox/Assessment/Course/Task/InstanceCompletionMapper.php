<?php
/**
 * Mapper for a student's completion of a task.
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2013, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

namespace Schoolbox\Assessment\Course\Task;

use Schoolbox\Collection;
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

    /**
     * Retrieves the most recent completions for each task instance in
     * $task_instance_id_list.
     *
     * @param int[] $task_instance_id_list
     * @param int   $student_id
     *
     * @return Collection
     */
    public function findLatestForList($task_instance_id_list, $student_id = null) {
        if (empty($task_instance_id_list)) {
            return $this->createCollection();
        }
        $query = "
            SELECT {$this->getColumnString('ti')}
            FROM `{$this->getTable()}` `ti`
            INNER JOIN (
                    SELECT
                        `ti_inner`.`task_instance_id`,
                        `ti_inner`.`student_id`,
                        MAX(`ti_inner`.`completion_date`) as `completion_date`
                    FROM `{$this->getTable()}` `ti_inner`
                    GROUP BY `ti_inner`.`task_instance_id`, `ti_inner`.`student_id`
                ) `x`
                ON  `ti`.`task_instance_id` = `x`.`task_instance_id`
                AND `ti`.`student_id`       = `x`.`student_id`
                AND `ti`.`completion_date`  = `x`.`completion_date`
        ";
        $where_parts = array(
            'ti.task_instance_id IN '.$this->getArrayPlaceholders($task_instance_id_list, 'ti')
        );
        $params = $this->getArrayParameters($task_instance_id_list, 'ti');
        if (!empty($student_id)) {
            $where_parts[]     = 'ti.student_id = :student';
            $params['student'] = $student_id;
        }
        $statement = $this->getDatabase()->prepare($query . ' WHERE ' . implode(' AND ', $where_parts));
        $statement->execute($params);

        return $this->hydrateList($statement, 'ti');
    }
}

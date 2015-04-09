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
use Schoolbox\User;

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
            'column'     => 'marking_user_id',
            'property'   => 'markingUser',
            'type'       => self::TYPE_MODEL,
            'join'       => 'marking_user',
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
        $this->hasJoin('marking_user', array(
            'mapper'    => 'Schoolbox\\UserMapper',
            'this_key'  => 'marking_user_id',
            'other_key' => 'id',
        ));
        $this->registerCallback('preSave', array($this, 'preSave'));
    }

    /**
     * Unsets this task instance as having been completed by the student.
     *
     * Only tasks set to self assessment may be marked as completed by the student
     *
     * @param int $task_instance_id
     * @param int $student_id
     *
     * @return array
     */
    public function unmarkAsCompleted($task_instance_id, $student_id)
    {
        return $this->mark($task_instance_id, $student_id, InstanceCompletion::COMPLETION_STATUS_INCOMPLETE);
    }

    /**
     * Sets this task instance as having been completed by the student.
     *
     * Only tasks set to self assessment may be marked as completed by the student
     *
     * @param int $task_instance_id
     * @param int $student_id
     *
     * @return array
     */
    public function markAsCompleted($task_instance_id, $student_id)
    {
        return $this->mark($task_instance_id, $student_id, InstanceCompletion::COMPLETION_STATUS_REQUIRES_CONFIRMATION);
    }

    /**
     * Internal method used by unmarkAsCompleted and markAsCompleted.
     *
     * @param int $task_instance_id
     * @param int $student_id
     * @param int $status
     *
     * @return array
     */
    protected function mark($task_instance_id, $student_id, $status)
    {
        // Is there already a completion record for this item? If so, don't
        // create a new one.
        $existing = $this->findByColumnList(
            array(
                'task_instance_id' => $task_instance_id,
                'student_id'       => $student_id,
            ),
            array('limit' => 1)
        );
        if (count($existing) > 0) {
            return array();
        }

        // Get the related task instance (should it have been passed?) for the
        // completion status to set.
        //$task_instance = $this->getJoinMapper('task_instance')->findById($task_instance_id);

        $model = new InstanceCompletion();
        $model
            ->setTaskInstance($task_instance_id)
            ->setStudent($student_id)
            ->setCompletionDate(new \Alaress_Date_Format(new \DateTime('now')))
            ->setCompletionStatus($status)
        ;
        return $this->save($model);
    }

    /**
     * Retrieves most recent completion for $student_id on $task_instance_id.
     *
     * @param int $task_instance_id
     * @param int $student_id
     *
     * @return InstanceCompletion
     * @throws Mapper\Exception if no completion was found.
     */
    public function findLatestForTaskAndStudent($task_instance_id, $student_id) {
        $list = $this->findLatestForList(array($task_instance_id), $student_id);
        if ($list->count() === 0) {
            throw new Mapper\Exception('Instance of object not found');
        }
        return $list->first();
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
        $where_parts[]            = 'ti.completion_status != :exclude_status';
        $params['exclude_status'] = InstanceCompletion::COMPLETION_STATUS_INCOMPLETE;

        $statement = $this->getDatabase()->prepare($query . ' WHERE ' . implode(' AND ', $where_parts));
        $statement->execute($params);

        return $this->hydrateList($statement, 'ti');
    }

    /**
     * Pre-save method for saving an instance completion object - saves the
     * marking user if this is a new object.
     *
     * @param InstanceCompletion $object
     */
    public function preSave(InstanceCompletion $object) {
        if (!$object->getId() && $_SESSION['user'] instanceof User) {
            $object->setMarkingUser($_SESSION['user']);
        }
    }
}

<?php
/**
 * Mapper for a student's completion of a task.
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2013, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

namespace Schoolbox\Assessment\Course\Task;

use Schoolbox\Assessment\Course\TaskMapper;
use Schoolbox\Collection;
use Schoolbox\Mapper;
use Schoolbox\User;

class CompletionMapper extends Mapper {

    protected function mapping() {
        $this->hasTable('task_completion');
        $this->hasModel(Completion::class);
        $this->hasMap(array(
            'column'     => 'id',
            'property'   => 'id',
            'type'       => self::TYPE_INTEGER,
            'identifier' => true,
        ));
        $this->hasMap(array(
            'column'     => 'task_id',
            'property'   => 'task',
            'type'       => self::TYPE_MODEL,
            'join'       => 'task',
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

        $this->hasJoin('task', array(
            'mapper'    => TaskMapper::class,
            'this_key'  => 'task_id',
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
     * @param int $task_id
     * @param int $student_id
     *
     * @return array
     */
    public function unmarkAsCompleted($task_id, $student_id)
    {
        return $this->mark($task_id, $student_id, Completion::COMPLETION_STATUS_INCOMPLETE);
    }

    /**
     * Sets this task instance as having been completed by the student.
     *
     * Only tasks set to self assessment may be marked as completed by the student
     *
     * @param int $task_id
     * @param int $student_id
     *
     * @return array
     */
    public function markAsCompleted($task_id, $student_id)
    {
        return $this->mark($task_id, $student_id, Completion::COMPLETION_STATUS_REQUIRES_CONFIRMATION);
    }

    /**
     * Internal method used by unmarkAsCompleted and markAsCompleted.
     *
     * @param int $task_id
     * @param int $student_id
     * @param int $status
     *
     * @return array
     */
    protected function mark($task_id, $student_id, $status)
    {
        // What's the most recent completion record?
        // Don't create a new one if it's the same status as the one I want to create.
        try {
            $existing = $this->findOneByColumnList(
                array(
                    'task_id' => $task_id,
                    'student_id'       => $student_id,
                ),
                array('completion_date' => 'desc')
            );
            if ($existing->getCompletionStatus() == $status) {
                return array();
            }
        } catch (Mapper\Exception $e) {
            // no existing completion. Continue
        }

        $model = new Completion();
        $model
            ->setTaskInstance($task_id)
            ->setStudent($student_id)
            ->setCompletionDate(new \Alaress_Date_Format(new \DateTime('now')))
            ->setCompletionStatus($status)
        ;
        return $this->save($model);
    }

    /**
     * Retrieves most recent completion for $student_id on $task_instance_id.
     *
     * @param int $task_id
     * @param int $student_id
     *
     * @return Completion
     * @throws Mapper\Exception if no completion was found.
     */
    public function findLatestForTaskAndStudent($task_id, $student_id) {
        $list = $this->findLatestForList(array($task_id), $student_id);
        if ($list->count() === 0) {
            throw new Mapper\Exception('Instance of object not found');
        }
        return $list->first();
    }

    /**
     * Retrieves the most recent completions for each task in
     * $task_id_list.
     *
     * @param int[] $task_id_list
     * @param int   $student_id
     *
     * @return Collection
     */
    public function findLatestForList($task_id_list, $student_id = null) {
        if (empty($task_id_list)) {
            return $this->createCollection();
        }
        $query = "
            SELECT {$this->getColumnString('ti')}
            FROM `{$this->getTable()}` `ti`
            INNER JOIN (
                    SELECT
                        `ti_inner`.`task_id`,
                        `ti_inner`.`student_id`,
                        MAX(`ti_inner`.`completion_date`) as `completion_date`
                    FROM `{$this->getTable()}` `ti_inner`
                    GROUP BY `ti_inner`.`task_id`, `ti_inner`.`student_id`
                ) `x`
                ON  `ti`.`task_id` = `x`.`task_id`
                AND `ti`.`student_id`       = `x`.`student_id`
                AND `ti`.`completion_date`  = `x`.`completion_date`
        ";
        $where_parts = array(
            'ti.task_id IN '.$this->getArrayPlaceholders($task_id_list, 'ti')
        );
        $params = $this->getArrayParameters($task_id_list, 'ti');
        if (!empty($student_id)) {
            $where_parts[]     = 'ti.student_id = :student';
            $params['student'] = $student_id;
        }
        $where_parts[]            = 'ti.completion_status != :exclude_status';
        $params['exclude_status'] = Completion::COMPLETION_STATUS_INCOMPLETE;

        $statement = $this->getDatabase()->prepare($query . ' WHERE ' . implode(' AND ', $where_parts));
        $statement->execute($params);

        return $this->hydrateList($statement, 'ti');
    }

    /**
     * Pre-save method for saving an task completion object - saves the
     * marking user if this is a new object.
     *
     * @param Completion $object
     */
    public function preSave(Completion $object) {
        if (!$object->getId() && $_SESSION['user'] instanceof User) {
            $object->setMarkingUser($_SESSION['user']);
        }
    }
}

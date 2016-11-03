<?php
/**
 * Model for student's completion of a task.
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2013, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

namespace Schoolbox\Assessment\Course\Task;

use Alaress_Date_Format;
use Schoolbox\Assessment\Course\Task;
use Schoolbox\User;

class Completion {

    const COMPLETION_STATUS_INCOMPLETE            = 1;
    const COMPLETION_STATUS_REQUIRES_CONFIRMATION = 2;
    const COMPLETION_STATUS_COMPLETE              = 3;

    /**
     * @var int
     */
    protected $id;

    /**
     * @var Task
     */
    protected $task;

    /**
     * @var User
     */
    protected $student;

    /**
     * The user who created this completion record, as opposed to the student
     * who it is for.
     *
     * @var User
     */
    protected $marking_user;

    /**
     * @var Alaress_Date_Format
     */
    protected $completion_date;

    /**
     * @var int
     */
    protected $completion_status = self::COMPLETION_STATUS_INCOMPLETE;

    /**
     * @param  \Alaress_Date_Format $completion_date
     * @return $this
     */
    public function setCompletionDate($completion_date) {
        $this->completion_date = $completion_date;
        return $this;
    }

    /**
     * @return \Alaress_Date_Format
     */
    public function getCompletionDate() {
        return $this->completion_date;
    }

    /**
     * @param  int $completion_status
     * @return $this
     */
    public function setCompletionStatus($completion_status) {
        $this->completion_status = $completion_status;
        return $this;
    }

    /**
     * @return int
     */
    public function getCompletionStatus() {
        return $this->completion_status;
    }

    /**
     * @param  int   $id
     * @return $this
     */
    public function setId($id) {
        $this->id = $id;
        return $this;
    }

    /**
     * @return int
     */
    public function getId() {
        return $this->id;
    }

    /**
     * @param  \Schoolbox\User $student
     * @return $this
     */
    public function setStudent($student) {
        $this->student = $student;
        return $this;
    }

    /**
     * @return \Schoolbox\User
     */
    public function getStudent() {
        return $this->student;
    }

    /**
     * @param  \Schoolbox\User $marker
     * @return $this
     */
    public function setMarkingUser($marker) {
        $this->marking_user = $marker;
        return $this;
    }

    /**
     * @return \Schoolbox\User
     */
    public function getMarkingUser() {
        return $this->marking_user;
    }

    /**
     * @param  Task $task
     * @return $this
     */
    public function setTask($task) {
        $this->task = $task;
        return $this;
    }

    /**
     * @return Task
     */
    public function getTask() {
        return $this->task;
    }
}

<?php
/**
 * Model for student's completion of a task.
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2013, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

namespace Schoolbox\Assessment\Course\Task;

use Schoolbox\Assessment\Course\Task;
use Schoolbox\ISoftDeletable;
use Schoolbox\Mapper\Traits\Model\DeletedAtDate;
use Schoolbox\Mapper\Traits\Model\Id;
use Schoolbox\User;

class Completion implements ISoftDeletable
{
    const COMPLETION_STATUS_INCOMPLETE            = 1;
    const COMPLETION_STATUS_REQUIRES_CONFIRMATION = 2;
    const COMPLETION_STATUS_COMPLETE              = 3;

    use Id;
    use DeletedAtDate;

    /**
     * @var Task
     */
    private $task;

    /**
     * The use who this record is for, as opposed to who created the record
     *
     * @var User
     */
    private $student;

    /**
     * The user who created this completion record, as opposed to the student who it is for.
     *
     * @var User
     */
    private $marking_user;

    /**
     * @var int
     */
    private $completion_status = self::COMPLETION_STATUS_INCOMPLETE;


    private $completionDate;


    /* Getters / Setters
     */

    /**
     * @return Task
     */
    public function getTask()
    {
        return $this->task;
    }

    /**
     * @param  Task $task
     * @return static
     */
    public function setTask(Task $task)
    {
        $this->task = $task;
        return $this;
    }

    /**
     * @return User
     */
    public function getStudent()
    {
        return $this->student;
    }

    /**
     * @param  User $student
     * @return static
     */
    public function setStudent(User $student)
    {
        $this->student = $student;
        return $this;
    }

    /**
     * @return User
     */
    public function getMarkingUser()
    {
        return $this->marking_user;
    }

    /**
     * @param  User $marker
     * @return static
     */
    public function setMarkingUser(User $marker)
    {
        $this->marking_user = $marker;
        return $this;
    }

    /**
     * @return \Alaress_Date_Format
     */
    public function getCompletionDate()
    {
        return $this->completionDate;
    }

    /**
     * @param \Alaress_Date_Format $completionDate
     * @return static
     */
    public function setCompletionDate(\Alaress_Date_Format $completionDate)
    {
        $this->completionDate = $completionDate;
        return $this;
    }

    /**
     * @return int
     */
    public function getCompletionStatus()
    {
        return $this->completion_status;
    }

    /**
     * @param  int $completion_status
     * @return static
     */
    public function setCompletionStatus($completion_status)
    {
        $this->completion_status = $completion_status;
        return $this;
    }
}


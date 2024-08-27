<?php

/**
 * Model for student's completion of a task.
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

namespace Schoolbox\Assessment\Course\Task;

use Alaress_Date_Format;
use Schoolbox\Assessment\Course\Task;
use Schoolbox\ISoftDeletable;
use Schoolbox\Mapper\Traits\Model\DeletedAtDate;
use Schoolbox\Mapper\Traits\Model\Id;
use Schoolbox\User;

class Completion implements ISoftDeletable
{
    use Id;
    use DeletedAtDate;

    const STATUS_INCOMPLETE                = 1;
    const STATUS_SELF_ASSESSED_COMPLETE    = 2;
    const STATUS_TEACHER_ASSESSED_COMPLETE = 3;


    /**
     * Maps consts to human-readable equivalents
     */
    public const STATUS_MAP = [
        self::STATUS_INCOMPLETE => 'Incomplete',
        self::STATUS_SELF_ASSESSED_COMPLETE => 'Self-assessed',
        self::STATUS_TEACHER_ASSESSED_COMPLETE => 'Complete'
    ];


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
    private $markingUser;

    /**
     * @var int
     */
    private $completionStatus = self::STATUS_INCOMPLETE;


    private ?Alaress_Date_Format $completionDate = null;


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
        return $this->markingUser;
    }

    /**
     * @param  User $marker
     * @return static
     */
    public function setMarkingUser(User $marker)
    {
        $this->markingUser = $marker;
        return $this;
    }

    /**
     * @return Alaress_Date_Format|null
     */
    public function getCompletionDate(): ?Alaress_Date_Format
    {
        return $this->completionDate;
    }

    /**
     * @param Alaress_Date_Format|null $completionDate
     *
     * @return static
     */
    public function setCompletionDate(?Alaress_Date_Format $completionDate): self
    {
        $this->completionDate = $completionDate;
        return $this;
    }

    /**
     * @return int
     */
    public function getCompletionStatus()
    {
        return $this->completionStatus;
    }

    /**
     * @param  int $completionStatus
     * @return static
     */
    public function setCompletionStatus($completionStatus)
    {
        $this->completionStatus = $completionStatus;
        return $this;
    }
}

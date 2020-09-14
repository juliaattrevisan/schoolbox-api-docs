<?php
/**
 * @copyright Copyright 2020, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */
declare(strict_types=1);

namespace Schoolbox\Assessment\Course\Task\File;

use Alaress_Date_Format;
use Schoolbox\Assessment\Course\Task;
use Schoolbox\Resource\File\IFile;
use Schoolbox\Resource\File\StorableTrait;
use Schoolbox\User;

/**
 * File attached to a task.
 */
class Model implements IFile
{
    use StorableTrait;


    /**
     * @var Task
     */
    private $task;


    /**
     * @return Task
     */
    public function getTask(): Task
    {
        return $this->task;
    }


    /**
     * @param Task $task
     * @return $this
     */
    public function setTask(Task $task): self
    {
        $this->task = $task;
        return $this;
    }


    /**
     * @return User
     */
    public function getOwner()
    {
        return $this->task->getOwner();
    }


    /**
     * @return Alaress_Date_Format
     */
    public function getCreatedAt()
    {
        return $this->storage->getCreatedAt();
    }
}

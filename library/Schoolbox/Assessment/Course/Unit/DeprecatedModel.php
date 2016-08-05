<?php
/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2016, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */

namespace Schoolbox\Assessment\Course\Unit;


use Schoolbox\Assessment\Course\Task;
use Schoolbox\Collection;

/**
 * Class Model
 *
 * @deprecated This model will be replaced with one for an actual unit object.
 *
 * @package Schoolbox\Assessment\Course\Unit
 */
class DeprecatedModel extends \Schoolbox\Resource\Folder\Model
{

    /** @var Collection|Task[] */
    protected $tasks;


    /**
     * @return \Schoolbox\Assessment\Course\Task[]|Collection
     */
    public function getTasks()
    {
        return $this->tasks;
    }


    /**
     * @param \Schoolbox\Assessment\Course\Task[]|Collection $tasks
     * @return DeprecatedModel
     */
    public function setTasks($tasks)
    {
        $this->tasks = $tasks;
        return $this;
    }

}

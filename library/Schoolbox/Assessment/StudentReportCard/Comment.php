<?php

/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Schoolbox\Assessment\StudentReportCard;

use Schoolbox\ISoftDeletable;
use Schoolbox\IModel;
use Schoolbox\Mapper\Traits\Model\CreateCollection;
use Schoolbox\Mapper\Traits\Model\CreatedAtDate;
use Schoolbox\Mapper\Traits\Model\DeletedAtDate;
use Schoolbox\Mapper\Traits\Model\Id;
use Schoolbox\Mapper\Traits\Model\UpdatedAtDate;
use Schoolbox\Resource\Folder\Model as Folder;
use Schoolbox\User;

class Comment implements IModel, ISoftDeletable
{
    use Id;
    use DeletedAtDate;
    use CreatedAtDate;
    use UpdatedAtDate;
    use CreateCollection;

    /** @var User $commenter */
    protected $commenter;

    /** @var User $student */
    protected $student;

    /** @var Folder $category */
    protected $category;

    /** @var string $body */
    protected $body;

    /** @var bool $parent_visibility */
    protected $parent_visibility;

    /** @var bool $student_visibility */
    protected $student_visibility;

    /**
     * @param string $body
     */
    public function setBody($body)
    {
        $this->body = $body;
    }

    /**
     * @return string
     */
    public function getBody()
    {
        return $this->body;
    }

    /**
     * @param \Schoolbox\Resource\Folder\Model $category
     */
    public function setCategory($category)
    {
        $this->category = $category;
    }

    /**
     * @return \Schoolbox\Resource\Folder\Model
     */
    public function getCategory()
    {
        return $this->category;
    }

    /**
     * @param \Schoolbox\User $commenter
     */
    public function setCommenter($commenter)
    {
        $this->commenter = $commenter;
    }

    /**
     * @return \Schoolbox\User
     */
    public function getCommenter()
    {
        return $this->commenter;
    }

    /**
     * @param boolean $parent_visibility
     */
    public function setParentVisibility($parent_visibility)
    {
        $this->parent_visibility = $parent_visibility;
    }

    /**
     * @return boolean
     */
    public function getParentVisibility()
    {
        return $this->parent_visibility;
    }

    /**
     * @return boolean
     */
    public function isVisibleToParent()
    {
        return !!$this->parent_visibility;
    }

    /**
     * @param \Schoolbox\User $student
     */
    public function setStudent($student)
    {
        $this->student = $student;
    }

    /**
     * @return \Schoolbox\User
     */
    public function getStudent()
    {
        return $this->student;
    }

    /**
     * @param boolean $student_visibility
     */
    public function setStudentVisibility($student_visibility)
    {
        $this->student_visibility = $student_visibility;
    }

    /**
     * @return boolean
     */
    public function getStudentVisibility()
    {
        return $this->student_visibility;
    }

    /**
     * @return boolean
     */
    public function isVisibleToStudent()
    {
        return !!$this->student_visibility;
    }
}

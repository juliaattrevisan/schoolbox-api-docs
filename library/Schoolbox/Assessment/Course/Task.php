<?php
/**
 * Model for course tasks.
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2013, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

namespace Schoolbox\Assessment\Course;

use Alaress_Date_Format;
use Schoolbox\ISoftDeletable;
use Schoolbox\Resource\Category;
use Schoolbox\Assessment\Component\SubmissionBox;
use Schoolbox\Assessment\Component\OnlineAssessment;
use Schoolbox\User;

class Task implements ISoftDeletable {

    const TYPE_LINK           = 1;
    const TYPE_STUDENT_ASSESS = 2;
    const TYPE_TEACHER_ASSESS = 3;
    const TYPE_DUE_WORK       = 4;
    const TYPE_ONLINE_ASSESS  = 5;

    /**
     * @var int
     */
    protected $id;

    /**
     * @var Category
     */
    protected $category;

    /**
     * @var TaskType
     */
    protected $type;

    /**
     * @var string
     */
    protected $title;

    /**
     * @var User
     */
    protected $owner;

    /**
     * @var SubmissionBox
     */
    protected $submission_box;

    /**
     * @var OnlineAssessment
     */
    protected $assessment;

    /**
     * @var Alaress_Date_Format
     */
    protected $created;

    /**
     * @var Alaress_Date_Format
     */
    protected $deleted;

    /**
     * @param  \Schoolbox\Resource\Category $category
     * @return $this
     */
    public function setCategory($category) {
        $this->category = $category;
        return $this;
    }

    /**
     * @return \Schoolbox\Resource\Category
     */
    public function getCategory() {
        return $this->category;
    }

    /**
     * @param  \Alaress_Date_Format $created
     * @return $this
     */
    public function setCreated($created) {
        $this->created = $created;
        return $this;
    }

    /**
     * @return \Alaress_Date_Format
     */
    public function getCreated() {
        return $this->created;
    }

    /**
     * @param  \Schoolbox\User $owner
     * @return $this
     */
    public function setOwner($owner) {
        $this->owner = $owner;
        return $this;
    }

    /**
     * @return \Schoolbox\User
     */
    public function getOwner() {
        return $this->owner;
    }

    /**
     * @param  \Alaress_Date_Format $deleted
     * @return $this
     */
    public function setDeleted($deleted) {
        $this->deleted = $deleted;
        return $this;
    }

    /**
     * @return \Alaress_Date_Format
     */
    public function getDeleted() {
        return $this->deleted;
    }

    /**
     * @param  int $id
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
     * @param  string $title
     * @return $this
     */
    public function setTitle($title) {
        $this->title = $title;
        return $this;
    }

    /**
     * @return string
     */
    public function getTitle() {
        return $this->title;
    }

    /**
     * @param  TaskType $type
     * @return $this
     */
    public function setType($type) {
        $this->type = $type;
        return $this;
    }

    /**
     * @return TaskType
     */
    public function getType() {
        return $this->type;
    }

    /**
     * @param  \Schoolbox\Assessment\Component\OnlineAssessment $assessment
     * @return $this
     */
    public function setAssessment($assessment) {
        $this->assessment = $assessment;
        return $this;
    }

    /**
     * @return \Schoolbox\Assessment\Component\OnlineAssessment
     */
    public function getAssessment() {
        return $this->assessment;
    }

    /**
     * @param  \Schoolbox\Assessment\Component\SubmissionBox $submission_box
     * @return $this
     */
    public function setSubmissionBox($submission_box) {
        $this->submission_box = $submission_box;
        return $this;
    }

    /**
     * @return \Schoolbox\Assessment\Component\SubmissionBox
     */
    public function getSubmissionBox() {
        return $this->submission_box;
    }

    /**
     * Is this deleted? (added so there's a consistent method for determining if
     * a model is deleted)
     *
     * @return bool
     */
    public function isDeleted() {
        return ($this->getDeleted() instanceof \Alaress_Date_Format);
    }
}

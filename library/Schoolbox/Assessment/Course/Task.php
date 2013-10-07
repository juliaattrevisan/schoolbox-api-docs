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

    const TYPE_TASK          = 1;
    const TYPE_DUE_WORK      = 2;
    const TYPE_ONLINE_ASSESS = 3;

    /**
     * @var int
     */
    protected $id;

    /**
     * @var Category
     */
    protected $category;

    /**
     * @var string
     */
    protected $title;

    /**
     * @var string
     */
    protected $link;

    /**
     * @var bool
     */
    protected $requires_confirmation;

    /**
     * @var int
     */
    protected $order;

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
     * @param  string $link
     * @return $this
     */
    public function setLink($link) {
        $this->link = $link;
        return $this;
    }

    /**
     * @return string
     */
    public function getLink() {
        return $this->link;
    }

    /**
     * @param  bool  $requires_confirmation
     * @return $this
     */
    public function setRequiresConfirmation($requires_confirmation) {
        $this->requires_confirmation = $requires_confirmation;
        return $this;
    }

    /**
     * @return bool
     */
    public function getRequiresConfirmation() {
        return $this->requires_confirmation;
    }

    /**
     * @param  int   $order
     * @return $this
     */
    public function setOrder($order) {
        $this->order = $order;
        return $this;
    }

    /**
     * @return int
     */
    public function getOrder() {
        return $this->order;
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

    public function getType() {
        if ($this->getAssessment()->getId()) {
            return self::TYPE_ONLINE_ASSESS;
        }
        if ($this->getSubmissionBox()->getId()) {
            return self::TYPE_DUE_WORK;
        }
        return self::TYPE_TASK;
    }

    /**
     * Temp method for getting this task's icon class.
     *
     * @todo move this to a renderer class at some point.
     *
     * @return string
     */
    public function getIconClass() {
        switch ($this->getType()) {
            case self::TYPE_DUE_WORK:
                return 'new';
            case self::TYPE_ONLINE_ASSESS:
                return 'onlineassessment';
            default:
                break;
        }
        return 'tick';
    }

    /**
     * Temp method for getting this task's icon class.
     *
     * @todo move this back to lookup table?
     *
     * @return string
     */
    public function getLabel() {
        switch ($this->getType()) {
            case self::TYPE_DUE_WORK:
                return 'Due Work';
            case self::TYPE_ONLINE_ASSESS:
                return 'Online Assessment';
            default:
                break;
        }
        return 'Task';
    }

}

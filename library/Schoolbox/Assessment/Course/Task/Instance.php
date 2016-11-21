<?php
/**
 * Model for task instances.
 *
 * @deprecated Task instances have been migrated to the base task table.
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2013, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

namespace Schoolbox\Assessment\Course\Task;

use Alaress_Date_Format;
use Schoolbox\Collection;
use Schoolbox\User;
use Schoolbox\ISoftDeletable;
use Schoolbox\Assessment\Component\SubmissionBox;
use Schoolbox\Learning\Quiz\Model as Quiz;
use Schoolbox\Assessment\Course\Task;
use Schoolbox\Assessment\Course\UnitInstance;

class Instance implements ISoftDeletable {

    /**
     * @var int
     */
    protected $id;

    /**
     * @var Task
     */
    protected $task;

    /**
     * @var UnitInstance
     */
    protected $unit_instance;

    /**
     * @var Collection|Completion[]
     */
    protected $completion_list;

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
     * @var Quiz
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

    public function __construct() {
        $this->completion_list = new Collection();
    }

    /**
     * @param  \Schoolbox\Assessment\Course\Task $task
     * @return $this
     */
    public function setTask($task) {
        $this->task = $task;
        return $this;
    }

    /**
     * @return \Schoolbox\Assessment\Course\Task
     */
    public function getTask() {
        return $this->task;
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
     * @param  \Schoolbox\Assessment\Course\UnitInstance $unit_instance
     * @return $this
     */
    public function setUnitInstance($unit_instance) {
        $this->unit_instance = $unit_instance;
        return $this;
    }

    /**
     * @return \Schoolbox\Assessment\Course\UnitInstance
     */
    public function getUnitInstance() {
        return $this->unit_instance;
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
     * @param  Quiz $assessment
     * @return $this
     */
    public function setAssessment($assessment) {
        $this->assessment = $assessment;
        return $this;
    }

    /**
     * @return Quiz
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
     * @param  Completion[]|Collection $completion_list
     * @return $this
     */
    public function setCompletionList($completion_list) {
        $this->completion_list = $completion_list;
        return $this;
    }

    /**
     * @return Completion[]|Collection
     */
    public function getCompletionList() {
        return $this->completion_list;
    }

    /**
     * Is this deleted? (added so there's a consistent method for determining if
     * a model is deleted)
     *
     * @return bool
     */
    public function isDeleted() {
        return ($this->getDeleted() instanceof \Alaress_Date_Format && !$this->getDeleted()->isNull());
    }
}

<?php
/**
 * Model for task instances.
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
use Schoolbox\Assessment\Component\OnlineAssessment;
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
     * @var Collection|InstanceCompletion[]
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
     * @param  InstanceCompletion[]|Collection $completion_list
     * @return $this
     */
    public function setCompletionList($completion_list) {
        $this->completion_list = $completion_list;
        return $this;
    }

    /**
     * @return InstanceCompletion[]|Collection
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

    /**
     * Copied from task mapper
     *
     * @return int
     */
    public function getType() {
        if ($this->getAssessment()->getId()) {
            return Task::TYPE_ONLINE_ASSESS;
        }
        if ($this->getSubmissionBox()->getId()) {
            return Task::TYPE_DUE_WORK;
        }
        return Task::TYPE_TASK;
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
            case Task::TYPE_DUE_WORK:
                return 'icon-due-work';
            case Task::TYPE_ONLINE_ASSESS:
                return 'icon-quiz';
            default:
                break;
        }
        return 'icon-task';
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
            case Task::TYPE_DUE_WORK:
                return 'Due Work';
            case Task::TYPE_ONLINE_ASSESS:
                return 'Quiz';
            default:
                break;
        }
        return 'Task';
    }

}

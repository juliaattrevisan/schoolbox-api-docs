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
use Component_Instance;
use Schoolbox\Collection;
use Schoolbox\ISoftDeletable;
use Schoolbox\Resource\Folder\Model as Folder;

class UnitInstance implements ISoftDeletable {

    /**
     * @var int
     */
    protected $id;

    /**
     * @var Component_Instance
     */
    protected $component;

    /**
     * @var Folder
     */
    protected $category;

    /**
     * @var Folder
     */
    protected $unit_category;

    /**
     * @var Collection|Task\Instance[]
     */
    protected $tasks;

    /**
     * @var string
     */
    protected $title;

    /**
     * @var Alaress_Date_Format
     */
    protected $open_date;

    /**
     * @var Alaress_Date_Format
     */
    protected $due_date;

    /**
     * @var Alaress_Date_Format
     */
    protected $created;

    /**
     * @var Alaress_Date_Format
     */
    protected $deleted;

    /**
     * @var array
     */
    protected $workDates;

    /**
     * @var array
     */
    protected $quizDates;


    /**
     * UnitInstance constructor.
     */
    public function __construct() {
        $this->tasks = new Collection();
    }

    /**
     * @param  \Component_Instance $component
     * @return self
     */
    public function setComponent($component) {
        $this->component = $component;
        return $this;
    }

    /**
     * @return \Component_Instance
     */
    public function getComponent() {
        return $this->component;
    }

    /**
     * @param  \Alaress_Date_Format $created
     * @return self
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
     * @param  \Alaress_Date_Format $deleted
     * @return self
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
     * @param  \Alaress_Date_Format $due_date
     * @return self
     */
    public function setDueDate($due_date) {
        $this->due_date = $due_date;
        return $this;
    }

    /**
     * @return \Alaress_Date_Format
     */
    public function getDueDate() {
        return $this->due_date;
    }

    /**
     * @param  int   $id
     * @return self
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
     * @param  \Alaress_Date_Format $open_date
     * @return self
     */
    public function setOpenDate($open_date) {
        $this->open_date = $open_date;
        return $this;
    }

    /**
     * @return \Alaress_Date_Format
     */
    public function getOpenDate() {
        return $this->open_date;
    }

    /**
     * @param  string $title
     * @return self
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
     * @param  \Schoolbox\Resource\Folder\Model $category
     * @return $this
     */
    public function setCategory($category) {
        $this->category = $category;
        return $this;
    }

    /**
     * @return \Schoolbox\Resource\Folder\Model
     */
    public function getCategory() {
        return $this->category;
    }

    /**
     * @param  \Schoolbox\Resource\Folder\Model $unit_category
     * @return $this
     */
    public function setUnitCategory($unit_category) {
        $this->unit_category = $unit_category;
        return $this;
    }

    /**
     * @return \Schoolbox\Resource\Folder\Model
     */
    public function getUnitCategory() {
        return $this->unit_category;
    }

    /**
     * @param  Collection $tasks
     * @return self
     */
    public function setTasks($tasks) {
        $this->tasks = $tasks;
        return $this;
    }

    /**
     * @return Task\Instance[]|Collection
     */
    public function getTasks() {
        return $this->tasks;
    }

    /**
     * {@inheritdoc}
     */
    public function isDeleted() {
        return ($this->getDeleted() instanceof \Alaress_Date_Format && !$this->getDeleted()->isNull());
    }

    /**
     * This unit is open if the current date is after the open date, and before
     * the due date.
     *
     * @return bool
     */
    public function isOpen() {
        return !($this->isBeforeOpenDate() || $this->isAfterDueDate());
    }

    /**
     * Is this unit open for submissions?
     *
     * @return bool
     */
    public function isBeforeOpenDate() {
        $date = new Alaress_Date_Format(new \DateTime());
        return $date->compareDateOnly($this->getOpenDate()) < 0;
    }

    /**
     * Is this unit open for submissions?
     *
     * @return bool
     */
    public function isAfterDueDate() {
        $date = new Alaress_Date_Format(new \DateTime());
        return $date->compareDateOnly($this->getDueDate()) > 0;
    }

    /**
     * Retrieves a time relative to whenever the course unit opens.
     *
     * It opens on 12am of the open date.
     *
     * @return Alaress_Date_Format
     */
    public function getRelativeOpenDate() {
        $date = clone $this->getOpenDate()->getDateTimeObject();
        $date->modify('00:00:00');
        return new Alaress_Date_Format($date);
    }

    /**
     * Retrieves a time relative to whenever the course unit closes.
     *
     * It closes on the last second of the due date.
     *
     * @return Alaress_Date_Format
     */
    public function getRelativeDueDate() {
        $date = clone $this->getDueDate()->getDateTimeObject();
        $date->modify('23:59:59');
        return new Alaress_Date_Format($date);
    }

    /**
     * Get array of dates for work. Used to override default dates when saving
     *
     * @return array
     */
    public function getWorkDates() {
        return $this->workDates;
    }

    /**
     * Get array of dates for quizzes. Used to override default dates when saving
     *
     * @return array
     */
    public function getQuizDates() {
        return $this->quizDates;
    }

    /**
     * Set array of dates for work. Used to override default dates when saving
     *
     * @param array $workDates
     * @return self
     */
    public function setWorkDates($workDates) {
        $this->workDates = $workDates;
        return $this;
    }

    /**
     * Set array of dates for quizzes. Used to override default dates when saving
     *
     * @param array $quizDates
     * @return self
     */
    public function setQuizDates($quizDates) {
        $this->quizDates = $quizDates;
        return $this;
    }

}

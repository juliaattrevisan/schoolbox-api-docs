<?php
/**
 * Model for rubric descriptors
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2013, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

namespace Schoolbox\Assessment\Component\SubmissionBox\Rubric;

use Schoolbox\Assessment\Component\SubmissionBox;
use Schoolbox\ISoftDeletable;

class Descriptor implements ISoftDeletable
{
    /**
     * @var int
     */
    protected $id;

    /**
     * @var Criterion
     */
    protected $criterion;

    /**
     * @var SubmissionBox
     */
    protected $submission_box;

    /** @var Level */
    protected $level;

    /**
     * @var int
     */
    protected $value;

    /**
     * @var string
     */
    protected $description;

    /**
     * @var bool
     */
    protected $valid;

    /**
     * @return int
     */
    public function getId()
    {
        return $this->id;
    }

    /**
     * @param int $id
     * @return static
     */
    public function setId($id)
    {
        $this->id = $id;
        return $this;
    }

    /**
     * @param  Criterion $criterion
     * @return $this
     */
    public function setCriterion($criterion) {
        $this->criterion = $criterion;
        return $this;
    }

    /**
     * @return Criterion
     */
    public function getCriterion() {
        return $this->criterion;
    }

    /**
     * @param  string $description
     * @return $this
     */
    public function setDescription($description) {
        $this->description = $description;
        return $this;
    }

    /**
     * @return string
     */
    public function getDescription() {
        return $this->description;
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
     * @return Level
     */
    public function getLevel()
    {
        return $this->level;
    }


    /**
     * @param Level $level
     * @return static
     */
    public function setLevel($level)
    {
        $this->level = $level;
        return $this;
    }


    /**
     * @param  boolean $valid
     * @return $this
     */
    public function setValid($valid) {
        $this->valid = $valid;
        return $this;
    }

    /**
     * @return boolean
     */
    public function getValid() {
        return $this->valid;
    }

    /**
     * @param  int   $value
     * @return $this
     */
    public function setValue($value) {
        $this->value = $value;
        return $this;
    }

    /**
     * @return int
     */
    public function getValue() {
        return $this->value;
    }

    /**
     * @return bool
     */
    public function isDeleted()
    {
        return !$this->getValid();
    }
}

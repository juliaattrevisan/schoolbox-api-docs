<?php
/**
 * Model for rubric criteria.
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2013, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

namespace Schoolbox\Assessment\Component\SubmissionBox\Rubric;

use Schoolbox\ISoftDeletable;
use Alaress_Date_Format;

class Criterion implements ISoftDeletable {

    const PARENT_TYPE_CATEGORY   = 'category';
    const PARENT_TYPE_SUBMISSION = 'submission';
    const PARENT_TYPE_CURRICULUM = 'curriculum';

    /**
     * @var int
     */
    protected $id;

    /**
     * @var string
     */
    protected $grouping;

    /**
     * @var string
     */
    protected $name;

    /**
     * @var int
     */
    protected $parent_id;

    /**
     * @var string
     */
    protected $parent_type = self::PARENT_TYPE_CATEGORY;

    /**
     * @var Alaress_Date_Format
     */
    protected $deletedAt;

    /**
     * @param  string $grouping
     * @return $this
     */
    public function setGrouping($grouping) {
        $this->grouping = $grouping;
        return $this;
    }

    /**
     * @return string
     */
    public function getGrouping() {
        return $this->grouping;
    }

    /**
     * @param  int   $id
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
     * @param  string $name
     * @return $this
     */
    public function setName($name) {
        $this->name = $name;
        return $this;
    }

    /**
     * @return string
     */
    public function getName() {
        return $this->name;
    }

    /**
     * @param  int   $parent_id
     * @return $this
     */
    public function setParentId($parent_id) {
        $this->parent_id = $parent_id;
        return $this;
    }

    /**
     * @return int
     */
    public function getParentId() {
        return $this->parent_id;
    }

    /**
     * @param  string $parent_type
     * @return $this
     */
    public function setParentType($parent_type) {
        $this->parent_type = $parent_type;
        return $this;
    }

    /**
     * @return string
     */
    public function getParentType() {
        return $this->parent_type;
    }

    /**
     * @param  Alaress_Date_Format $deletedAt
     * @return $this
     */
    public function setDeletedAt($deletedAt) {
        $this->deletedAt = $deletedAt;
        return $this;
    }

    /**
     * @return Alaress_Date_Format
     */
    public function getDeletedAt() {
        return $this->deletedAt;
    }

    /**
     * @return bool
     */
    public function isDeleted() {
        return ($this->getDeletedAt() instanceof Alaress_Date_Format && !$this->getDeletedAt()->isNull());
    }

}

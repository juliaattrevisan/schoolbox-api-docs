<?php
/**
 *
 * @author      Damian Sloane <damian.sloane@alaress.com.au>
 * @copyright   Copyright 2016, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace Schoolbox\Pastoral\Severity;

use Schoolbox\ISoftDeletable;
use Schoolbox\User;

class Model implements ISoftDeletable {

    /** @var int */
    protected $id;

    /** @var string */
    protected $name;

    /** @var int */
    protected $order;

    /** @var \Alaress_Date_Format */
    protected $deletedAt;

    /**
     * @param string $name
     * @return self
     */
    public function setName($name) {
        $this->name = $name;
        return $name;
    }

    /**
     * @return string
     */
    public function getName() {
        return $this->name;
    }

    /**
     * @param int $id
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
     * @param int $order
     * @return self
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
     * @param \Alaress_Date_Format $deletedAt
     * @return self
     */
    public function setDeletedAt($deletedAt) {
        $this->deletedAt = $deletedAt;
        return $this;
    }

    /**
     * @return \Alaress_Date_Format $deletedAt
     */
    public function getDeletedAt() {
        return $this->deletedAt;
    }

    /**
     * Is this object deleted?
     *
     * @return bool
     */
    public function isDeleted() {
        return !$this->getDeletedAt() instanceof \Alaress_Date_NullDate;
    }
}
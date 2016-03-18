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
    protected $datetimeDeleted;

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
     * @param \Alaress_Date_Format $datetimeDeleted
     * @return self
     */
    public function setDatetimeDeleted($datetimeDeleted) {
        $this->datetimeDeleted = $datetimeDeleted;
        return $this;
    }

    /**
     * @return \Alaress_Date_Format $datetimeDeleted
     */
    public function getDatetimeDeleted() {
        return $this->datetimeDeleted;
    }

    /**
     * Is this object deleted?
     *
     * @return bool
     */
    public function isDeleted() {
        return !$this->getDatetimeDeleted() instanceof \Alaress_Date_NullDate;
    }
}
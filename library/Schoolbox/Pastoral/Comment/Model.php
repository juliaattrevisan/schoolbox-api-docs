<?php
/**
 *
 * @author      Damian Sloane <damian.sloane@alaress.com.au>
 * @copyright   Copyright 2016, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace Schoolbox\Pastoral\Comment;

use Schoolbox\ISoftDeletable;
use Schoolbox\User;

class Model implements ISoftDeletable {

    /** @var int */
    protected $id;

    /** @var int */
    protected $pastoralId;

    /** @var string */
    protected $body;

    /** @var \Alaress_Date_Format */
    protected $datetimePosted;

    /** @var \Alaress_Date_Format */
    protected $datetimeModified;

    /** @var \Alaress_Date_Format */
    protected $datetimeDeleted;

    /** @var User */
    protected $owner;

    /**
     * @param string $pastoralId
     * @return self
     */
    public function setPastoralId($pastoralId) {
        $this->pastoralId = $pastoralId;
        return $pastoralId;
    }

    /**
     * @return int
     */
    public function getPastoralId() {
        return $this->pastoralId;
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
     * @param string $body
     * @return self
     */
    public function setBody($body) {
        $this->body = $body;
        return $this;
    }

    /**
     * @return string
     */
    public function getBody() {
        return $this->body;
    }

    /**
     * @param \Schoolbox\User $owner
     * @return self
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
     * @param \Alaress_Date_Format $datetimeDeleted
     * @return self
     */
    public function setDatetimeDeleted($datetimeDeleted) {
        $this->datetimeDeleted = $datetimeDeleted;
        return $this;
    }

    /**
     * @return \Alaress_Date_Format $modifiedDate
     */
    public function getDatetimeDeleted() {
        return $this->datetimeDeleted;
    }

    /**
     * @param \Alaress_Date_Format $datetimeModified
     * @return self
     */
    public function setDatetimeModified($datetimeModified) {
        $this->datetimeModified = $datetimeModified;
        return $this;
    }

    /**
     * @return \Alaress_Date_Format $modifiedDate
     */
    public function getDatetimeModified() {
        return $this->datetimeModified;
    }

    /**
     * @param \Alaress_Date_Format $datetimePosted
     * @return self
     */
    public function setDatetimePosted($datetimePosted) {
        $this->datetimePosted = $datetimePosted;
        return $this;
    }

    /**
     * @return \Alaress_Date_Format $modifiedDate
     */
    public function getDatetimePosted() {
        return $this->datetimePosted;
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

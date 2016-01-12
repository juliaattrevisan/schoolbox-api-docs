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
    protected $postDate;

    /** @var \Alaress_Date_Format */
    protected $modifiedDate;

    /** @var \Alaress_Date_Format */
    protected $deletedDate;

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
     * @param \Alaress_Date_Format $modifiedDate
     * @return self
     */
    public function setDeletedDate($deletedDate) {
        $this->deletedDate = $deletedDate;
        return $this;
    }

    /**
     * @return \Alaress_Date_Format $modifiedDate
     */
    public function getDeletedDate() {
        return $this->deletedDate;
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
     * Is this object deleted?
     *
     * @return bool
     */
    public function isDeleted() {
        return $this->getDeletedDate() != null;
    }

    /**
     * @param \Alaress_Date_Format $modifiedDate
     * @return self
     */
    public function setModifiedDate($modifiedDate) {
        $this->modifiedDate = $modifiedDate;
        return $this;
    }

    /**
     * @return \Alaress_Date_Format
     */
    public function getModifiedDate() {
        return $this->modifiedDate;
    }


    /**
     * @param \Alaress_Date_Format $postDate
     * @return self
     */
    public function setPostDate($postDate) {
        $this->postDate = $postDate;
        return $this;
    }

    /**
     * @return \Alaress_Date_Format
     */
    public function getPostDate() {
        return $this->postDate;
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

}

<?php
/**
 *
 * @author      Damian Sloane <damian.sloane@alaress.com.au>
 * @copyright   Copyright 2016, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace Schoolbox\Pastoral\Pastoral;

use Schoolbox\ISoftDeletable;
use Schoolbox\User;
use Scrub;

class Model implements ISoftDeletable {

    /** @var int */
    protected $id;

    /** @var int */
    protected $typeId;

    /** @var int */
    protected $severityId;

    /** @var int */
    protected $externalId;

    /** @var string */
    protected $typeName;

    /** @var string */
    protected $severityName;

    /** @var int */
    protected $studentId;

    /** @var string */
    protected $heading;

    /** @var \Alaress_Date_Format */
    protected $postDate;

    /** @var \Alaress_Date_Format */
    protected $modifiedDate;

    /** @var string */
    protected $body;

    /** @var \Alaress_Date_Format */
    protected $deletedDate;

    /** @var bool */
    protected $private;

    /** @var User */
    protected $owner;

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
     * @param boolean $private
     * @return self
     */
    public function setPrivate($private) {
        $this->private = $private;
        return $this;
    }

    /**
     * @return boolean
     */
    public function getPrivate() {
        return $this->private;
    }

    /**
     * @return string
     */
    public function getBodyText() {
        return Scrub::html2text($this->body);
    }

    /**
     * @param \Alaress_Date_Format $deletedDate
     * @return self
     */
    public function setDeletedDate($deletedDate) {
        $this->deletedDate = deletedDate;
        return $this;
    }

    /**
     * @return \Alaress_Date_Format
     */
    public function getDeletedDate() {
        return $this->deletedDate;
    }

    /**
     * @param string $heading
     * @return self
     */
    public function setHeading($heading) {
        $this->heading = $heading;
        return $this;
    }

    /**
     * @return string
     */
    public function getHeading() {
        return $this->heading;
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
     * @param int $externalId
     * @return self
     */
    public function setExternalId($externalId) {
        $this->externalId = $externalId;
        return $this;
    }

    /**
     * @return int
     */
    public function getExternalId() {
        return $this->externalId;
    }

    /**
     * @param int $typeId
     * @return self
     */
    public function setTypeId($typeId) {
        $this->typeId = $typeId;
        return $this;
    }

    /**
     * @return int
     */
    public function getTypeId() {
        return $this->typeId;
    }

    /**
     * @param int $severityId
     * @return self
     */
    public function setSeverityId($severityId) {
        $this->severityId = $severityId;
        return $this;
    }

    /**
     * @return int
     */
    public function getSeverityId() {
        return $this->severityId;
    }

    /**
     * @param string $typeName
     * @return self
     */
    public function setTypeName($typeName) {
        $this->typeName = $typeName;
        return $this;
    }

    /**
     * @return string
     */
    public function getTypeName() {
        return $this->typeName;
    }

    /**
     * @param string $severityName
     * @return self
     */
    public function setSeverityName($severityName) {
        $this->severityName = $severityName;
        return $this;
    }

    /**
     * @return string
     */
    public function getSeverityName() {
        return $this->severityName;
    }

    /**
     * @param int $studentId
     * @return self
     */
    public function setStudentId($studentId) {
        $this->studentId = $studentId;
        return $this;
    }

    /**
     * @return int
     */
    public function getStudentId() {
        return $this->studentId;
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
     * Is this object deleted?
     *
     * @return bool
     */
    public function isDeleted() {
        return $this->getDeletedDate() != null;
    }
}

<?php
/**
 *
 * @author      Damian Sloane <damian.sloane@alaress.com.au>
 * @copyright   Copyright 2016, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace Schoolbox\Pastoral\Pastoral;

use Schoolbox\Pastoral;
use Schoolbox\Collection;
use Schoolbox\ISoftDeletable;
use Schoolbox\User;
use Scrub;

class Model implements ISoftDeletable
{
    /** @var int */
    protected $id;

    /** @var int */
    protected $externalId;

    /** @var Model */
    protected $parent;

    /** @var Pastoral\Type\Model */
    protected $type;

    /** @var Pastoral\Severity\Model */
    protected $severity;

    /** @var \Schoolbox\User */
    protected $student;

    /** @var string */
    protected $heading;

    /** @var string */
    protected $body;

    /** @var bool */
    protected $private;

    /** @var User */
    protected $owner;

    /** @var Collection|Pastoral\Access\Model[] */
    protected $accessList;

    /** @var Collection|Pastoral\Comment\Model[] */
    protected $commentList;

    /** @var Collection|Pastoral\Attachment\Model[] */
    protected $attachmentList;

    /** @var Collection|Pastoral\Tags\Model[] */
    protected $tagsList;

    /** @var Collection|Model[] */
    protected $relatedRecords;

    /** @var \Alaress_Date_Format */
    protected $createdAt;

    /** @var \Alaress_Date_Format */
    protected $updatedAt;

    /** @var \Alaress_Date_Format */
    protected $deletedAt;

    /**
     * @param  Collection|Pastoral\Access\Model[] $accessList
     * @return $this
     */
    public function setAccessList($accessList)
    {
        $this->accessList = $accessList;
        return $this;
    }

    /**
     * @return Collection|Pastoral\Access\Model[]
     */
    public function getAccessList()
    {
        return $this->accessList;
    }

    /**
     * @param  Collection|Pastoral\Tags\Model[] $tagsList
     * @return $this
     */
    public function setTagsList($tagsList)
    {
        $this->tagsList = $tagsList;
        return $this;
    }

    /**
     * @return Collection|Pastoral\Tags\Model[]
     */
    public function getTagsList()
    {
        return $this->tagsList;
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
     * @return string
     */
    public function getBodyText() {
        return Scrub::html2text($this->body);
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
     * @param Collection|Pastoral\Comment\Model[] $commentList
     * @return self
     */
    public function setCommentList($commentList) {
        $this->commentList = $commentList;
        return $this;
    }

    /**
     * @return Collection|Pastoral\Comment\Model[]
     */
    public function getCommentList() {
        return $this->commentList;
    }

    /**
     * @return Collection|Pastoral\Attachment\Model[]
     */
    public function getAttachmentList() {
        return $this->attachmentList;
    }

    /**
     * @param Collection|Pastoral\Attachment\Model[] $attachmentList
     * @return self
     */
    public function setAttachmentList($attachmentList) {
        $this->attachmentList = $attachmentList;
        return $this;
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
     * @param Pastoral\Type\Model $type
     * @return self
     */
    public function setType($type) {
        $this->type = $type;
        return $this;
    }

    /**
     * @return Pastoral\Type\Model
     */
    public function getType() {
        return $this->type;
    }

    /**
     * @param Model $parent
     * @return self
     */
    public function setParent($parent) {
        $this->parent = $parent;
        return $this;
    }

    /**
     * @return Model
     */
    public function getParent() {
        return $this->parent;
    }

    /**
     * @param Pastoral\Severity\Model $severity
     * @return self
     */
    public function setSeverity($severity) {
        $this->severity = $severity;
        return $this;
    }

    /**
     * @return Pastoral\Severity\Model
     */
    public function getSeverity() {
        return $this->severity;
    }

    /**
     * @param User $student
     * @return self
     */
    public function setStudent($student) {
        $this->student = $student;
        return $this;
    }

    /**
     * @return User
     */
    public function getStudent() {
        return $this->student;
    }

    /**
     * @param User $owner
     * @return self
     */
    public function setOwner($owner) {
        $this->owner = $owner;
        return $this;
    }

    /**
     * @return User
     */
    public function getOwner() {
        return $this->owner;
    }

    /**
     * @param \Alaress_Date_Format $updatedAt
     * @return self
     */
    public function setUpdatedAt($updatedAt) {
        $this->updatedAt = $updatedAt;
        return $this;
    }

    /**
     * @return \Alaress_Date_Format
     */
    public function getUpdatedAt() {
        return $this->updatedAt;
    }

    /**
     * @param \Alaress_Date_Format $createdAt
     * @return self
     */
    public function setCreatedAt($createdAt) {
        $this->createdAt = $createdAt;
        return $this;
    }

    /**
     * @return \Alaress_Date_Format
     */
    public function getCreatedAt() {
        return $this->createdAt;
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
     * @return \Alaress_Date_Format
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

    /**
     * @param Collection|Model
     */
    public function setRelatedRecords($relatedRecords) {
        $this->relatedRecords = $relatedRecords;
    }

    /**
     * @return Collection|Model
     */
    public function getRelatedRecords() {
        return $this->relatedRecords;
    }
}

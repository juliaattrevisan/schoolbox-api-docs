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

    /** @var int */
    protected $parentId;

    /** @var Pastoral\Type\Model */
    protected $type;

    /** @var Pastoral\Severity\Model */
    protected $severity;

    /** @var \Schoolbox\User */
    protected $student;

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

    /** @var Collection|Pastoral\Access\Model[] */
    protected $accessList;

    /** @var Collection|Pastoral\Comment\Model[] */
    protected $commentList;

    /** @var Collection|Pastoral\Attachment\Model[] */
    protected $attachmentList;

    /** @var Collection|Pastoral\Tags\Model[] */
    protected $tagsList;

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
        $this->deletedDate = $deletedDate;
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
     * @param int $parentId
     * @return self
     */
    public function setParentId($parentId) {
        $this->parentId = $parentId;
        return $this;
    }

    /**
     * @return int
     */
    public function getParentId() {
        return $this->parentId;
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
        return !($this->getDeletedDate() instanceof \Alaress_Date_NullDate);
    }
}

<?php
/**
 * @author      Damian Sloane <damian.sloane@alaress.com.au>
 * @copyright   Copyright 2016, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */

namespace Schoolbox\Pastoral;

use Schoolbox\Mapper\Traits\Model\CreatedAtDate;
use Schoolbox\Mapper\Traits\Model\DeletedAtDate;
use Schoolbox\Mapper\Traits\Model\Id;
use Schoolbox\Mapper\Traits\Model\UpdatedAtDate;
use Schoolbox\Collection;
use Schoolbox\ISoftDeletable;
use Schoolbox\Pastoral\Category\Model as PastoralCategory;
use Schoolbox\Pastoral\Severity\Model as PastoralSeverity;
use Schoolbox\Pastoral\Type\Model as PastoralType;
use Schoolbox\User;

class Model implements ISoftDeletable
{
    use Id;
    use CreatedAtDate;
    use UpdatedAtDate;
    use DeletedAtDate;

    /** @var int */
    protected $externalId;

    /** @var Model */
    protected $parent;

    /** @var PastoralType */
    protected $type;

    /** @var PastoralCategory */
    protected $category;

    /** @var PastoralSeverity */
    protected $severity;

    /** @var User */
    protected $student;

    /** @var string */
    protected $heading;

    /** @var string */
    protected $body;

    /** @var float */
    protected $points;

    /** @var bool */
    protected $private;

    /** @var User */
    protected $owner;

    /** @var User */
    protected $modifiedBy;

    /** @var Collection */
    protected $access;

    /** @var Collection */
    protected $comments;

    /** @var Collection */
    protected $attachments;

    /** @var Collection */
    protected $tags;

    /** @var Collection */
    protected $relatedRecords;

    /** @var Collection */
    protected $revisions;

    /**
     * @param  Collection $access
     * @return $this
     */
    public function setAccess($access)
    {
        $this->access = $access;
        return $this;
    }

    /**
     * @return Collection
     */
    public function getAccess()
    {
        return $this->access;
    }

    /**
     * @param  Collection $tags
     * @return $this
     */
    public function setTags($tags)
    {
        $this->tags = $tags;
        return $this;
    }

    /**
     * @return Collection
     */
    public function getTags()
    {
        return $this->tags;
    }

    /**
     * @param boolean $private
     * @return self
     */
    public function setPrivate($private)
    {
        $this->private = $private;
        return $this;
    }

    /**
     * @return boolean
     */
    public function getPrivate()
    {
        return $this->private;
    }

    /**
     * @param string $body
     * @return self
     */
    public function setBody($body)
    {
        $this->body = $body;
        return $this;
    }

    /**
     * @return string
     */
    public function getBody()
    {
        return $this->body;
    }

    /**
     * @param string $heading
     * @return self
     */
    public function setHeading($heading)
    {
        $this->heading = $heading;
        return $this;
    }

    /**
     * @return string
     */
    public function getHeading()
    {
        return $this->heading;
    }

    /**
     * @param float $points
     * @return self
     */
    public function setPoints($points)
    {
        $this->points = $points;
        return $this;
    }

    /**
     * @return float
     */
    public function getPoints()
    {
        return $this->points;
    }

    /**
     * @param Collection $comments
     * @return self
     */
    public function setComments($comments)
    {
        $this->comments = $comments;
        return $this;
    }

    /**
     * @return Collection
     */
    public function getComments()
    {
        return $this->comments;
    }

    /**
     * @return Collection
     */
    public function getAttachments()
    {
        return $this->attachments;
    }

    /**
     * @param Collection $attachments
     * @return self
     */
    public function setAttachments($attachments)
    {
        $this->attachments = $attachments;
        return $this;
    }

    /**
     * @param int $externalId
     * @return self
     */
    public function setExternalId($externalId)
    {
        $this->externalId = $externalId;
        return $this;
    }

    /**
     * @return int
     */
    public function getExternalId()
    {
        return $this->externalId;
    }

    /**
     * @param PastoralType $type
     * @return self
     */
    public function setType($type)
    {
        $this->type = $type;
        return $this;
    }

    /**
     * @return PastoralType
     */
    public function getType()
    {
        return $this->type;
    }

    /**
     * @param PastoralCategory $category
     * @return self
     */
    public function setCategory($category)
    {
        $this->category = $category;
        return $this;
    }

    /**
     * @return PastoralCategory
     */
    public function getCategory()
    {
        return $this->category;
    }

    /**
     * @param Model $parent
     * @return self
     */
    public function setParent($parent)
    {
        $this->parent = $parent;
        return $this;
    }

    /**
     * @return Model
     */
    public function getParent()
    {
        return $this->parent;
    }

    /**
     * @param PastoralSeverity $severity
     * @return self
     */
    public function setSeverity($severity)
    {
        $this->severity = $severity;
        return $this;
    }

    /**
     * @return PastoralSeverity
     */
    public function getSeverity()
    {
        return $this->severity;
    }

    /**
     * @param User $student
     * @return self
     */
    public function setStudent($student)
    {
        $this->student = $student;
        return $this;
    }

    /**
     * @return User
     */
    public function getStudent()
    {
        return $this->student;
    }

    /**
     * @param User $owner
     * @return self
     */
    public function setOwner($owner)
    {
        $this->owner = $owner;
        return $this;
    }

    /**
     * @return User
     */
    public function getOwner()
    {
        return $this->owner;
    }

    /**
     * @param User $modifiedBy
     * @return self
     */
    public function setModifiedBy($modifiedBy)
    {
        $this->modifiedBy = $modifiedBy;
        return $this;
    }

    /**
     * @return User
     */
    public function getModifiedBy()
    {
        return $this->modifiedBy;
    }    

    /**
     * @param Collection|Model
     */
    public function setRelatedRecords($relatedRecords)
    {
        $this->relatedRecords = $relatedRecords;
    }

    /**
     * @return Collection|Model
     */
    public function getRelatedRecords()
    {
        return $this->relatedRecords;
    }

    /**
     * @return Collection
     */
    public function getRevisions() {
        return $this->revisions;
    }

    /**
     * @param Collection $revisions
     * @return self
     */
    public function setRevisions($revisions) {
        $this->revisions = $revisions;
        return $this;
    }

}

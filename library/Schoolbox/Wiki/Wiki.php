<?php

/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Schoolbox\Wiki;

use Schoolbox\ISoftDeletable;
use Schoolbox\Mapper\Traits\Model\DeletedAtDate;
use Schoolbox\Resource\Folder\Model as Folder;

class Wiki implements ISoftDeletable
{
    use DeletedAtDate;

    /** @var int */
    protected $id;

    /** @var Folder */
    protected $category;

    /** @var null|Revision */
    protected $latestRevision;

    /** @var string */
    protected $slug;

    /** @var \Alaress_Date_Format */
    protected $created;

    /** @var bool */
    protected $locked;


    /**
     * @param int $id
     * @return self
     */
    public function setId($id)
    {
        $this->id = $id;
        return $this;
    }


    /**
     * @return int
     */
    public function getId()
    {
        return $this->id;
    }


    /**
     * @param Folder $category
     * @return self
     */
    public function setCategory($category)
    {
        $this->category = $category;
        return $this;
    }


    /**
     * @return Folder
     */
    public function getCategory()
    {
        return $this->category;
    }


    /**
     * @param null|Revision $latestRevision
     * @return self
     */
    public function setLatestRevision($latestRevision)
    {
        $this->latestRevision = $latestRevision;
        return $this;
    }


    /**
     * @return null|Revision
     */
    public function getLatestRevision()
    {
        return $this->latestRevision;
    }


    /**
     * @param string $slug
     * @return self
     */
    public function setSlug($slug)
    {
        $this->slug = $slug;
        return $this;
    }


    /**
     * @return string
     */
    public function getSlug()
    {
        return $this->slug;
    }


    /**
     * @param \Alaress_Date_Format $created
     * @return self
     */
    public function setCreated($created)
    {
        $this->created = $created;
        return $this;
    }


    /**
     * @return \Alaress_Date_Format
     */
    public function getCreated()
    {
        return $this->created;
    }



    /**
     * @param bool $locked
     * @return self
     */
    public function setLocked($locked)
    {
        $this->locked = $locked;
        return $this;
    }


    /**
     * @return bool
     */
    public function getLocked()
    {
        return $this->locked;
    }
}

<?php

/**
 *
 * @author      Damian Sloane <damian.sloane@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Schoolbox\Menu\Side;

use Schoolbox\ISoftDeletable;

class Model implements ISoftDeletable
{
    /** @var int */
    protected $id;

    /** @var int */
    protected $sortOrder;

    /** @var Model */
    protected $parent;

    /** @var string */
    protected $title;

    /** @var string */
    protected $url;

    /** @var string */
    protected $target;

    /** @var \Alaress_Date_Format */
    protected $deletedAt;

    /** @var \Schoolbox\Collection */
    protected $children;

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
     * @param \Schoolbox\Collection $children
     * @return self
     */
    public function setChildren($children)
    {
        $this->children = $children;
        return $this;
    }

    /**
     * @return \Schoolbox\Collection
     */
    public function getChildren()
    {
        return $this->children;
    }

    /**
     * @return int
     */
    public function getParentId()
    {
        return $this->getParent()->getId();
    }

    /**
     * @param string $title
     * @return self
     */
    public function setTitle($title)
    {
        $this->title = $title;
        return $this;
    }

    /**
     * @return string
     */
    public function getTitle()
    {
        return $this->title;
    }

    /**
     * @param string $url
     * @return self
     */
    public function setUrl($url)
    {
        $this->url = $url;
        return $this;
    }

    /**
     * @return string
     */
    public function getUrl()
    {
        return $this->url;
    }

    /**
     * @param string $target
     * @return self
     */
    public function setTarget($target)
    {
        $this->target = $target;
        return $this;
    }

    /**
     * @return string
     */
    public function getTarget()
    {
        return $this->target;
    }

    /**
     * @param int $sortOrder
     * @return self
     */
    public function setSortOrder($sortOrder)
    {
        $this->sortOrder = $sortOrder;
        return $this;
    }

    /**
     * @return int
     */
    public function getSortOrder()
    {
        return $this->sortOrder;
    }

    /**
     * @param \Alaress_Date_Format $deletedAt
     * @return self
     */
    public function setDeletedAt($deletedAt)
    {
        $this->deletedAt = $deletedAt;
        return $this;
    }

    /**
     * @return \Alaress_Date_Format $deletedAt
     */
    public function getDeletedAt()
    {
        return $this->deletedAt;
    }

    /**
     * Is this object deleted?
     *
     * @return bool
     */
    public function isDeleted()
    {
        return !$this->getDeletedAt() instanceof \Alaress_Date_NullDate;
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
}

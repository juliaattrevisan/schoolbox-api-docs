<?php

/**
 * Model for normal (non-eportfolio) blog posts
 *
 * @author      Damian Sloane <damian.sloane@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Schoolbox\Blog\Homepage\Post;

use Schoolbox\ISoftDeletable;
use Schoolbox\Mapper\Traits\Model\DeletedAtDate;
use Schoolbox\Resource\Folder\Model as Folder;
use Schoolbox\User;

class Model implements ISoftDeletable
{
    use DeletedAtDate;

    /** @var int */
    protected $id;

    /** @var Folder */
    protected $folder;

    /** @var User */
    protected $owner;

    /** @var \Alaress_Date_Format */
    protected $createdAt;

    /** @var \Alaress_Date_Format */
    protected $updatedAt;

    /** @var string */
    protected $heading;

    /** @var string */
    protected $body;

    /** @var \Schoolbox\Blog\Homepage\Comment\Mapper */
    protected $comments;

    /** @var \Schoolbox\Blog\Homepage\Image\Mapper */
    protected $images;

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
     * @param Folder $folder
     * @return self
     */
    public function setFolder($folder)
    {
        $this->folder = $folder;
        return $this;
    }

    /**
     * @return Folder
     */
    public function getFolder()
    {
        return $this->folder;
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
     * @param \Alaress_Date_Format $createdAt
     * @return self
     */
    public function setCreatedAt($createdAt)
    {
        $this->createdAt = $createdAt;
        return $this;
    }

    /**
     * @return \Alaress_Date_Format
     */
    public function getCreatedAt()
    {
        return $this->createdAt;
    }

    /**
     * @return \Alaress_Date_Format
     */
    public function getUpdatedAt()
    {
        return $this->updatedAt;
    }

    /**
     * @param  \Alaress_Date_Format $updatedAt
     * @return $this
     */
    public function setUpdatedAt($updatedAt)
    {
        $this->updatedAt = $updatedAt;
        return $this;
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
     * @return \Schoolbox\Blog\Homepage\Comment\Mapper
     */
    public function getComments()
    {
        return $this->comments;
    }

    /**
     * @param \Schoolbox\Blog\Homepage\Comment\Mapper $comments
     * @return $this
     */
    public function setComments($comments)
    {
        $this->comments = $comments;
        return $this;
    }

    /**
     * @return \Schoolbox\Blog\Homepage\Image\Mapper
     */
    public function getImages()
    {
        return $this->images;
    }

    /**
     * @param \Schoolbox\Blog\Homepage\Image\Mapper $images
     * @return $this
     */
    public function setImages($images)
    {
        $this->images = $images;

        return $this;
    }
}

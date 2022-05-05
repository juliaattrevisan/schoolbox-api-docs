<?php

/**
 * Model for forum threads.
 *
 * @author    James Leckie <jleckie@alaress.com.au>
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

namespace Schoolbox\Forum;

use Alaress_Date_Format;
use Schoolbox\Collection;
use Schoolbox\ISoftDeletable;
use Schoolbox\Resource\Folder\Model as Folder;
use Schoolbox\User;
use Schoolbox\Forum\ThreadMapper as ThreadMapper;

class Thread implements ISoftDeletable
{

    /** @var int */
    protected $id;

    /** @var Folder */
    protected $category;

    /** @var User */
    protected $user;

    /** @var string */
    protected $title;

    /** @var Alaress_Date_Format */
    protected $date;

    /** @var string */
    protected $deleted = 'false';

    /** @var bool */
    protected $locked = false;

    /** @var int */
    protected $views = 0;

    /** @var Post */
    protected $firstPost;

    /** @var Post */
    protected $lastPost;

    /** @var Collection|Post[] */
    protected $posts;

    /** @var Alaress_Date_Format */
    protected $lastPostDate;

    /** @var User */
    protected $lastPostUser;


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
     * @return Folder|null
     */
    public function getCategory()
    {
        return $this->category;
    }


    /**
     * @param User $user
     * @return self
     */
    public function setUser($user)
    {
        $this->user = $user;
        return $this;
    }


    /**
     * @return User
     */
    public function getUser()
    {
        return $this->user;
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
     * @param Alaress_Date_Format $date
     * @return self
     */
    public function setDate($date)
    {
        $this->date = $date;
        return $this;
    }


    /**
     * @return Alaress_Date_Format
     */
    public function getDate()
    {
        return $this->date;
    }


    /**
     * @param string $deleted
     * @return self
     */
    public function setDeleted($deleted)
    {
        $this->deleted = $deleted;
        return $this;
    }


    /**
     * @return string
     */
    public function getDeleted()
    {
        return $this->deleted;
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


    /**
     * @param int $views
     * @return self
     */
    public function setViews($views)
    {
        $this->views = $views;
        return $this;
    }

    /**
     * @return bool|self
     */
    public function recordView()
    {
        $this->views++;
        return $this;
    }

    /**
     * @return int
     */
    public function getViews()
    {
        return $this->views;
    }

    /**
     * @param Post $firstPost
     * @return self
     */
    public function setFirstPost($firstPost)
    {
        $this->firstPost = $firstPost;
        return $this;
    }

    /**
     * @return Post
     */
    public function getFirstPost()
    {
        return $this->firstPost;
    }




    /**
     * @param Post $lastPost
     * @return self
     */
    public function setLastPost($lastPost)
    {
        $this->lastPost = $lastPost;
        return $this;
    }


    /**
     * @return Post
     */
    public function getLastPost()
    {
        return $this->lastPost;
    }

    /**
     * @param Alaress_Date_Format $lastPostDate
     * @return self
     */
    public function setLastPostDate($lastPostDate)
    {
        $this->lastPostDate = $lastPostDate;
        return $this;
    }


    /**
     * @return Alaress_Date_Format
     */
    public function getLastPostDate()
    {
        return $this->lastPostDate;
    }


    /**
     * @param object $lastPostUser
     * @return self
     */
    public function setLastPostUser($lastPostUser)
    {
        $this->lastPostUser = $lastPostUser;
        return $this;
    }


    /**
     * @return object
     */
    public function getLastPostUser()
    {
        return $this->lastPostUser;
    }

    /**
     * Is this object deleted?
     * @return bool
     */
    public function isDeleted()
    {
        return ($this->getDeleted() == 'true');
    }

    /**
     * Is this object locked?
     * @return bool
     */
    public function isLocked()
    {
        return ($this->getLocked() == 'true');
    }
}

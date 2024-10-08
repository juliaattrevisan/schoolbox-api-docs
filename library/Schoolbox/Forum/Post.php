<?php

/**
 * Model for forum posts.
 *
 * @author    James Leckie <jleckie@alaress.com.au>
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

namespace Schoolbox\Forum;

use Alaress_Date_Format;
use Schoolbox\ISoftDeletable;
use Schoolbox\Mapper\Traits\Model\DeletedAtDate;
use Schoolbox\User;

class Post implements ISoftDeletable
{
    use DeletedAtDate;

    /** @var int */
    protected $id;

    /** @var int */
    protected $thread;

    /** @var Post */
    protected $parent = null;

    /** @var User */
    protected $owner;

    /** @var Alaress_Date_Format */
    protected $date;

    /** @var string */
    protected $body;

    /**
     * @var string
     * @deprecated use `$deletedAt`
     */
    protected $deleted = 'false';

    /** @var Alaress_Date_Format */
    protected $modified;

    /** @var string */
    protected $censored = 'false';


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
     * @param Thread $thread
     * @return self
     */
    public function setThread($thread)
    {
        $this->thread = $thread;
        return $this;
    }


    /**
     * @return Thread
     */
    public function getThread()
    {
        return $this->thread;
    }


    /**
     * @return Post
     */
    public function getParent()
    {
        return $this->parent;
    }


    /**
     * @param Post $parent
     * @return self
     */
    public function setParent($parent)
    {
        $this->parent = $parent;
        return $this;
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
     * Removes all the content before the final </blockquote> so it's just the text the user wrote
     *
     * @return string
     */
    public function getBodyWithoutQuote()
    {
        $tag = 'blockquote';

        if (($i = strrpos($this->body, "</$tag>")) !== false) {
            $trimmed = trim(substr($this->body, $i + strlen("</$tag>")));

            if (strlen($trimmed)) {
                return $trimmed;
            }
        }

        return $this->body;
    }


    /**
     * @deprecated use `setDeletedAt`
     *
     * @param string $deleted
     * @return self
     */
    public function setDeleted($deleted)
    {
        $this->deleted = $deleted;
        return $this;
    }


    /**
     * @deprecated use `getDeletedAt`
     *
     * @return string
     */
    public function getDeleted()
    {
        return $this->deleted;
    }


    /**
     * @param Alaress_Date_Format $modified
     * @return self
     */
    public function setModified($modified)
    {
        $this->modified = $modified;
        return $this;
    }


    /**
     * @return Alaress_Date_Format
     */
    public function getModified()
    {
        return $this->modified;
    }


    /**
     * @param string $censored
     * @return self
     */
    public function setCensored($censored)
    {
        $this->censored = $censored;
        return $this;
    }


    /**
     * @return string
     */
    public function getCensored()
    {
        return $this->censored;
    }
}

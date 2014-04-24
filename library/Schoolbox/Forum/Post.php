<?php

/**
 * Model for forum posts.
 *
 * @author    James Leckie <jleckie@alaress.com.au>
 * @copyright Copyright 2014, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

namespace Schoolbox\Forum;
use Schoolbox\ISoftDeletable;


class Post implements ISoftDeletable {

    /** @var int */
    protected $postID;

    /** @var int */
    protected $threadID;

    /** @var \Schoolbox\User */
    protected $owner;

    /** @var \Alaress_Date_Format */
    protected $date;

    /** @var string */
    protected $body;

    /** @var string */
    protected $deleted = 'false';

    /** @var \Alaress_Date_Format */
    protected $modified;

    /** @var string */
    protected $censored = 'false';


    /**
     * @param int $postID
     * @return self
     */
    public function setPostID($postID) {
        $this->postID = $postID;
        return $this;
    }


    /**
     * @return int
     */
    public function getPostID() {
        return $this->postID;
    }


    /**
     * @param int $threadID
     * @return self
     */
    public function setThreadID($threadID) {
        $this->threadID = $threadID;
        return $this;
    }


    /**
     * @return int
     */
    public function getThreadID() {
        return $this->threadID;
    }


    /**
     * @param int $userID
     * @return self
     */
    public function setOwner($owner) {
        $this->owner = $owner;
        return $this;
    }


    /**
     * @return int
     */
    public function getOwner() {
        return $this->owner;
    }


    /**
     * @param \Alaress_Date_Format $date
     * @return self
     */
    public function setDate($date) {
        $this->date = $date;
        return $this;
    }


    /**
     * @return \Alaress_Date_Format
     */
    public function getDate() {
        return $this->date;
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
     * @param string $deleted
     * @return self
     */
    public function setDeleted($deleted) {
        $this->deleted = $deleted;
        return $this;
    }


    /**
     * @return string
     */
    public function getDeleted() {
        return $this->deleted;
    }


    /**
     * @param \Alaress_Date_Format $modified
     * @return self
     */
    public function setModified($modified) {
        $this->modified = $modified;
        return $this;
    }


    /**
     * @return \Alaress_Date_Format
     */
    public function getModified() {
        return $this->modified;
    }


    /**
     * @param string $censored
     * @return self
     */
    public function setCensored($censored) {
        $this->censored = $censored;
        return $this;
    }


    /**
     * @return string
     */
    public function getCensored() {
        return $this->censored;
    }

    /**
     * Is this object deleted?
     * @return bool
     */
    public function isDeleted(){
        return ($this->getDeleted() == 'true');
    }
}

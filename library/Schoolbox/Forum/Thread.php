<?php

/**
 * Model for forum threads.
 *
 * @author    James Leckie <jleckie@alaress.com.au>
 * @copyright Copyright 2014, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

namespace Schoolbox\Forum;

class Thread
{

    /** @var int */
    protected $threadID;

    /** @var int */
    protected $categoryID;

    /** @var int */
    protected $userID;

    /** @var string */
    protected $title;

    /** @var \Alaress_Date_Format */
    protected $date;

    /** @var bool */
    protected $deleted;

    /** @var bool */
    protected $locked;

    /** @var int */
    protected $views;

    /** @var object */
    protected $lastPost;

    /** @var \Alaress_Date_Format */
    protected $lastPostDate;

    /** @var object */
    protected $lastPostUser;

    /** @var string */
    protected $lastPostUserName;

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
     * @param int $categoryID
     * @return self
     */
    public function setCategoryID($categoryID) {
        $this->categoryID = $categoryID;
        return $this;
    }


    /**
     * @return int
     */
    public function getCategoryID() {
        return $this->categoryID;
    }


    /**
     * @param int $userID
     * @return self
     */
    public function setUserID($userID) {
        $this->userID = $userID;
        return $this;
    }


    /**
     * @return int
     */
    public function getUserID() {
        return $this->userID;
    }


    /**
     * @param string $title
     * @return self
     */
    public function setTitle($title) {
        $this->title = $title;
        return $this;
    }


    /**
     * @return string
     */
    public function getTitle() {
        return $this->title;
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
     * @param bool $deleted
     * @return self
     */
    public function setDeleted($deleted) {
        $this->deleted = $deleted;
        return $this;
    }


    /**
     * @return bool
     */
    public function getDeleted() {
        return $this->deleted;
    }


    /**
     * @param bool $locked
     * @return self
     */
    public function setLocked($locked) {
        $this->locked = $locked;
        return $this;
    }


    /**
     * @return bool
     */
    public function getLocked() {
        return $this->locked;
    }


    /**
     * @param int $views
     * @return self
     */
    public function setViews($views) {
        $this->views = $views;
        return $this;
    }


    /**
     * @return int
     */
    public function getViews() {
        return $this->views;
    }


    /**
     * @param object $lastPost
     * @return self
     */
    public function setLastPost($lastPost) {
        $this->lastPost = $lastPost;
        return $this;
    }


    /**
     * @return object
     */
    public function getLastPost() {
        return $this->lastPost;
    }


    /**
     * @param \Alaress_Date_Format $lastPostDate
     * @return self
     */
    public function setLastPostDate($lastPostDate) {
        $this->lastPostDate = $lastPostDate;
        return $this;
    }


    /**
     * @return \Alaress_Date_Format
     */
    public function getLastPostDate() {
        return $this->lastPostDate;
    }


    /**
     * @param object $lastPostUser
     * @return self
     */
    public function setLastPostUser($lastPostUser) {
        $this->lastPostUser = $lastPostUser;
        return $this;
    }


    /**
     * @return object
     */
    public function getLastPostUser() {
        return $this->lastPostUser;
    }


    /**
     * @param string $lastPostUser
     * @return self
     */
    public function setLastPostUserName($lastPostUserName) {
        $this->lastPostUserName = $lastPostUserName;
        return $this;
    }


    /**
     * @return string
     */
    public function getLastPostUserName() {
        return $this->lastPostUserName;
    }

}

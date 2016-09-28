<?php
/**
 * @author      Damian Sloane <damian.sloane@alaress.com.au>
 * @copyright   Copyright 2016, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */

namespace Schoolbox\Menu\Side\UserOptions;

class Model
{
    /** @var int */
    protected $id;

    /** @var int */
    protected $userId;

    /** @var int */
    protected $menuSideId;

    /** @var int */
    protected $showBookmarks;

    /** @var int */
    protected $showGroups;

    /** @var int */
    protected $showSubjects;

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
     * @param int $userId
     * @return self
     */
    public function setUserId($userId)
    {
        $this->userId = $userId;
        return $this;
    }

    /**
     * @return int
     */
    public function getUserId()
    {
        return $this->userId;
    }

    /**
     * @param int $showBookmarks
     * @return self
     */
    public function setShowBookmarks($showBookmarks)
    {
        $this->showBookmarks = $showBookmarks;
        return $this;
    }

    /**
     * @return int
     */
    public function getShowBookmarks()
    {
        return $this->showBookmarks;
    }

    /**
     * @param int $showGroups
     * @return self
     */
    public function setShowGroups($showGroups)
    {
        $this->showGroups = $showGroups;
        return $this;
    }

    /**
     * @return int
     */
    public function getShowGroups()
    {
        return $this->showGroups;
    }

    /**
     * @param int $showSubjects
     * @return self
     */
    public function setShowSubjects($showSubjects)
    {
        $this->showSubjects = $showSubjects;
        return $this;
    }

    /**
     * @return int
     */
    public function getShowSubjects()
    {
        return $this->showSubjects;
    }
}
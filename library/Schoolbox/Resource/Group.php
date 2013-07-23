<?php
/**
 * Contains information about the type of group that a category represents
 * Categories can have groups of type, free, moderator or private.
 * A category is not a group unless this is set
 *
 * @author    James Leckie <james.leckie@alaress.com.au>
 * @copyright Copyright 2013, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */


namespace Schoolbox\Resource;

class Group {

    const PRIVATE_GROUP = 1;
    const MODERATED_GROUP = 2;
    const FREE_GROUP = 3;

    /**
     * The type of group
     *
     * @var int
     */
    protected $type;

    /**
     * @var int
     */
    protected $category;

    /**
     * Test if group is private
     *
     * @return bool
     */
    public function isPrivate() {
        if($this->getType() == PRIVATE_GROUP) {
            return true;
        }
        else {
            return false;
        }
    }

    /**
     * Test if group is moderated
     *
     * @return bool
     */
    public function isModerated() {
        if($this->getType() == MODERATED_GROUP) {
            return true;
        }
        else {
            return false;
        }
    }

    /**
     * Test if group is free
     *
     * @return bool
     */
    public function isFree() {
        if($this->getType() == FREE_GROUP) {
            return true;
        }
        else {
            return false;
        }
    }

    /**
     * @param int $type
     * @return $this
     */
    public function setType($type)
    {
        $this->type = $type;
        return $this;
    }

    /**
     * @return int
     */
    public function getType()
    {
        return $this->type;
    }

    public function setCategory($category)
    {
        $this->category = $category;
        return $this;
    }

    public function getCategory()
    {
        return $this->category;
    }


}

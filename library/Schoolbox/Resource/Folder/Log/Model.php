<?php
/**
 * 
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2015, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace Schoolbox\Resource\Folder\Log;

use Alaress_Date_Format;
use Schoolbox\Resource\Folder;
use Schoolbox\User;

class Model
{
    /**
     * @var Folder\Model
     */
    protected $category;

    /**
     * @var User
     */
    protected $user;

    /**
     * @var Alaress_Date_Format
     */
    protected $last_access_time;

    /**
     * @var int
     */
    protected $hitcount;

    /**
     * Auto-generated methods
     */

    /**
     * @param  Folder\Model $category
     *
     * @return $this
     */
    public function setCategory($category) {
        $this->category = $category;
        return $this;
    }

    /**
     * @return Folder\Model
     */
    public function getCategory() {
        return $this->category;
    }

    /**
     * @param  int $hitcount
     *
     * @return $this
     */
    public function setHitcount($hitcount) {
        $this->hitcount = $hitcount;
        return $this;
    }

    /**
     * @return int
     */
    public function getHitcount() {
        return $this->hitcount;
    }

    /**
     * @param  Alaress_Date_Format $last_access_time
     *
     * @return $this
     */
    public function setLastAccessTime($last_access_time) {
        $this->last_access_time = $last_access_time;
        return $this;
    }

    /**
     * @return Alaress_Date_Format
     */
    public function getLastAccessTime() {
        return $this->last_access_time;
    }

    /**
     * @param  User $user
     *
     * @return $this
     */
    public function setUser($user) {
        $this->user = $user;
        return $this;
    }

    /**
     * @return User
     */
    public function getUser() {
        return $this->user;
    }

}
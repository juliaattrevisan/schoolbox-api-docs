<?php
/**
 *
 * @author      Doug MacKenzie <doug.mackenzie@alaress.com.au>
 * @copyright   Copyright 2016, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace Schoolbox\Resource\Folder\Visits;

use Schoolbox\Role;
use Schoolbox\Resource\Folder\Model as Folder;

class Model
{
    /** @var \Alaress_Date_Format */
    protected $date;

    /** @var int */
    protected $hitcount;

    /** @var Folder */
    protected $folder;

    /**
     * @param int $hitcount
     * @return self
     */
    public function setHitcount($hitcount)
    {
        $this->hitcount = $hitcount;
        return $this;
    }

    /**
     * @return int
     */
    public function getHitcount()
    {
        return $this->hitcount;
    }

    /**
     * @param Role $role
     * @return self
     */
    public function setRole($role)
    {
        $this->role = $role;
        return $this;
    }

    /**
     * @return Role
     */
    public function getRole()
    {
        return $this->role;
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
     * @param \Alaress_Date_Format $date
     * @return self
     */
    public function setDate($date)
    {
        $this->date = $date;
        return $this;
    }

    /**
     * @return \Alaress_Date_Format
     */
    public function getDate()
    {
        return $this->date;
    }
}
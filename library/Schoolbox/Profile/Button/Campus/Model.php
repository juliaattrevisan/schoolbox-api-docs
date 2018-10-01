<?php
/**
 *
 * @author      Damian Sloane <damian.sloane@alaress.com.au>
 * @copyright   Copyright 2016, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace Schoolbox\Profile\Button\Campus;

use Schoolbox\ISoftDeletable;

class Model
{
    /** @var int */
    protected $id;

    /** @var int */
    protected $profileButtonId;

    /** @var int */
    protected $campusId;

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
     * @param int $profileButtonId
     * @return self
     */
    public function setProfileButtonId($profileButtonId)
    {
        $this->profileButtonId = $profileButtonId;
        return $this;
    }

    /**
     * @return int
     */
    public function getProfileButtonId()
    {
        return $this->profileButtonId;
    }

    /**
     * @param int $campusId
     * @return self
     */
    public function setCampusId($campusId)
    {
        $this->campusId = $campusId;
        return $this;
    }

    /**
     * @return int
     */
    public function getCampusId()
    {
        return $this->campusId;
    }
}
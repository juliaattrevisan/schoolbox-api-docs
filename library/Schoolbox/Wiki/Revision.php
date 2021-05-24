<?php

/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Schoolbox\Wiki;

use Schoolbox\User;

class Revision
{

    /** @var int */
    protected $id;

    /** @var Wiki */
    protected $wiki;

    /** @var string */
    protected $heading;

    /** @var string */
    protected $body;

    /** @var User */
    protected $user;

    /** @var \Alaress_Date_Format */
    protected $created;


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
     * @param Wiki $wiki
     * @return self
     */
    public function setWiki($wiki)
    {
        $this->wiki = $wiki;
        return $this;
    }


    /**
     * @return Wiki
     */
    public function getWiki()
    {
        return $this->wiki;
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
     * @param \Alaress_Date_Format $created
     * @return self
     */
    public function setCreated($created)
    {
        $this->created = $created;
        return $this;
    }


    /**
     * @return \Alaress_Date_Format
     */
    public function getCreated()
    {
        return $this->created;
    }
}

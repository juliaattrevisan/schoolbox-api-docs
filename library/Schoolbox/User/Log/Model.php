<?php

/**
 *
 * @author      Damian Sloane <damian.sloane@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Schoolbox\User\Log;

use Schoolbox\User;

class Model
{
    /** @var int */
    protected $id;

    /** @var string */
    protected $username;

    /** @var \Alaress_Date_Format */
    protected $date;

    /** @var string */
    protected $ip;

    /** @var bool */
    protected $login;

    /** @var bool */
    protected $logout;

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
     * @param string $username
     * @return self
     */
    public function setUsername($username)
    {
        $this->username = $username;
        return $this;
    }

    /**
     * @return string
     */
    public function getUsername()
    {
        return $this->username;
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

    /**
     * @param string $ip
     * @return self
     */
    public function setIp($ip)
    {
        $this->ip = $ip;
        return $this;
    }

    /**
     * @return string
     */
    public function getIp()
    {
        return $this->ip;
    }

    /**
     * @param bool $login
     * @return self
     */
    public function setLogin($login)
    {
        $this->login = $login;
        return $this;
    }

    /**
     * @return bool
     */
    public function getLogin()
    {
        return $this->login;
    }

    /**
     * @param bool $logout
     * @return self
     */
    public function setLogout($logout)
    {
        $this->logout = $logout;
        return $this;
    }

    /**
     * @return bool
     */
    public function getLogout()
    {
        return $this->logout;
    }
}

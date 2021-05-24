<?php

/**
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

namespace Schoolbox\User\Password\Reset;

use Alaress_Date_Format;
use Alaress_Date_NullDate;
use Schoolbox\User;

/**
 * Model for password resets
 *
 * @package Schoolbox\User\Password\Reset
 */
class Model
{
    /**
     * The unique and identifying token for this password reset.
     *
     * Might be a GUID or something.
     *
     * @var string
     */
    protected $token;

    /**
     * Who requested the reset?
     *
     * @var User
     */
    protected $user;

    /**
     * The IP address from whence this request originated.
     *
     * @var string
     */
    protected $ip;

    /**
     * The time that the reset took place (if it ever did).
     *
     * @var Alaress_Date_Format
     */
    protected $resetTime;

    /**
     * When the reset attempt expires. Defaults to one day from now.
     *
     * @var Alaress_Date_Format
     */
    protected $expiry;

    /**
     * Constructor
     */
    public function __construct()
    {
        $this->user      = new User();
        $this->expiry    = Alaress_Date_Format::now()->addDays(1);
        $this->resetTime = new Alaress_Date_NullDate();
    }

    /**
     * @return User
     */
    public function getUser()
    {
        return $this->user;
    }

    /**
     * @param  User $user
     * @return $this
     */
    public function setUser($user)
    {
        $this->user = $user;
        return $this;
    }

    /**
     * @return string
     */
    public function getToken()
    {
        return $this->token;
    }

    /**
     * @param  string $token
     * @return $this
     */
    public function setToken($token)
    {
        $this->token = $token;
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
     * @param  string $ip
     * @return $this
     */
    public function setIp($ip)
    {
        $this->ip = $ip;
        return $this;
    }

    /**
     * @return Alaress_Date_Format
     */
    public function getResetTime()
    {
        return $this->resetTime;
    }

    /**
     * @param  Alaress_Date_Format $resetTime
     * @return $this
     */
    public function setResetTime($resetTime)
    {
        $this->resetTime = $resetTime;
        return $this;
    }

    /**
     * @return Alaress_Date_Format
     */
    public function getExpiry()
    {
        return $this->expiry;
    }

    /**
     * @param  Alaress_Date_Format $expiry
     * @return $this
     */
    public function setExpiry($expiry)
    {
        $this->expiry = $expiry;
        return $this;
    }
}

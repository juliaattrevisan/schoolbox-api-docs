<?php
/**
 * 
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2014, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace Schoolbox\MobileApp;


use Alaress_Date_Format;
use Schoolbox\User;

class Registry {

    const PROVIDER_GOOGLE = 'GCM';
    const PROVIDER_APPLE  = 'APNS';


    /** @var User */
    protected $user;

    /** @var string */
    protected $token;

    /** @var string */
    protected $provider;

    /** @var Alaress_Date_Format */
    protected $createdAt;

    /** @var Alaress_Date_Format */
    protected $touchedAt;


    /**
     * @return User
     */
    public function getUser()
    {
        return $this->user;
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
     * @return string
     */
    public function getToken()
    {
        return $this->token;
    }


    /**
     * @param string $token
     * @return self
     */
    public function setToken($token)
    {
        $this->token = $token;
        return $this;
    }


    /**
     * @return Alaress_Date_Format
     */
    public function getTouchedAt()
    {
        return $this->touchedAt;
    }


    /**
     * @param Alaress_Date_Format $touchedAt
     * @return self
     */
    public function setTouchedAt($touchedAt)
    {
        $this->touchedAt = $touchedAt;
        return $this;
    }


    /**
     * @return string
     */
    public function getProvider()
    {
        return $this->provider;
    }


    /**
     * @param string $provider
     * @return self
     */
    public function setProvider($provider)
    {
        $this->provider = $provider;
        return $this;
    }


    /**
     * @return Alaress_Date_Format
     */
    public function getCreatedAt()
    {
        return $this->createdAt;
    }


    /**
     * @param Alaress_Date_Format $createdAt
     * @return self
     */
    public function setCreatedAt($createdAt)
    {
        $this->createdAt = $createdAt;
        return $this;
    }



} 
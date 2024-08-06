<?php

/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Schoolbox\MobileApp;

use Alaress_Date_Format;
use Schoolbox\ISoftDeletable;
use Schoolbox\Mapper\Traits\Model\CreatedAtDate;
use Schoolbox\Mapper\Traits\Model\DeletedAtDate;
use Schoolbox\User;
use SensitiveParameter;

class Registry implements ISoftDeletable
{
    use CreatedAtDate;
    use DeletedAtDate;

    const PROVIDER_GOOGLE    = 'GCM';
    const PROVIDER_APPLE     = 'APNS';
    const PROVIDER_DIGISTORM = 'Digistorm';
    const PROVIDER_FIREBASE  = 'Firebase';
    const PROVIDER_TASS_ORBIT  = 'TassOrbit';


    /** @var User */
    protected $user;

    /** @var string */
    protected $token;

    /** @var string */
    protected $deviceId;

    /** @var Service */
    protected $service;

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
     * @param string $token
     * @return $this
     */
    public function setToken(#[SensitiveParameter] $token)
    {
        $this->token = $token;
        return $this;
    }


    /**
     * @return string
     */
    public function getDeviceId()
    {
        return $this->deviceId;
    }


    /**
     * @param string $deviceId
     * @return $this
     */
    public function setDeviceId($deviceId)
    {
        $this->deviceId = $deviceId;
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
     * @return $this
     */
    public function setTouchedAt($touchedAt)
    {
        $this->touchedAt = $touchedAt;
        return $this;
    }


    /**
     * @return Service
     */
    public function getService()
    {
        return $this->service;
    }


    /**
     * @param Service $service
     * @return $this
     */
    public function setService($service)
    {
        $this->service = $service;
        return $this;
    }
}

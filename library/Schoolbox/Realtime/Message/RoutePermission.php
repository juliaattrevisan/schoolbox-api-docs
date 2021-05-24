<?php

/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Schoolbox\Realtime\Message;

use Schoolbox\Role;

class RoutePermission
{

    const MUTE    = 'MU';
    const DIGEST  = 'DI';
    const INSTANT = 'IN';
    const PUSH    = 'MO';
    const EMAIL   = 'EM';

    /** @var Source */
    protected $msgSource;
    /** @var int */
    protected $role;
    /** @var array */
    protected $default;
    /** @var array */
    protected $mask;


    /**
     * @param Source $msgSource
     */
    public function setMsgSource($msgSource)
    {
        $this->msgSource = $msgSource;
    }

    /**
     * @return Source
     */
    public function getMsgSource()
    {
        return $this->msgSource;
    }

    /**
     * @param Role $role
     */
    public function setRole($role)
    {
        $this->role = $role;
    }

    /**
     * @return Role
     */
    public function getRole()
    {
        return $this->role;
    }


    /**
     * @return array
     */
    public function getDefault()
    {
        return $this->default;
    }


    /**
     * @param array $default
     * @return static
     */
    public function setDefault($default)
    {
        $this->default = $default;
        return $this;
    }


    /**
     * @return array
     */
    public function getMask()
    {
        return $this->mask;
    }


    /**
     * @param array $mask
     * @return static
     */
    public function setMask($mask)
    {
        $this->mask = $mask;
        return $this;
    }
}

<?php

/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Schoolbox\Sync\Effect;

use Schoolbox\Sync;

class Model
{
    use Sync\Rule\ComponentTrait;

    /** @var string */
    protected $userProperty;

    /** @var Type\Model */
    protected $effectType;


    /**
     * @return string
     */
    public function getUserProperty()
    {
        return $this->userProperty;
    }


    /**
     * @param string $userProperty
     * @return static
     */
    public function setUserProperty($userProperty)
    {
        $this->userProperty = $userProperty;
        return $this;
    }


    /**
     * @return Type\Model
     */
    public function getEffectType()
    {
        return $this->effectType;
    }


    /**
     * @param Type\Model $effectType
     * @return static
     */
    public function setEffectType($effectType)
    {
        $this->effectType = $effectType;
        return $this;
    }
}

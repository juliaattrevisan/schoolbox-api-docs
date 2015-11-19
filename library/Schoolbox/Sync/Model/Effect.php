<?php
/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2015, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace Schoolbox\Sync\Model;


class Effect
{
    use RuleComponentTrait;

    /** @var string */
    protected $userProperty;

    /** @var EffectType */
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
     * @return EffectType
     */
    public function getEffectType()
    {
        return $this->effectType;
    }


    /**
     * @param EffectType $effectType
     * @return static
     */
    public function setEffectType($effectType)
    {
        $this->effectType = $effectType;
        return $this;
    }

}
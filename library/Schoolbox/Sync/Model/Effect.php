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

    /** @var int */
    protected $id;

//    /** @var Rule */
//    protected $rule;

    /** @var string */
    protected $userProperty;

    /** @var EffectType */
    protected $effectType;

    /** @var array */
    protected $arguments;


    /**
     * @return int
     */
    public function getId()
    {
        return $this->id;
    }


    /**
     * @param int $id
     * @return static
     */
    public function setId($id)
    {
        $this->id = $id;
        return $this;
    }


//    /**
//     * @return Rule
//     */
//    public function getRule()
//    {
//        return $this->rule;
//    }
//
//
//    /**
//     * @param Rule $rule
//     * @return static
//     */
//    public function setRule($rule)
//    {
//        $this->rule = $rule;
//        return $this;
//    }


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


    /**
     * @return array
     */
    public function getArguments()
    {
        return $this->arguments;
    }


    /**
     * @param array $arguments
     * @return static
     */
    public function setArguments($arguments)
    {
        // rather than typehint and have it blow up, at least using an exception is recoverable
        if (!is_array($arguments)) {
            throw new \InvalidArgumentException('Arguments must be an array, got ' . gettype($arguments));
        }
        $this->arguments = $arguments;
        return $this;
    }

}
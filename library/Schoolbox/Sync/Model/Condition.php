<?php
/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2015, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace Schoolbox\Sync\Model;


class Condition
{

    /** @var int */
    protected $id;

//    /** @var Rule */
//    protected $rule;

    /** @var ConditionType */
    protected $conditionType;

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
     * @return ConditionType
     */
    public function getConditionType()
    {
        return $this->conditionType;
    }


    /**
     * @param ConditionType $conditionType
     * @return static
     */
    public function setConditionType($conditionType)
    {
        $this->conditionType = $conditionType;
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
        $this->arguments = $arguments;
        return $this;
    }

}
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
    use RuleComponentTrait;

    /** @var ConditionType */
    protected $conditionType;


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

}
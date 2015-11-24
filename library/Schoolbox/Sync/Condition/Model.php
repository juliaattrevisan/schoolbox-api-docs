<?php
/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2015, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace Schoolbox\Sync\Condition;

use Schoolbox\Sync;

class Model
{
    use Sync\Rule\ComponentTrait;

    /** @var Type\Model */
    protected $conditionType;


    /**
     * @return Type\Model
     */
    public function getConditionType()
    {
        return $this->conditionType;
    }


    /**
     * @param Type\Model $conditionType
     * @return static
     */
    public function setConditionType($conditionType)
    {
        $this->conditionType = $conditionType;
        return $this;
    }

}
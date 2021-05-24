<?php

/**
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Schoolbox\Sync\Condition\Fragment;

use Schoolbox\Sync\Condition\Type\Model as ConditionType;

/**
 * Class Factory
 *
 * @package Schoolbox\Sync\Condition\Fragment
 */
class Factory
{

    /**
     * General purpose Condition
     */
    private const TYPE_GENERAL = 1;


    /**
     * Condition specifically for user existence
     */
    private const TYPE_USEREXISTS = 2;


    /**
     * Produce a Container
     *
     * @return Fragment
     */
    public function produce()
    {
        return new Fragment('@upsCondition/container.twig');
    }


    /**
     * Produce a subfragment
     *
     * @param ConditionType $conditionType
     * @return Fragment|null
     */
    public function produceType(ConditionType $conditionType)
    {
        if ($conditionType->getId() == self::TYPE_GENERAL) {
            return new Fragment('@upsCondition/general.twig');
        } else if ($conditionType->getId() == self::TYPE_USEREXISTS) {
            return new Fragment('@upsCondition/user-exists.twig');
        }

        /* You shouldn't be able to get here, but anyway
         */
        return null;
    }
}

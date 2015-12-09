<?php
/**
 * Factory
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2011, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace Schoolbox\Sync\Condition\Fragment;

use Schoolbox\Sync\Condition\Type\Model as ConditionType;

class Factory {
    const TYPE_GENERAL = 1;
    const TYPE_USEREXISTS = 2;

    /**
     * Produce a Container
     *
     * @return Fragment
     */
    public function produce() {
        return new Fragment('@upsCondition/container.twig');
    }

    /**
     * Produce a subfragment
     *
     * @param $conditionType
     * @return Fragment
     */
    public function produceType(ConditionType $conditionType) {
        if ($conditionType->getId() == self::TYPE_GENERAL) {
            return new Fragment('@upsCondition/general.twig');
        }
        else if ($conditionType->getId() == self::TYPE_USEREXISTS) {
            return new Fragment('@upsCondition/user-exists.twig');
        }
    }
} 
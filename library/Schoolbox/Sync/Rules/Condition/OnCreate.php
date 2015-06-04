<?php
/**
 * 
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2015, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace Schoolbox\Sync\Rules\Condition;

use Alaress\Rules\Condition;
use Schoolbox\Mapper;

class OnCreate extends Condition
{

    /**
     * @param bool $negate
     */
    public function __construct($negate = false)
    {
        parent::__construct(
            function ($input, $mode) {
                return $mode == Mapper::METHOD_CREATE;
            },
            $negate
        );
    }


    /**
     * @return string
     */
    public function getSummary()
    {
        $not = $this->isNegated() ? 'NOT ' : '';

        return "mode $not= CREATE";
    }

} 
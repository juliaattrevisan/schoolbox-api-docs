<?php
/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2015, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace Schoolbox\Assessment\Rubric\Competency;


use Schoolbox\Assessment\Rubric\DescriptorList;

/**
 * Class Level
 *
 * @deprecated use `\Schoolbox\Assessment\Rubric\Grid\Level` instead
 */
class Level extends DescriptorList
{

    /**
     * Returns the highest possible mark for this Calculable
     * TODO I'm not sure where, if at all, this is actually being used?
     * it appears to be summating a column of descriptor values, which we never actually do...
     *
     * @return float
     */
    public function calculateMax()
    {
        $total = 0;

        foreach ($this->descriptors as $descriptor) {
            if(is_numeric($descriptor->calculateMax())) {
                $total += $descriptor->calculateMax();
            }
        }

        return $total;
    }

}

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

class Level extends DescriptorList
{

    /**
     * Returns the total possible value of this Calculable
     *
     * @return float
     */
    public function getTotal()
    {
        $total = 0;

        foreach ($this->descriptors as $descriptor) {
            if(is_numeric($descriptor->getTotal())) {
                $total += $descriptor->getTotal();
            }
        }

        return $total;
    }

}

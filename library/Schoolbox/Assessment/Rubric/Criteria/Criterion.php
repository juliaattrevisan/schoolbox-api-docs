<?php
/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2015, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace Schoolbox\Assessment\Rubric\Criteria;


use Schoolbox\Assessment\Rubric\DescriptorList;

class Criterion extends DescriptorList
{

    /**
     * Returns the total of the values of descriptors contained herein, irrespective of selection
     *
     * @return float
     */
    public function getTotal()
    {
        $total = 0;

        foreach ($this->descriptors as $descriptor) {
            $total = max($total, $descriptor->getTotal());
        }

        return $total;

    }

}

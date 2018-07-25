<?php
/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2015, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace Schoolbox\Assessment\Rubric;


use SplObjectStorage;

class CalculableAggregator extends SplObjectStorage implements Calculable
{

    /**
     * Returns the sum of the Calculables contained herein
     *
     * @return float
     */
    public function getMark()
    {
        $value = 0;

        foreach ($this as $calc) {
            $value += $calc->getMark();
        }

        return $value;
    }


    /**
     * Returns the total of the Calculables contained herein
     *
     * @return float
     */
    public function getMax()
    {
        $total = 0;

        foreach ($this as $calc) {
            $total += $calc->getMax();
        }

        return $total;
    }


    /**
     * Returns the percentage of the Calculables contained herein
     * @return float
     */
    public function getPercentage()
    {
        if ($total = $this->getMax()) {
            return $this->getMark() / $total;
        }
        return 0;
    }

}

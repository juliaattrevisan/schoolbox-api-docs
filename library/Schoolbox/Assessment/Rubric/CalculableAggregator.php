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
    public function getSum()
    {
        $value = 0;

        foreach ($this as $calc) {
            $value += $calc->getSum();
        }

        return $value;
    }


    /**
     * Returns the total of the Calculables contained herein
     *
     * @return float
     */
    public function getTotal()
    {
        $total = 0;

        foreach ($this as $calc) {
            $total += $calc->getTotal();
        }

        return $total;
    }


    /**
     * Returns the percentage of the Calculables contained herein
     * @return float
     */
    public function getPercentage()
    {
        if ($total = $this->getTotal()) {
            return $this->getSum() / $total;
        }
        return 0;
    }

}

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


/**
 * Class CalculableAggregator
 * Superclass for rubric elements that need to summate values on collections of children, in order to calculate overall grades.
 *
 * @package Schoolbox\Assessment\Rubric
 */

class CalculableAggregator extends SplObjectStorage implements Calculable
{

    /**
     * Summate the marks given for a Calculable
     *
     * @return float
     */
    public function calculateMark()
    {
        $mark = 0;

        foreach ($this as $calc) {
            $mark += $calc->calculateMark();
        }

        return $mark;
    }


    /**
     * Calculates the highest available mark for this Calculable
     *
     * @return float
     */
    public function calculateMax()
    {
        $total = 0;

        foreach ($this as $calc) {
            $total += $calc->calculateMax();
        }

        return $total;
    }


    /**
     * Scale the summated marks given for a Calculable, based on the total possible marks for the Calculable
     *
     * @return float
     */
    public function calculateScaledMark()
    {
        $scaledMark = $this->calculateMark();

        if ($rowMaxSum = $this->calculateRowMaxSum()) {
            $scaledMark = $this->calculateMark() * ($this->calculateMax() / $this->calculateRowMaxSum());
        }

        return $scaledMark;
    }


    /**
     * Summate the highest values from each row in the Calculable (help this is very hard to put into words)
     * This is essentially the highest mark that can be achieved for the Calculable, without scaling
     *
     * @return float
     */
    public function calculateRowMaxSum()
    {
        $rowMaxSum = 0;

        foreach ($this as $calc) {
            $rowMaxSum += $calc->calculateRowMaxSum();
        }

        return $rowMaxSum;
    }


    /**
     * Turns the scaled mark for the Calculable into a percentage of the total possible marks
     *
     * @return float
     */
    public function calculatePercentage()
    {
        $percentage = 0;

        foreach ($this as $calc) {
            if ($max = $calc->calculateMax()) {
                $percentage = $calc->calculateScaledMark() / $max;
            }
        }

        return $percentage;
    }

}

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
 * @deprecated use `\Schoolbox\Assessment\Rubric\{RowSet,Grid,MarkedGrid}` instead
 *
 * @package Schoolbox\Assessment\Rubric
 */

class CalculableAggregator extends SplObjectStorage implements Calculable
{
//    /**
//     * Deprecation warning
//     */
//    public function __construct()
//    {
//        trigger_error(
//            'This class is deprecated; use "\Schoolbox\Assessment\Rubric\{RowSet,Grid,MarkedGrid}" instead',
//            E_USER_DEPRECATED
//        );
//    }

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
        $scaledMark = 0;

        foreach ($this as $calc) {
            $scaledMark += $calc->calculateScaledMark();
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

}

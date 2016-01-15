<?php
/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2015, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace Schoolbox\Assessment\Rubric;


interface Calculable
{

    /**
     * Return the calculated sum value of this Calculable (NOT the total possible value)
     *
     * @return float
     */
    public function getSum();


    /**
     * Returns the total possible value of this Calculable
     *
     * @return float
     */
    public function getTotal();

}

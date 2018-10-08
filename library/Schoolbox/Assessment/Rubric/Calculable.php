<?php
/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2015, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace Schoolbox\Assessment\Rubric;


/**
 * Interface Calculable
 *
 * @deprecated this has no further use now that everything that uses it is deprecated
 */
interface Calculable
{

    /**
     * Return the mark given for this Calculable
     *
     * @return float|null
     */
    public function calculateMark();


    /**
     * Returns the highest possible mark for this Calculable (takes into account custom max capability marks)
     *
     * @return float
     */
    public function calculateMax();

}

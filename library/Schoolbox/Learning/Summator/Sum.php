<?php
/**
 * Maximum
 *
 * Calculate the highest marked Assessable
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2011, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace Schoolbox\Learning\Summator;

use \Schoolbox\Collection;

class Sum implements ISummator
{

    /**
     * Execute summation strategy
     *
     * @param Collection $allAssessables
     * @return null|int|float
     */
    public function summate(Collection $allAssessables)
    {
        $sum = null;

        foreach ($allAssessables as $currentAssessable) {
            if ($currentAssessable->calculateMark() !== null) {
                $sum += $currentAssessable->calculateMark();
            }
        }

        return $sum;
    }
}
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

class Maximum implements ISummator
{

    /**
     * Execute summation strategy
     *
     * Don't do $allAssessables->sort() as this will actually
     * change the ordering of this object, by reference
     *
     * @param Collection $allAssessables
     * @return null|int|float
     */
    public function summate(Collection $allAssessables)
    {
        $max = null;

        /* Accommodate a set of negative values
         */
        if ($allAssessables->count()) {
            $max = $allAssessables->first()->calculateMark();

            foreach ($allAssessables as $currentAssessable) {
                if (($currentAssessable->calculateMark() > $max) && ($currentAssessable->calculateMark() !== null)) {
                    $max = $currentAssessable->calculateMark();
                }
            }
        }

        return $max;
    }
}
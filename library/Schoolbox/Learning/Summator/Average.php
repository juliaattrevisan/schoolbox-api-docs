<?php

/**
 * Average
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2011, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */
namespace Schoolbox\Learning\Summator;

use \Schoolbox\Collection;

class Average implements ISummator
{

    /**
     * Average constructor.
     */
    public function __construct()
    {
    }

    /**
     * Execute summation strategy
     *
     * @param Collection $allAssessables
     * @return float|null
     */
    public function summate(Collection $allAssessables)
    {
        if ($allAssessables->count()) {
            $numItems = 0;
            $sum = 0;

            /* Exclude null values from average
             */
            foreach ($allAssessables as $currentAssessable) {
                if (!$currentAssessable->isNullMark()) {
                    $sum += $currentAssessable->calculateMark();
                    $numItems++;
                }
            }

            if ($numItems) {
                return (float)$sum / (float)$numItems;
            }
        }

        /* TODO: When nothing to mark, what should this return?
         */
        return null;
    }
}
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
use \Schoolbox\Learning\Assessment\AbstractResponse;

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

        if ($allAssessables->count()) {
            $sum = 0;

            foreach ($allAssessables as $currentAssessable) {

                /** @var AbstractResponse $currentResponse */
                foreach ($currentAssessable->getAllResponses() as $currentResponse) {
                    $feedback = $currentResponse->findMostRecentFeedback();

                    if ($feedback) {
                        $sum += $feedback->calculateMark();
                    }
                }
            }
        }

        return $sum;
    }
}
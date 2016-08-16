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

class Minimum implements ISummator
{

    /**
     * Execute summation strategy
     *
     * Don't do $allAssessables->sort() as this will actually
     * change the ordering of this object, by reference
     *
     * Accommodate a set of negative values
     *
     * @param Collection $allAssessables
     * @return null|int|float
     */
    public function summate(Collection $allAssessables)
    {
        $min = null;

        if ($allAssessables->count()) {
            foreach ($allAssessables as $currentAssessable) {

                /** @var AbstractResponse $currentResponse */
                foreach ($currentAssessable->getAllResponses() as $currentResponse) {
                    $feedback = $currentResponse->findMostRecentFeedback();

                    if ($feedback) {
                        $currentFeedbackMark = $feedback->calculateMark();

                        /* $min is being set for the first time to a non-null value
                         * If you don't do this check for $min == null, summate() will
                         * always return null (any actual value is never less than null)
                         */
                        if ($currentFeedbackMark < $min || $min == null) {
                            $min = $currentFeedbackMark;
                        }
                    }
                }
            }
        }

        return $min;
    }
}
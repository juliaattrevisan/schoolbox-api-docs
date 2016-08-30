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
namespace Schoolbox\Learning\Assessment\Summator;

use \Schoolbox\Collection;
use \Schoolbox\Learning\Assessment\AbstractResponse;

class Maximum implements ISummator
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
        $max = null;

        if ($allAssessables->count()) {
            foreach ($allAssessables as $currentAssessable) {

                /** @var AbstractResponse $currentResponse */
                foreach ($currentAssessable->getAllResponses() as $currentResponse) {
                    $feedback = $currentResponse->findMostRecentNonNullFeedback();

                    if ($feedback) {
                        $currentFeedbackMark = $feedback->calculateMark();

                        if ($currentFeedbackMark > $max) {
                            $max = $currentFeedbackMark;
                        }
                    }
                }
            }
        }

        return $max;
    }
}
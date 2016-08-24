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
use \Schoolbox\User;
use \Schoolbox\Learning\Assessment\AbstractAssessment;
use \Schoolbox\Learning\Assessment\AbstractResponse;
use \Schoolbox\Learning\Assessment\Participant;

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
     * @param User $user
     * @return null|int|float
     */
    public function summate(Collection $allAssessables, User $user)
    {
        $min = null;

        if ($allAssessables->count()) {

            /** @var AbstractAssessment $currentAssessable */
            foreach ($allAssessables as $currentAssessable) {

                /** @var Participant $participant */
                $participant = $currentAssessable->findParticipant($user);

                /** @var AbstractResponse $currentResponse */
                if ($participant) {
                    foreach ($participant->findAllResponsesForAssessable($currentAssessable) as $currentResponse) {
                        $feedback = $currentResponse->findMostRecentFeedback();

                        /* Null should not be the minimum
                         */
                        if ($feedback && !$feedback->isNullMark()) {
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
        }

        return $min;
    }
}
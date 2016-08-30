<?php
/**
 * Minimum
 *
 * Calculate the lowest normalisedMark Feedback for the selected User
 *
 * Tasks do not participate in the Minimum calculation and
 * must be handled specially, as null is always less than any
 * numeric value. The minimum value of a set of values that
 * contain a mix of nulls and non-nulls should never be null.
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

                try {
                    /** @var Participant $participant */
                    $participant = $currentAssessable->findParticipant($user);

                    /** @var AbstractResponse $currentResponse */
                    if ($participant) {

                        /* Only use numeric marks in Average computation
                         * to prevent Tasks from influencing your result
                         */
                        $feedback = $participant->findMostRecentFeedback(
                            $currentAssessable,
                            Participant::TRAVERSAL_MARKED
                        );

                        /* Null should not be the minimum
                         */
                        if ($feedback && $feedback->isMarked()) {
                            $markValue = $feedback->calculateMark();

                            /* $min is being set for the first time to a non-null value
                             * If you don't do this check for $min == null, summate() will
                             * always return null (any actual value is never less than null)
                             */
                            if ($markValue < $min || $min == null) {
                                $min = $markValue;
                            }
                        }
                    }
                }
                /* noop $user not found in Assessable's Participant collection
                 */
                catch (\UnexpectedValueException $e) {
                }

            }
        }

        return $min;
    }
}
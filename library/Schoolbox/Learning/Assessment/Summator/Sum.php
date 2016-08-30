<?php
/**
 * Sum
 *
 * Calculate the Sum of the normalisedMarks for every Feedback
 * for a particular User.
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

class Sum implements ISummator
{
    /**
     * Execute summation strategy
     *
     * @param Collection $allAssessables
     * @param User $user
     * @return null|int|float
     */
    public function summate(Collection $allAssessables, User $user)
    {
        $sum = null;

        if ($allAssessables->count()) {
            $sum = 0;

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
                        $feedback = $participant->findMostRecentFeedback($currentAssessable, Participant::TRAVERSAL_MARKED);

                        if ($feedback && $feedback->isMarked()) {
                            $sum += $feedback->calculateMark();
                        }
                    }
                }
                /* noop $user not found in Assessable's Participant collection
                 */
                catch (\UnexpectedValueException $e) {
                }

            }
        }

        return $sum;
    }
}
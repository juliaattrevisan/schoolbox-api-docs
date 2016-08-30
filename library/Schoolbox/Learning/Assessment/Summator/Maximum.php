<?php
/**
 * Maximum
 *
 * Calculate the highest normalisedMark Feedback for the selected User
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
     * @param User $user
     * @return null|int|float
     */
    public function summate(Collection $allAssessables, User $user)
    {
        $max = null;

        if ($allAssessables->count()) {

            /** @var AbstractAssessment $currentAssessable */
            foreach ($allAssessables as $currentAssessable) {

                /** @var Participant $participant */
                $participant = $currentAssessable->findParticipant($user);

                /** @var AbstractResponse $currentResponse */
                if ($participant) {

                    /* Only use numeric marks in Average computation
                     * to prevent Tasks from influencing your result
                     */
                    $feedback = $participant->findMostRecentFeedback($currentAssessable, Participant::TRAVERSAL_MARKED);

                    if ($feedback && !$feedback->isNullMark()) {
                        $markValue = $feedback->calculateMark();

                        if ($markValue > $max) {
                            $max = $markValue;
                        }
                    }
                }
            }
        }

        return $max;
    }
}
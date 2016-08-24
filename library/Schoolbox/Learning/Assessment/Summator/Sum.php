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

                /** @var Participant $participant */
                $participant = $currentAssessable->findParticipant($user);

                /** @var AbstractResponse $currentResponse */
                if ($participant) {
                    foreach ($participant->findAllResponsesForAssessable($currentAssessable) as $currentResponse) {
                        $feedback = $currentResponse->findMostRecentFeedback();

                        if ($feedback) {
                            $sum += $feedback->calculateMark();
                        }
                    }
                }
            }
        }

        return $sum;
    }
}
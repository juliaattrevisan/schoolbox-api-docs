<?php
/**
 * Average
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

class Average implements ISummator
{
    /**
     * Average constructor.
     */
    public function __construct()
    {
    }
    /**
     * Execute summation strategy for a particular Participant
     *
     * @param Collection $allAssessables
     * @param User $user
     * @return float|null
     */
    public function summate(Collection $allAssessables, User $user)
    {
        if ($allAssessables->count()) {
            $numItems    = 0;
            $sum         = 0;

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
                            $numItems++;
                        }
                    }
                }
            }

            if ($numItems) {
                return (float)$sum / (float)$numItems;
            }
        }

        return null;
    }
}

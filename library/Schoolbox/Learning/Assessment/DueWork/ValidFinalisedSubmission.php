<?php

/**
 * ValidFinalisedSubmission
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Schoolbox\Learning\Assessment\DueWork;

use Schoolbox\Learning\Assessment\IAssessable;
use Schoolbox\User;

class ValidFinalisedSubmission
{

    /**
     * @param IAssessable $assessment
     * @param User        $student
     * @param int         $window
     * @return bool
     * @throws \DomainException
     */
    public function isSatisfiedBy(IAssessable $assessment, User $student, int $window)
    {
        /* Find the most recent submission updated_at timestamp and compare the current time
         * This prevents SA-6704, a javascripted exploit, where users can craft async requests
         * to flood the db with meaningless values.
         */
        $participant = $assessment->getParticipantIfExtant($student);

        if ($participant !== null) {
            $mostRecentResponse = $participant->findMostRecentSubmittedResponse();

            if ($mostRecentResponse !== null) {
                $threshold = \Alaress_Date_Format::now();
                $threshold->addSeconds(-1 * $window);

                if ($threshold->lte($mostRecentResponse->getSubmittedAt())) {
                    throw new \DomainException("Resubmission window exceeded for {$student->getFullname()}");
                }
            }
        }

        return true;
    }
}

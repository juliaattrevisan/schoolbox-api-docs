<?php
/**
 * AbstractSummator
 *
 * Template Design Pattern
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2011, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace Schoolbox\Learning\Assessment\Summator;

use Schoolbox\Collection;
use Schoolbox\Learning\Assessment\IAssessable;
use Schoolbox\Learning\Assessment\Participant;
use Schoolbox\User;

abstract class AbstractSummator implements ISummator
{

    /**
     * @inheritDoc
     */
    public function summate(Collection $allAssessables, User $user, $publishedOnly)
    {
        if ($allAssessables->count() == 0) {
            return null;
        }

        $this->reset();

        /* Calculate weighting
         */
        $totalWeight = $this->calculateTotalWeight($allAssessables);

        /** @var IAssessable $currentAssessable */
        foreach ($allAssessables as $currentAssessable) {
            try {
                /** @var Participant $participant */
                $participant = $currentAssessable->getParticipant($user);

                /* Only use numeric marks in Average computation
                 * to prevent Tasks from influencing your result
                 */
                if ($publishedOnly) {
                    $feedback = $participant->findMostRecentPublishedFeedback($currentAssessable);
                } else {
                    $feedback = $participant->findMostRecentFeedback($currentAssessable);
                }

                if ($feedback) {
                    $val = $feedback->getNormalisedMark();

                    if (!is_null($val)) {
                        $this->aggregate($val, $currentAssessable->getWeight(), $totalWeight);
                    }
                }
            }
            catch (\UnexpectedValueException $e) {
                /* noop $user not found in Assessable's Participant collection
                 */
            }
        }

        return $this->finalise();
    }

    /**
     * The sum total weight of all the assessables
     *
     * @param Collection $allAssessables
     * @return int
     */
    protected abstract function calculateTotalWeight(Collection $allAssessables);

    /**
     * @param float $val
     * @param int $assessableWeight
     * @param int $totalWeight
     * @return void
     */
    protected abstract function aggregate($val, $assessableWeight, $totalWeight);

    /**
     * @return void
     */
    protected abstract function reset();

    /**
     * @return float|null
     */
    protected abstract function finalise();
}
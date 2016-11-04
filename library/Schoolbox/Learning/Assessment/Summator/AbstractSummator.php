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

        /** @var IAssessable $currentAssessable */
        foreach ($allAssessables as $currentAssessable) {
            if ($currentAssessable->isParticipant($user)) {
                /** @var Participant $participant */
                $participant = $currentAssessable->getParticipant($user);

                /* Only use numeric marks in Average computation
                 * to prevent Tasks from influencing your result
                 */
                if ($publishedOnly) {
                    $feedback = $participant->findMostRecentPublishedFeedback();
                } else {
                    $feedback = $participant->findMostRecentFeedback();
                }

                if ($feedback) {
                    $val = $feedback->getNormalisedMark();

                    if (!is_null($val)) {
                        $this->aggregate($val, $currentAssessable->getWeight());
                    }
                }
            }
        }

        return $this->finalise();
    }

    /**
     * @inheritdoc
     */
    public function summateAllAssessablesForUser(Collection $allAssessables, User $user, $publishedOnly)
    {
        return $this->summate($allAssessables, $user, $publishedOnly);
    }

    /**
     * @inheritdoc
     */
    public function summateSingleAssessable(IAssessable $assessable, $publishedOnly)
    {
        $this->reset();
        foreach ($assessable->getAllParticipants() as $participant) {

            if ($publishedOnly) {
                $feedback = $participant->findMostRecentPublishedFeedback();
            } else {
                $feedback = $participant->findMostRecentFeedback();
            }
            if ($feedback) {
                $val = $feedback->getNormalisedMark();

                if (isset($val)) {
                    // Weight doesn't matter within the context of a single assessment
                    $this->aggregate($val, $assessable->getWeight());
                }
            }
        }
        return $this->finalise();
    }

    /**
     * @inheritdoc
     */
    public function summateAllAssessables(Collection $allAssessables, $publishedOnly, ISummator $subSummator)
    {
        $this->reset();
        /** @var IAssessable $assessable */
        foreach ($allAssessables as $assessable) {
            $val = $subSummator->summateSingleAssessable($assessable, $publishedOnly);
            if (isset($val)) {
                $this->aggregate($val, $assessable->getWeight());
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
     * @return void
     */
    protected abstract function aggregate($val, $assessableWeight);

    /**
     * @return void
     */
    protected abstract function reset();

    /**
     * @return float|null
     */
    protected abstract function finalise();
}
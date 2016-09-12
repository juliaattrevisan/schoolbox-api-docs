<?php
/**
 * AbstractSummator
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
                        $this->aggregate($val);
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
     * @param float $val
     * @return void
     */
    protected abstract function aggregate($val);

    /**
     * @return void
     */
    protected abstract function reset();

    /**
     * @return float|null
     */
    protected abstract function finalise();

}
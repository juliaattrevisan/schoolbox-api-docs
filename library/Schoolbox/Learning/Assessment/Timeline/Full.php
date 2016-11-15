<?php
/**
 * Timeline
 *
 * Takes a Due Work's Responses and Feedbacks and arranges
 * them into a neatish timeline suitable for a forum-like
 * nested structure ordered by submittedAt and publishedAt
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2011, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace Schoolbox\Learning\Assessment\Timeline;

use Alaress_Date_Format;
use \Schoolbox\Collection;
use Schoolbox\Learning\Assessment\IFeedback;
use Schoolbox\Learning\Assessment\IResponse;
use Schoolbox\Learning\Assessment\Participant;

class Full
{

    /**
     * @var Participant
     */
    protected $participant;

    /**
     * @var Collection
     */
    protected $recombined;

    /**
     * Save yourself some time by returning the cached
     * version of the recombination if it exists
     *
     * @var bool
     */
    protected $hasRecombined = false;

    /**
     * Timeline constructor.
     *
     * @param Participant $participant
     */
    public function __construct(Participant $participant)
    {
        $this->setParticipant($participant);
        $this->setRecombined(new Collection());
    }

    /**
     * Get the timeline
     *
     * Recombine if required, otherwise return a cached
     * version of the recombination because reasons
     *
     * @return Collection
     */
    public function getTimeline()
    {
        if (!$this->getHasRecombined()) {
            $this->recombine();
            $this->setHasRecombined(true);
        }

        return $this->getRecombined();
    }

    /**
     * Combining the collections
     *
     * 1 + 1 = 1
     */
    protected function recombine()
    {
        $processedFeedback = [];

        foreach ($this->getParticipant()->getAllResponses() as $currentResponse) {

            /* Only show Submitted submissions (ignore drafts)
             */
            if ($currentResponse->isSubmitted()) {
                $this->getRecombined()->add($currentResponse);

                /* Maintain a list of all the Feedbacks that were discovered
                 * as being part of Responses to ensure they don't get duped
                 * in the timeline
                 */
                foreach ($currentResponse->getAllFeedback() as $currentFeedback) {
                    $processedFeedback[] = $currentFeedback->getId();
                }
            }
        }

        /* Only grab unique Floating Feedbacks
         */
        foreach ($this->getParticipant()->getAllFeedbacks() as $currentFeedback) {
            if (!in_array($currentFeedback->getId(), $processedFeedback)) {

                /* Only show Published feedbacks
                 */
                if ($currentFeedback->isPublished()) {
                    $this->getRecombined()->add($currentFeedback);
                }
            }
        }

        /* Now sort them with a callback attack
         * Four possible cases to consider:
         *
         * Response compared with Response
         * Response compared with Feedback
         * Feedback compared with Feedback
         * Feedback compared with Response
         *
         * Since both have different date fields to compare against
         */
        $this->getRecombined()->sortByCallback(function($a, $b) {
            $date1 = null;
            $date2 = null;

            /* Compare dates
             */
            $date1 = $this->getDate($a);
            $date2 = $this->getDate($b);

            /* Chooze wisely
             */
            if ($date1 && $date2) {
                return $date2->compare($date1);
            }

            throw new \InvalidArgumentException('Null date provided');
        });
    }


    /**
     * @param IResponse|IFeedback $interaction
     * @return Alaress_Date_Format
     */
    protected function getDate($interaction)
    {
        if ($interaction instanceof IResponse) {
            if (!$interaction->getSubmittedAt()->isNull()) {
                return $interaction->getSubmittedAt();
            }

        } elseif ($interaction instanceof IFeedback) {
            if (!$interaction->getPublishedAt()->isNull()) {
                return $interaction->getPublishedAt();
            }
        }

        return $interaction->getUpdatedAt();
    }

    /* Getters/Setters
     */

    /**
     * @return Participant
     */
    protected function getParticipant()
    {
        return $this->participant;
    }

    /**
     * @param Participant $assessment
     * @return $this
     */
    protected function setParticipant($assessment)
    {
        $this->participant = $assessment;
        return $this;
    }

    /**
     * @return Collection
     */
    protected function getRecombined()
    {
        return $this->recombined;
    }

    /**
     * @param Collection $recombined
     * @return $this
     */
    protected function setRecombined($recombined)
    {
        $this->recombined = $recombined;
        return $this;
    }

    /**
     * @return boolean
     */
    protected function getHasRecombined()
    {
        return $this->hasRecombined;
    }

    /**
     * @param boolean $hasRecombined
     * @return $this
     */
    protected function setHasRecombined($hasRecombined)
    {
        $this->hasRecombined = $hasRecombined;
        return $this;
    }
}

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

namespace Schoolbox\Learning\Assessment\DueWork;

use \Schoolbox\Collection;
use \Schoolbox\Learning\Assessment\DueWork\Model as DueWork;
use \Schoolbox\Learning\Assessment\DueWork\Submission\Model as Submission;
use \Schoolbox\Learning\Assessment\DueWork\Feedback\Model as Feedback;

class Timeline
{

    /**
     * @var DueWork
     */
    private $assessment;

    /**
     * @var Collection
     */
    private $recombined;

    /**
     * Save yourself some time by returning the cached
     * version of the recombination if it exists
     *
     * @var bool
     */
    private $hasRecombined = false;

    /**
     * Timeline constructor.
     *
     * @param DueWork $assessment
     */
    public function __construct(DueWork $assessment)
    {
        $this->setAssessment($assessment);
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
    private function recombine()
    {
        $processedFeedback = [];

        /** @var Submission $currentResponse */
        foreach ($this->getAssessment()->getResponses() as $currentResponse) {
            $this->getRecombined()->add($currentResponse);

            /* Maintain a list of all the Feedbacks that were discovered
             * as being part of Responses to ensure they don't get duped
             * in the timeline
             */
            foreach ($currentResponse->getAllFeedback() as $currentFeedback) {
                $processedFeedback[] = $currentFeedback->getId();
            }
        }

        /* Only grab unique Floating Feedbacks
         */
        foreach ($this->getAssessment()->getFeedbacks() as $currentFeedback) {
            if (!in_array($currentFeedback->getId(), $processedFeedback)) {
                $this->getRecombined()->add($currentFeedback);
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

            /* Get the first date to compare against
             */
            if ($a instanceof Submission) {
                $date1 = $a->getSubmittedAt();
            }
            elseif ($a instanceof Feedback) {
                $date1 = $a->getPublishedAt();
            }

            /* Get the second date to compare against
             */
            if ($b instanceof Submission) {
                $date2 = $b->getSubmittedAt();
            }
            elseif ($b instanceof Feedback) {
                $date2 = $b->getPublishedAt();
            }

            /* Chooze wisely
             */
            if ($date1 && $date2) {
                return ($date1->eq($date2)) ? 0 : (($date1->gt($date2)) ? -1 : 1);
            }

            /* In some random way you might end up here
             */
            return -1;
        });
    }

    /* Getters/Setters
     */

    /**
     * @return Model
     */
    private function getAssessment()
    {
        return $this->assessment;
    }

    /**
     * @param Model $assessment
     * @return $this
     */
    private function setAssessment($assessment)
    {
        $this->assessment = $assessment;
        return $this;
    }

    /**
     * @return Collection
     */
    private function getRecombined()
    {
        return $this->recombined;
    }

    /**
     * @param Collection $recombined
     * @return $this
     */
    private function setRecombined($recombined)
    {
        $this->recombined = $recombined;
        return $this;
    }

    /**
     * @return boolean
     */
    private function getHasRecombined()
    {
        return $this->hasRecombined;
    }

    /**
     * @param boolean $hasRecombined
     * @return $this
     */
    private function setHasRecombined($hasRecombined)
    {
        $this->hasRecombined = $hasRecombined;
        return $this;
    }
}
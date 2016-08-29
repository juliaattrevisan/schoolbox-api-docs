<?php
/**
 * Project
 *
 * Composite Design Pattern
 *
 * This contains a Collection of IAssessables such as Due Work,
 * Quiz and Tasks.
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2011, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace Schoolbox\Learning\Assessment;

use \Schoolbox\Collection;

class Project extends AbstractAssessment
{

    /**
     * @var Collection
     */
    protected $assessables;

    /**
     * Project constructor.
     */
    public function __construct()
    {
        /* I know Dr hates hook methods, so do this instead
         */
        parent::__construct();

        $this->setAssessables(new Collection());
    }

    /**
     * Calculate how many IAssessable instances are in this Project
     *
     * @return int
     */
    public function numAssessables()
    {
        return $this->getAssessables()->count();
    }

    /**
     * Calculate how many Participant instances are in this Project
     *
     * @return int
     */
    public function numParticipants()
    {
        $denominator = 0;

        foreach ($this->getAssessables() as $currentAssessable) {
            $denominator += $currentAssessable->getAllParticipants()->count();
        }

        return $denominator;
    }

    /**
     * Calculate how many IAssessable instances have a non-null mark value
     *
     * The most recent action determines the marked/not-marked state of
     * an Assessable. This accounts for a series of edge cases which really
     * make you hate life
     *
     * For a particular Participant:
     *
     * If the Assessable contains zero Responses and zero Feedbacks:
     *     >>> not marked
     *
     * If the Assessable contains at least one Response and zero Feedbacks:
     *     >>> not marked
     *
     * If the Assessable contains zero Responses and at least one Feedback:
     *     >>> marked
     *
     * If the Assessable contains at least one Response and at least one Feedback:
     * Start traversing to find out what's going on.
     *          If the most recent Response contains a Feedback -OR-
     *          If a Feedback occurred more recently than the Response
     *          (ie. Feedback is the most recent action) >>> marked
     *
     *          Otherwise >>> not marked
     */
    public function numMarked()
    {
        $num = 0;

        /** @var IAssessable $currentAssessable */
        foreach ($this->getAssessables() as $currentAssessable) {

            /** @var Participant $currentParticipant */
            foreach ($currentAssessable->getAllParticipants() as $currentParticipant) {
                $isAssessableMarked = false;

                $numResponses = $currentParticipant->findAllResponsesForAssessable($currentAssessable)->count();
                $numFeedbacks = $currentParticipant->findAllFeedbackForAssessable($currentAssessable)->count();

                /* No Responses, No Feedback >>> not marked
                 */
                if (!$numResponses && !$numFeedbacks) {
                    $isAssessableMarked = false;
                }
                /* At least one Response, No Feedback >>> not marked
                 */
                elseif ($numResponses && !$numFeedbacks) {
                    $isAssessableMarked = false;
                }
                /* No Responses, at least one Feedback >>> marked
                 */
                elseif (!$numResponses && $numFeedbacks) {
                    $isAssessableMarked = true;
                }
                /* Time to inspect the stacks
                 */
                else {

                    /** @var IResponse $latestResponse */
                    $allResponses = $currentParticipant->findAllResponsesForAssessable($currentAssessable);
                    $latestResponse = $allResponses->first();

                    /** @var IFeedback $latestFeedback */
                    $allFeedbacks = $currentParticipant->findAllFeedbackForAssessable($currentAssessable);
                    $latestFeedback = $allFeedbacks->first();

                    /* Find the most recent Response
                     *
                     * @var IResponse $currentResponse
                     */
                    foreach ($allResponses as $currentResponse) {
                        if ($currentResponse->getUpdatedAt()->gt($latestResponse->getUpdatedAt())) {
                            $latestResponse = $currentResponse;
                        }
                    }

                    /* Find the most recent Feedback
                     *
                     * @var IFeedback $currentFeedback
                     */
                    foreach ($allFeedbacks as $currentFeedback) {
                        if ($currentFeedback->getUpdatedAt()->gt($latestFeedback->getUpdatedAt())) {
                            $latestFeedback = $currentFeedback;
                        }
                    }

                    /* Compare to see which one is the most recent action to occur
                     */
                    if ($latestFeedback->getUpdatedAt()->gt($latestResponse->getUpdatedAt())) {
                        $isAssessableMarked = true;
                    }
                }

                /* Increment if marked
                 */
                if ($isAssessableMarked) {
                    $num++;
                }
            }
        }

        return $num;
    }

    /**
     * Calculate percentage of a Project that has been marked
     *
     * @return int
     */
    public function calculatePercentageMarked()
    {
        if ($this->numAssessables()) {
            return 100 * ($this->numMarked() / $this->numParticipants());
        }

        return 0;
    }

    /**
     * Calculate the number of Feedbacks that have been Published
     * to Students
     *
     * @return int
     */
    public function numPublished()
    {
        $numPublished = 0;

        /** @var IAssessable $currentAssessable */
        foreach ($this->getAssessables() as $currentAssessable) {

            /** @var Participant $currentParticipant */
            foreach ($currentAssessable->getAllParticipants() as $currentParticipant) {

                /** @var AbstractResponse $currentResponse */
                foreach ($currentParticipant->findAllResponsesForAssessable($currentAssessable) as $currentResponse) {
                    if ($currentResponse->findMostRecentPublishedFeedback()) {
                        $numPublished++;
                    }
                }
            }
        }

        return $numPublished;
    }

    /**
     * Calculate percentage of a Project that has been Published
     *
     * @return int
     */
    public function calculatePercentagePublished()
    {
        if ($this->numAssessables()) {
            return 100 * ($this->numPublished() / $this->numParticipants());
        }
    }

    /**
     * @inheritdoc
     *
     * @return string
     */
    public function getTypeName()
    {
        return 'Project';
    }

    /* Getters/Setters
     */

    /**
     * @return Collection
     */
    public function getAssessables()
    {
        return $this->assessables;
    }

    /**
     * @param Collection $assessables
     * @return self
     */
    public function setAssessables($assessables)
    {
        $this->assessables = $assessables;
        return $this;
    }
}
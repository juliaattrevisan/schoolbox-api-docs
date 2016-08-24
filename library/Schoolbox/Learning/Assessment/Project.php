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
     */
    public function numMarked()
    {
        $numMarked = 0;

        /** @var IAssessable $currentAssessable */
        foreach ($this->getAssessables() as $currentAssessable) {

            /** @var Participant $currentParticipant */
            foreach ($currentAssessable->getAllParticipants() as $currentParticipant) {

                /** @var AbstractResponse $currentResponse */
                foreach ($currentParticipant->findAllResponsesForAssessable($currentAssessable) as $currentResponse) {
                    if ($currentResponse->findMostRecentFeedback()) {
                        $numMarked++;
                    }
                }
            }
        }

        return $numMarked;
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
<?php
/**
 * Provides the offline version of the MarkbookInterface
 *
 * This is used by all connectors if it is not implemented. The purpose is just to do nothing, because we have no
 * markbook to integrate with.
 *
 * At the time of writing this is only implemented for Synergetic so this version will be used for all other systems
 *
 * @author      James Leckie <jleckie@alaress.com.au>
 * @copyright   Copyright 2015, Alaress Pty Ltd
 */

use \Schoolbox\Assessment\Activity;

class External_Offline_Markbook implements External_MarkbookInterface
{
    /**
     * @inheritdoc
     */
    public function getAssessment(Activity $assessment)
    {
        return [];
    }

    /**
     * @inheritdoc
     */
    public function hasAssessment(Activity $assessment)
    {
        return false;
    }

    /**
     * @inheritdoc
     */
    public function createAssessment(Activity $assessment)
    {
        return [];
    }

    /**
     * @inheritdoc
     */
    public function updateAssessment(Activity $assessment)
    {
        return [];
    }

    /**
     * @inheritDoc
     */
    public function deleteAssessment(Activity $assessment)
    {
        return [];
    }

    /**
     * @inheritdoc
     */
    public function setMark($returnedWork)
    {
    }
}
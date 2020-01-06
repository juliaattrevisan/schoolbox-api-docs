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
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 */

use External\IOfflineConnector;
use Schoolbox\Learning\Assessment\IAssessable;
use Schoolbox\Learning\Assessment\IFeedback;

class External_Offline_Markbook implements External_MarkbookInterface, IOfflineConnector
{
    /**
     * The offline markbook doesn't sync anything.
     *
     * @inheritDoc
     */
    public function isSyncable(IAssessable $assessment): bool
    {
        return false;
    }

    /**
     * @inheritdoc
     */
    public function syncAssessment(IAssessable $assessment): bool
    {
        return false;
    }

    /**
     * @inheritdoc
     */
    public function getAssessment(IAssessable $assessment): array
    {
        return [];
    }

    /**
     * @inheritdoc
     */
    public function hasAssessment(IAssessable $assessment): bool
    {
        return false;
    }

    /**
     * @inheritdoc
     */
    public function createAssessment(IAssessable $assessment): bool
    {
        return false;
    }

    /**
     * @inheritdoc
     */
    public function updateAssessment(IAssessable $assessment): bool
    {
        return false;
    }

    /**
     * @inheritDoc
     */
    public function deleteAssessment(IAssessable $assessment): bool
    {
        return false;
    }

    /**
     * @inheritdoc
     */
    public function syncFeedback(IFeedback $returnedWork): bool
    {
        return false;
    }
}

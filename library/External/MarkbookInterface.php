<?php
/**
 * This interface defines how to store markbook data in a remote SIS
 *
 * @author      James Leckie <jleckie@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

use Schoolbox\Learning\Assessment\IAssessable;
use Schoolbox\Learning\Assessment\IFeedback;

interface External_MarkbookInterface extends External\IConnector
{
    /**
     * Is this assessment syncable to this external markbook?
     *
     * @param IAssessable $assessment
     * @return bool
     */
    public function isSyncable(IAssessable $assessment): bool;

    /**
     * Returns the assessment information if found in remote system
     *
     * @param IAssessable $assessment
     * @return array
     */
    public function getAssessment(IAssessable $assessment): array;

    /**
     * Syncs this assessment to the markbook.
     *
     * @param IAssessable $assessment
     * @return bool
     */
    public function syncAssessment(IAssessable $assessment): bool;

    /**
     * Indicates if assessment exists in external system's markbook
     *
     * @param IAssessable $assessment
     * @return bool
     */
    public function hasAssessment(IAssessable $assessment): bool;

    /**
     * Deletes an existing markbook assessment
     *
     * @param IAssessable $assessment
     * @return bool
     */
    public function deleteAssessment(IAssessable $assessment): bool;

    /**
     * Creates or updates the mark for a student in the remote markbook
     *
     * @param IFeedback $returnedWork
     * @return bool
     */
    public function syncFeedback(IFeedback $returnedWork): bool;
}


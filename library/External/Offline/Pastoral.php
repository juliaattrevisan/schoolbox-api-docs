<?php
/**
 * Provides the offline version of the PastoralInterface
 * This is used by all connectors if it is not implemented.  The purpose is just to do nothing, because we have no
 * pastoral to integrate with.
 * At the time of writing this is only implemented for Synergetic so this version will be used for all other systems
 *
 * @author      Damian Sloane <sloane@alaress.com.au>
 * @copyright   Copyright 2015, Alaress Pty Ltd
 */

use Schoolbox\Pastoral\Comment\Model as PastoralComment;
use Schoolbox\Pastoral\Model as PastoralModel;
use Schoolbox\Pastoral\UploadQueue\Model as UploadQueue;

class External_Offline_Pastoral implements External_PastoralInterface
{
    /**
     * Returns the pastoral record if it exists
     *
     * @param PastoralModel $pastoral
     * @return array
     */
    public function getPastoral(PastoralModel $pastoral)
    {
        // NOOP
        return array();
    }

    /**
     * Creates a new pastoral record in the external database
     *
     * @param PastoralModel $pastoral
     * @return array
     */
    public function createPastoral(PastoralModel $pastoral)
    {
        // NOOP
        return array();
    }

    /**
     * Updates an existing pastoral record
     *
     * @param PastoralModel $pastoral
     * @return array
     */
    public function updatePastoral(PastoralModel $pastoral)
    {
        // NOOP
        return array();
    }

    /**
     * @inheritdoc
     */
    public function createComment(PastoralComment $comment)
    {
        // NOOP
        return false;
    }

    /**
     * @inheritdoc
     */
    public function deleteComment(PastoralComment $comment)
    {
        // NOOP
        return false;
    }

    /**
     * @inheritdoc
     */
    public function uploadPastoralDocument(UploadQueue $upload)
    {
        // NOOP
        return false;
    }

    /**
     * @inheritdoc
     */
    public function importPastoral($userId, $defaultOwnerId, $importParentAccess)
    {
        // NOOP
    }

    /**
     * @inheritDoc
     */
    public function deletePastoral(PastoralModel $pastoral)
    {
        // NOOP
        return false;
    }
}
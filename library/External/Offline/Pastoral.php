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

use \Schoolbox\Pastoral\Pastoral;

class External_Offline_Pastoral implements External_PastoralInterface
{
    /**
     * Returns the pastoral record if it exists
     *
     * @param \Schoolbox\Pastoral\Pastoral\Model $pastoral
     * @return array
     */
    public function getPastoral(\Schoolbox\Pastoral\Pastoral\Model $pastoral)
    {
        // NOOP
        return array();
    }

    /**
     * Creates a new pastoral record in the external database
     *
     * @param \Schoolbox\Pastoral\Pastoral\Model $pastoral
     * @return array
     */
    public function createPastoral(\Schoolbox\Pastoral\Pastoral\Model $pastoral)
    {
        // NOOP
        return array();
    }

    /**
     * Updates an existing pastoral record
     *
     * @param \Schoolbox\Pastoral\Pastoral\Model $pastoral
     * @return array
     */
    public function updatePastoral(\Schoolbox\Pastoral\Pastoral\Model $pastoral)
    {
        // NOOP
        return array();
    }

    /**
     * Returns the comment record if it exists
     *
     * @param \Schoolbox\Pastoral\Comment\Model $comment
     * @return array
     */
    public function getComment(\Schoolbox\Pastoral\Comment\Model $comment)
    {
        // NOOP
        return array();
    }

    /**
     * Creates a new comment record in the external database
     *
     * @param \Schoolbox\Pastoral\Comment\Model $comment
     * @return array
     */
    public function createComment(\Schoolbox\Pastoral\Comment\Model $comment)
    {
        // NOOP
        return array();
    }

    /**
     * Updates an existing comment record
     *
     * @param \Schoolbox\Pastoral\Comment\Model $comment
     * @return array
     */
    public function updateComment(\Schoolbox\Pastoral\Comment\Model $comment)
    {
        // NOOP
        return array();
    }

    /**
     * Import pastoral care records from external SIS
     */
    public function importPastoral($userId)
    {
        // NOOP
    }

    /**
     * @inheritDoc
     */
    public function deletePastoral(\Schoolbox\Pastoral\Pastoral\Model $pastoral)
    {
        // NOOP
        return false;
    }
}
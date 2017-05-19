<?php
/**
 * This interface defines how to store pastoral data in a remote SIS
 *
 * @author      Damian Sloane <dsloane@alaress.com.au>
 * @copyright   Copyright 2016, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */

use Schoolbox\Pastoral\Comment\Model as PastoralCommentModel;
use Schoolbox\Pastoral\Model as PastoralModel;
use Schoolbox\Pastoral\UploadQueue\Model as UploadQueue;
use Schoolbox\User;

interface External_PastoralInterface
{
    /**
     * Import pastoral care records from external SIS
     *
     * @param User $user Schoolbox user ID of person doing the import
     * @param User $defaultOwner Schoolbox user ID of person who should be set at fallback owner of record
     * @param bool $importParentAccess Flag indicating if parent contacts should be added to the pastoral_access table
     * @return bool
     */
    public function importPastoral($user, $defaultOwner, $importParentAccess);

    /**
     * Returns the pastoral record if found in remote system
     *
     * @param PastoralModel $pastoral
     * @return array
     */
    public function getPastoral(PastoralModel $pastoral);

    /**
     * Creates a new pastoral record in the remote system
     *
     * @param PastoralModel $pastoral
     * @return bool
     */
    public function createPastoral(PastoralModel $pastoral);

    /**
     * Updates an existing pastoral record
     *
     * @param PastoralModel $pastoral
     * @return bool
     */
    public function updatePastoral(PastoralModel $pastoral);

    /**
     * Deletes an existing pastoral record
     *
     * @param PastoralModel $pastoral
     * @return bool
     */
    public function deletePastoral(PastoralModel $pastoral);

    /**
     * Creates a new pastoral comment in the remote system
     *
     * @param PastoralCommentModel $comment
     * @return bool|int ID of newly created comment
     */
    public function createComment(PastoralCommentModel $comment);

    /**
     * Deletes an existing comment record
     *
     * @param PastoralCommentModel $comment
     * @return bool
     */
    public function deleteComment(PastoralCommentModel $comment);

    /**
     * Upload pastoral care document that are currently queued
     * @param UploadQueue $upload
     * @return int|bool
     * @throws \Schoolbox\External\Exception
     */
    public function uploadPastoralDocument(UploadQueue $upload);
}



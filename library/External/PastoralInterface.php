<?php
/**
 * @author      Damian Sloane <dsloane@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

use Schoolbox\Pastoral;
use Schoolbox\User;

/**
 * This interface defines how to store pastoral data in a remote SIS
 */
interface External_PastoralInterface extends External\IConnector
{
    /**
     * Returns the pastoral record if found in remote system
     *
     * @param Pastoral\Model $pastoral
     * @return array
     */
    public function getPastoral(Pastoral\Model $pastoral): array;

    /**
     * Import pastoral care records from external SIS
     *
     * @param User $user Schoolbox user doing the import
     * @param User $defaultOwner Schoolbox user ID of person who should be set at fallback owner of record
     * @param bool $importParentAccess Flag indicating if parent contacts should be added to the pastoral_access table
     * @param bool $importRecords Flag indicating pastoral records should be imported from the SIS
     * @param bool $importAttachments Flag indicating pastoral record attachments should be imported from the SIS
     * @param bool $deleteRecords Flag to indicate if records should be deleted and recreated, or skipped if already existing
     */
    public function importPastoral(
        User $user,
        User $defaultOwner,
        bool $importParentAccess,
        bool $importRecords,
        bool $importAttachments,
        bool $deleteRecords
    ): void;

    /**
     * Sync a schoolbox pastoral record with the remote system
     *
     * Returns whether we *created* a record in the SIS (ie, an update will return false)
     *
     * @param Pastoral\Model $pastoral
     * @return bool
     */
    public function syncPastoral(Pastoral\Model $pastoral): bool;

    /**
     * Deletes an existing pastoral record
     *
     * Returns whether all corresponding records in the SIS were successfully deleted
     *
     * @param Pastoral\Model $pastoral
     * @return bool
     */
    public function deletePastoral(Pastoral\Model $pastoral): bool;

    /**
     * Creates a new pastoral comment in the remote system
     *
     * Returns whether a comment was created in the remote system
     *
     * @param Pastoral\Comment\Model $comment
     * @return bool
     */
    public function createComment(Pastoral\Comment\Model $comment): bool;

    /**
     * Deletes an existing comment record
     *
     * Returns whether any items were deleted
     *
     * @param Pastoral\Comment\Model $comment
     * @return bool
     */
    public function deleteComment(Pastoral\Comment\Model $comment): bool;

    /**
     * Upload pastoral care document that are currently queued
     *
     * Returns whether the document was uploaded to remove system
     *
     * @param Pastoral\UploadQueue\Model $upload
     * @return bool
     * @throws \Schoolbox\External\Exception
     */
    public function uploadPastoralDocument(Pastoral\UploadQueue\Model $upload): bool;

    /**
     * Gets available pastoral types
     * @return array
     */
    public function getTypeList(): array;

    /**
     * Gets available pastoral subtypes
     * @return array
     */
    public function getSubtypeList(): array;

    /**
     * Gets available document classifications
     * @return array
     */
    public function getDocumentClassificationList(): array;
}

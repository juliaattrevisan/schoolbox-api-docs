<?php
/**
 * Provides the offline version of the PastoralInterface
 * This is used by all connectors if it is not implemented.  The purpose is just to do nothing, because we have no
 * pastoral to integrate with.
 * At the time of writing this is only implemented for Synergetic so this version will be used for all other systems
 *
 * @author      Damian Sloane <sloane@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 */

use External\IOfflineConnector;
use Schoolbox\Pastoral\Comment\Model as PastoralComment;
use Schoolbox\Pastoral\Model as PastoralModel;
use Schoolbox\Pastoral\UploadQueue\Model as UploadQueue;
use Schoolbox\User;

class External_Offline_Pastoral implements External_PastoralInterface, IOfflineConnector
{
    /**
     * {@inheritDoc}
     */
    public function getPastoral(PastoralModel $pastoral): array
    {
        // NOOP
        return array();
    }

    /**
     * {@inheritDoc}
     */
    public function syncPastoral(PastoralModel $pastoral): bool
    {
        // NOOP
        return false;
    }

    /**
     * @inheritdoc
     */
    public function createComment(PastoralComment $comment): bool
    {
        // NOOP
        return false;
    }

    /**
     * @inheritdoc
     */
    public function deleteComment(PastoralComment $comment): bool
    {
        // NOOP
        return false;
    }

    /**
     * @inheritdoc
     */
    public function uploadPastoralDocument(UploadQueue $upload): bool
    {
        // NOOP
        return false;
    }

    /**
     * @inheritdoc
     */
    public function importPastoral(
        User $user,
        User $defaultOwner,
        bool $importParentAccess,
        bool $importRecords,
        bool $importAttachments,
        bool $deleteRecords
    ): void {
        // NOOP
    }

    /**
     * @inheritDoc
     */
    public function deletePastoral(PastoralModel $pastoral): bool
    {
        // NOOP
        return false;
    }

    /**
     * @inheritDoc
     */
    public function getTypeList(): array
    {
        return [];
    }

    /**
     * @inheritDoc
     */
    public function getSubtypeList(): array
    {
        return [];
    }

    /**
     * @inheritDoc
     */
    public function getDocumentClassificationList(): array
    {
        return [];
    }

}

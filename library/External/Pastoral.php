<?php
/**
 * @author      Damian Sloane <dsloane@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

use Schoolbox\Pastoral;
use Schoolbox\User;

class External_Pastoral implements External_PastoralInterface
{
    /** @var External_PastoralInterface */
    private $database;

    /**
     * Constructor
     *
     * @throws Exception
     * @param String $databaseName
     */
    public function __construct(string $databaseName = '')
    {
        $this->database = (new External_DatabaseFactory())->buildPastoralConnector($databaseName);
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
        $this->database->importPastoral($user, $defaultOwner, $importParentAccess, $importRecords, $importAttachments, $deleteRecords);
    }

    /**
     * @inheritdoc
     */
    public function getPastoral(Pastoral\Model $pastoral): array
    {
        return $this->database->getPastoral($pastoral);
    }

    /**
     * @inheritdoc
     */
    public function syncPastoral(Pastoral\Model $pastoral): bool
    {
        return $this->database->syncPastoral($pastoral);
    }

    /**
     * @inheritDoc
     */
    public function deletePastoral(Pastoral\Model $pastoral): bool
    {
        return $this->database->deletePastoral($pastoral);
    }

    /**
     * @inheritdoc
     */
    public function createComment(Pastoral\Comment\Model $comment): bool
    {
        return $this->database->createComment($comment);
    }

    /**
     * @inheritDoc
     */
    public function deleteComment(Pastoral\Comment\Model $comment): bool
    {
        return $this->database->deleteComment($comment);
    }

    /**
     * @inheritDoc
     */
    public function uploadPastoralDocument(Pastoral\UploadQueue\Model $upload): bool
    {
        return $this->database->uploadPastoralDocument($upload);
    }

    /**
     * @inheritDoc
     */
    public function getTypeList(): array
    {
        return $this->database->getTypeList();
    }

    /**
     * @inheritDoc
     */
    public function getSubtypeList(): array
    {
        return $this->database->getSubtypeList();
    }

    /**
     * @inheritDoc
     */
    public function getDocumentClassificationList(): array
    {
        return $this->database->getDocumentClassificationList();
    }
}

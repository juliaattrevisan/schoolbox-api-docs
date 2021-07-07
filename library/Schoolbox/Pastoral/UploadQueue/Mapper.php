<?php

/**
 * @author      Damian Sloane <damian.sloane@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Schoolbox\Pastoral\UploadQueue;

use PDOStatement;
use Schoolbox\GenericCollection;
use Schoolbox\File\Mimetype;
use Schoolbox\File\MimetypeMapper;
use Schoolbox\Mapper as BaseMapper;
use Schoolbox\Pastoral\Mapper as PastoralMapper;
use Schoolbox\Pastoral\Model as Pastoral;
use Schoolbox\Resource\Storage\Mapper as StorageMapper;
use Schoolbox\Resource\Storage\Model as Storage;

/**
 * @method Model createModel($idValue = null)
 */
class Mapper extends BaseMapper
{
    const UPLOAD_STATUS_PENDING = 'Pending';
    const UPLOAD_STATUS_PROCESSING = 'Processing';
    const UPLOAD_STATUS_FAILED = 'Failed';
    const UPLOAD_STATUS_COMPLETED = 'Completed';

    /**
     * Setup mapper
     */
    protected function mapping()
    {
        $this->hasTable('pastoral_upload_queue');
        $this->hasModel(Model::class);
        $this->uses('id', self::TYPE_INTEGER, self::COLUMN_IDENTIFIER);
        $this->joins('pastoral_id', PastoralMapper::class, 'id', null, null, 'pastoral', 'pastoral');
        $this->joins('hash', StorageMapper::class, 'hash', null, null, 'storage', 'storage');
        $this->uses('realname', self::TYPE_STRING);
        $this->uses('external_id', self::TYPE_INTEGER);
        $this->uses('student_external_id', self::TYPE_INTEGER);
        $this->uses('status', self::TYPE_STRING);
        $this->uses('message', self::TYPE_STRING);
        $this->uses('created_at', self::TYPE_DATETIME, self::COLUMN_CREATED_TIMESTAMP);
        $this->uses('updated_at', self::TYPE_DATETIME, self::COLUMN_UPDATED_TIMESTAMP);
        $this->uses('deleted_at', self::TYPE_DATETIME, self::COLUMN_SOFTDELETE);
    }

    /**
     * @inheritdoc
     */
    protected function getSelectString()
    {
        return "

            SELECT      {$this->getColumnString()},
                        {$this->getJoinColumnString('pastoral', 'p')},
                        {$this->getJoinMapper('storage')->getColumnString('s')},
				        {$this->getJoinMapper('storage')->getJoinColumnString('allowedFileExtension', 'mime')}
            FROM        {$this->getTable()}
            INNER JOIN  {$this->getJoinSql('pastoral', '', 'p', true)}
			INNER JOIN  {$this->getJoinSql('storage', '', 's')}
			LEFT JOIN   {$this->getJoinMapper('storage')->getJoinSql('allowedFileExtension', 's', 'mime')}
        ";
    }

    /**
     * @inheritdoc
     *
     * @param PDOStatement $statement
     *
     * @return GenericCollection|Model[]
     */
    protected function hydrateSelect(PDOStatement $statement)
    {
        $collection = $this->createCollection();

        /** @var MimetypeMapper $mimeMapper */
        $mimeMapper = $this->getJoinMapper('storage')->getJoinMapper('allowedFileExtension');

        foreach ($statement as $row) {
            /** @var Model $model */
            $model = $this->hydrate($row);

            /** @var Pastoral $pastoral */
            $pastoral = $this->hydrateJoin($row, 'pastoral', 'p');
            $model->setPastoral($pastoral);

            if (!empty($row['hash'])) {
                /** @var Storage $storage */
                $storage = $this->hydrateJoin($row, 'storage', 's');

                // Hydrate a mime type if we can find one

                /** @var Mimetype $mime */
                $mime = $mimeMapper->hydrate($row, 'mime');

                if ($mime instanceof Mimetype) {
                    $storage->setMimetype($mime);
                }

                $model->setStorage($storage);
            }

            $collection->add($model);
        }

        return $collection;
    }

    /**
     * @return GenericCollection|Model[]
     */
    public function getPending(): GenericCollection
    {
        return $this->findByColumn('status', self::UPLOAD_STATUS_PENDING);
    }

    /**
     * @param int $timeout
     *
     * @return int
     */
    public function markProcessingAsFailed($timeout)
    {
        $statement = $this->database->prepare("
            UPDATE {$this->getTable()}
            SET    status = :new_status, message = :message
            WHERE  status = :old_status
            AND    (updated_at + INTERVAL :timeout SECOND) < NOW()
        ");
        $statement->bindValue('old_status', self::UPLOAD_STATUS_PROCESSING);
        $statement->bindValue('new_status', self::UPLOAD_STATUS_FAILED);
        $statement->bindValue('message', sprintf('Timed out - failed to finish processing after %d seconds', $timeout));
        $statement->bindValue('timeout', intval($timeout), \PDO::PARAM_INT);
        $statement->execute();

        return $statement->rowCount();
    }
}

<?php
/**
 *
 * @author      Damian Sloane <damian.sloane@alaress.com.au>
 * @copyright   Copyright 2016, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace Schoolbox\Pastoral\UploadQueue;

use Schoolbox\Mapper as BaseMapper;
use Schoolbox\Pastoral\UploadQueue\Model as PastoralUploadQueueModel;

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
        $this->hasModel(PastoralUploadQueueModel::class);
        $this->uses('id', self::TYPE_INTEGER, self::COLUMN_IDENTIFIER);
        $this->uses('external_id', self::TYPE_INTEGER);
        $this->uses('student_external_id', self::TYPE_INTEGER);
        $this->uses('pastoral_id', self::TYPE_INTEGER);
        $this->uses('hash', self::TYPE_STRING);
        $this->uses('type', self::TYPE_STRING);
        $this->uses('status', self::TYPE_STRING);
        $this->uses('message', self::TYPE_STRING);
        $this->uses('created_at', self::TYPE_DATETIME);
        $this->uses('updated_at', self::TYPE_DATETIME);
        $this->uses('deleted_at', self::TYPE_DATETIME);
        $this->hasSoftDeleteDateField('deleted_at');
    }


    /**
     * @return \Schoolbox\Collection
     */
    public function getPending() {
        return $this->findByColumn('status', self::UPLOAD_STATUS_PENDING);
    }

    /**
     * @param int $timeout
     * @return int
     */
    public function markProcessingAsFailed($timeout) {
        $statement = $this->database->prepare("
            UPDATE {$this->getTable()}
            SET    status = :new_status, message = :message
            WHERE  status = :old_status
            AND    (updated_at + INTERVAL :timeout SECOND) < NOW()
        ");
        $statement->bindValue('old_status', self::UPLOAD_STATUS_PROCESSING);
        $statement->bindValue('new_status', self::UPLOAD_STATUS_FAILED);
        $statement->bindValue('message',    sprintf('Timed out - failed to finish processing after %d seconds', $timeout));
        $statement->bindValue('timeout',    intval($timeout), \PDO::PARAM_INT);
        $statement->execute();

        return $statement->rowCount();
    }
}

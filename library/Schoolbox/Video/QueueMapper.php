<?php
/**
 * During video transcoding, videos are loaded into the queue then processed in the background.
 * This library enables interaction with the queue, to load and process videos
 *
 * @author      James Leckie <james.leckie@alaress.com.au>
 * @copyright   2013 Alaress Pty Ltd
 * @link        http://www.alaress.com.au
 */

namespace Schoolbox\Video;

use PDO;
use PDOStatement;
use Schoolbox\Collection;
use Schoolbox\Mapper;
use Schoolbox\Resource\Storage;
use Schoolbox\Mapper\Exception as MapperException;

class QueueMapper extends Mapper {

    /* Possible video states */
    const STATUS_PENDING='pending';
    const STATUS_PROCESSING='processing';
    const STATUS_COMPLETED='completed';
	const STATUS_FAILED='failed';

    /* Possible video types */
    const FILE_TYPE_SBFS = 'sbfs';
    const FILE_TYPE_STORAGE = 'storage';
	const FILE_TYPE_SUBMISSION = 'submission';
	const FILE_TYPE_MYFILE = 'myfile';

    /**
     * Setup mapper
     */
    protected function mapping ()
    {
        $this->hasTable('video_queue');
        $this->hasModel(Queue::class);
        $this->uses('video_queue_id', self::TYPE_INTEGER, self::COLUMN_IDENTIFIER, 'id');
        $this->uses('file_id', self::TYPE_INTEGER);
        $this->uses('hash', self::TYPE_STRING);
        $this->joins('hash', Storage\Mapper::class, 'hash', null, null, 'file', 'file');
        $this->uses('status', self::TYPE_STRING);
        $this->uses('message', self::TYPE_STRING);
        $this->uses('duration', self::TYPE_INTEGER);
        $this->uses('createdOn', self::TYPE_DATETIME, null, 'createdAt');
        $this->uses('modifiedOn', self::TYPE_DATETIME, null, 'updatedAt');
    }

    /**
     * Return all pending transcodes
     * @return \Schoolbox\GenericCollection
     */
    public function getPending() {
        return $this->findByColumn('status', self::STATUS_PENDING);
    }

    /**
     * Mark videos that have been processing for longer than $timeout seconds as
     * having timed out.
     *
     * @param  int $timeout
     * @return int How many videos were marked as timed out?
     */
    public function markAllProcessingAsTimedOut($timeout) {
        $statement = $this->database->prepare("
            UPDATE {$this->getTable()}
            SET    status = :new_status, message = :message
            WHERE  status = :old_status
            AND    (modifiedOn + INTERVAL :timeout SECOND) < NOW()
        ");
        $statement->bindValue('old_status', self::STATUS_PROCESSING);
        $statement->bindValue('new_status', self::STATUS_FAILED);
        $statement->bindValue('message',    sprintf('Timed out - failed to finish processing after %d seconds', $timeout));
        $statement->bindValue('timeout',    intval($timeout), \PDO::PARAM_INT);
        $statement->execute();
        return $statement->rowCount();
    }

    /**
     * @param PDOStatement $statement
     * @return Collection
     */
    protected function hydrateSelect(PDOStatement $statement)
    {
        $hydratedCollection = $this->createCollection();

        while ($row = $statement->fetch(PDO::FETCH_ASSOC)) {

            $record = $this->hydrate($row);

            // Can't hydrate storage if there isn't a hash

            if (!empty($row['hash'])) {
                $record->setStorage($this->hydrateJoin($row, 'file'));
            }

            $hydratedCollection->add($record);
        }

        return $hydratedCollection;
    }
}

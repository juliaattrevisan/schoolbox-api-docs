<?php

/**
 * During video transcoding, videos are loaded into the queue then processed in the background.
 * This library enables interaction with the queue, to load and process videos
 *
 * @author      James Leckie <james.leckie@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Schoolbox\Video;

use PDO;
use PDOStatement;
use Schoolbox\Collection;
use Schoolbox\File\Mimetype;
use Schoolbox\File\MimetypeMapper;
use Schoolbox\Mapper;
use Schoolbox\Resource\Storage;
use Schoolbox\Resource\Storage\IStorage;
use Schoolbox\UserMapper;

/**
 * Mapper for queued video processes.
 *
 * @method Queue createModel($id = null)
 */
class QueueMapper extends Mapper
{

    /* Possible video states */
    const STATUS_PENDING = 'pending';
    const STATUS_PROCESSING = 'processing';
    const STATUS_COMPLETED = 'completed';
    const STATUS_FAILED = 'failed';

    /* Possible video types */
    const FILE_TYPE_SBFS = 'sbfs';
    const FILE_TYPE_STORAGE = 'storage';
    const FILE_TYPE_SUBMISSION = 'submission';
    const FILE_TYPE_MYFILE = 'myfile';

    /**
     * Setup mapper
     */
    protected function mapping()
    {
        $this->hasTable('video_queue');
        $this->hasModel(Queue::class);
        $this->uses('video_queue_id', self::TYPE_INTEGER, self::COLUMN_IDENTIFIER, 'id');
        $this->joins('hash', Storage\Mapper::class, 'hash', null, null, 'storage', 'storage');
        $this->joins('uploaded_by', UserMapper::class, 'id', null, null, 'user', 'user');
        $this->uses('status', self::TYPE_STRING);
        $this->uses('message', self::TYPE_STRING);
        $this->uses('duration', self::TYPE_INTEGER);
        $this->uses('createdOn', self::TYPE_DATETIME, self::COLUMN_CREATED_TIMESTAMP, 'createdAt');
        $this->uses('modifiedOn', self::TYPE_DATETIME, self::COLUMN_UPDATED_TIMESTAMP, 'updatedAt');
    }


    /**
     * Find out if $file had an error during transcode
     *
     * @param IStorage $file
     * @return bool
     */
    public function hasError(IStorage $file): bool
    {
        /* Find the transcode status for transcodable items
         * Namely audio files and video files
         */
        $error = true;

        if ($file->getMimetype()->isAudio() || $file->getMimetype()->isVideo()) {
            try {
                $videoQueueItem = $this->findOneByColumn('hash', $file->getHash());

                /* If it was in the queue, check its processing status
                 * If it's complete, we can show it normally
                 * Otherwise send the $error flag into the ui
                 */
                if ($videoQueueItem->isComplete()) {
                    $error = false;
                }
            } catch (\Schoolbox\Mapper\Exception $e) {
                // noop: hash not found
                // This file was never in the queue, so I guess we don't load it
            }
        }

        return $error;
    }


    /**
     * Return all pending transcodes
     * @return \Schoolbox\GenericCollection
     */
    public function getPending()
    {
        return $this->findByColumn('status', self::STATUS_PENDING);
    }

    /**
     * @param string|array $status
     * @param array|null $options
     * @return array|\Schoolbox\GenericCollection
     */
    public function findByStatus($status, $options = null)
    {
        $result = [];

        if (is_array($status)) {
            foreach ($status as $statusCurrent) {
                $results = $this->findByColumn('status', $statusCurrent, $options);

                if (!empty($results)) {
                    /** @var \Schoolbox\Video\Queue $videoQueue */
                    foreach ($results as $videoQueue) {
                        if ($videoQueue->getStorage() instanceof Storage\Model) {
                            $result[$statusCurrent][] = $videoQueue;
                        }
                    }
                }
            }
        } else {
            $results = $this->findByColumn('status', $status, $options);

            if (!empty($results)) {
                /** @var \Schoolbox\Video\Queue $videoQueue */
                foreach ($results as $videoQueue) {
                    if ($videoQueue->getStorage() instanceof Storage\Model) {
                        $result[] = $videoQueue;
                    }
                }
            }
        }

        return $result;
    }

    /**
     * Mark videos that have been processing for longer than $timeout seconds as
     * having timed out.
     *
     * @deprecated Should not be necessary as `\Schoolbox\Video\Queue::process`
     *             sets a time limit on the encoding process: if the time limit
     *             is reached, the encoding is marked as failed.
     *
     * @param  int $timeout
     * @return int How many videos were marked as timed out?
     */
    public function markAllProcessingAsTimedOut($timeout)
    {
        $statement = $this->database->prepare("
            UPDATE {$this->getTable()}
            SET    status = :new_status, message = :message
            WHERE  status = :old_status
            AND    (modifiedOn + INTERVAL :timeout SECOND) < NOW()
        ");
        $statement->bindValue('old_status', self::STATUS_PROCESSING);
        $statement->bindValue('new_status', self::STATUS_FAILED);
        $statement->bindValue('message', sprintf('Timed out - failed to finish processing after %d seconds', $timeout));
        $statement->bindValue('timeout', intval($timeout), \PDO::PARAM_INT);
        $statement->execute();
        return $statement->rowCount();
    }

    /**
     * @inheritdoc
     */
    protected function getSelectString()
    {
        $table = $this->database->getQuoter()->quoteIdentifier($this->table);

        $storageMapper = $this->getJoinMapper('storage');

        $query = "
            SELECT      {$this->getColumnString()},
                        {$this->getJoinColumnString('user', 'u')},
                        {$storageMapper->getColumnString('s')},
				        {$storageMapper->getJoinColumnString('allowedFileExtension', 'mime')}
            FROM        {$table}
            LEFT JOIN   {$this->getJoinSql('user', '', 'u')}
			JOIN   {$this->getJoinSql('storage', '', 's')}
			LEFT JOIN   {$storageMapper->getJoinSql('allowedFileExtension', 's', 'mime', true)}
        ";

        return $query;
    }

    /**
     * @param PDOStatement $statement
     * @return Collection
     */
    protected function hydrateSelect(PDOStatement $statement)
    {
        $hydratedCollection = $this->createCollection();

        // Shut the front door there are a goddamn lot of hoops to jump through to get a mime type!
        //
        //        ▄████████████▄▐█▄▄▄▄█▌
        //        █████▌▄▌▄▐▐▌██▌▀▀██▀▀
        //        ███▄█▌▄▌▄▐▐▌▀██▄▄█▌
        //        ▄▄▄▄█████████████

        /** @var Storage\Mapper $storageMapper */
        $storageMapper = $this->getJoinMapper('storage');

        /** @var MimetypeMapper $mimeMapper */
        $mimeMapper = $storageMapper->getJoinMapper('allowedFileExtension');

        /** @var UserMapper $userMapper */
        $userMapper = $this->getJoinMapper('user');

        while ($row = $statement->fetch(PDO::FETCH_ASSOC)) {

            /** @var Queue $record */
            $record = $this->hydrate($row)->setUser($userMapper->hydrate($row, 'u'));

            // Can't hydrate storage if there isn't a hash

            if (!empty($row['hash'])) {

                /** @var Storage\Model $storageObject */
                $storageObject = $this->hydrateJoin($row, 'storage', 's');

                // Hydrate a mime type if we can find one

                /** @var Mimetype $mime */
                $mime = $mimeMapper->hydrate($row, 'mime');

                if ($mime instanceof Mimetype) {
                    $storageObject->setMimetype($mime);
                }

                $record->setStorage($storageObject);
            }

            $hydratedCollection->add($record);
        }

        return $hydratedCollection;
    }
}

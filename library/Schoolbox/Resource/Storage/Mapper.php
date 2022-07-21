<?php

/**
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Schoolbox\Resource\Storage;

use Alaress\Database\Predicate\Builder;
use Alaress_Date_Format;
use PDO;
use PDOStatement;
use Schoolbox;
use Schoolbox\Collection;
use Schoolbox\File\Mimetype;
use Schoolbox\File\MimetypeMapper;
use Schoolbox\GenericCollection;
use Schoolbox\Mapper as BaseMapper;
use Schoolbox\Mapper\Exception as MapperException;
use Schoolbox\User;
use Schoolbox\Video\QueueMapper;

/**
 * Class Mapper
 * Storage Mapper. Maps storage table to Schoolbox storage objects
 *
 * @package Schoolbox\Resource\Storage
 * @method GenericCollection|Model[] findAll($options = [])
 * @method Model createModel($idValue = null)
 */
class Mapper extends BaseMapper implements IReceiver
{
    /**
     * Max length for filenames: anything longer causes issues on upload.
     */
    public const FILENAME_MAX_LENGTH = 250;

    /**
     * @return void
     * @throws MapperException
     */
    protected function mapping()
    {
        $this->hasTable('storage');
        $this->hasModel(Model::class);

        $this->uses('hash', self::TYPE_STRING, self::COLUMN_IDENTIFIER);
        $this->uses('filename', self::TYPE_STRING);
        $this->uses('filesize', self::TYPE_INTEGER, null, 'size');
        $this->uses('uploaded_on', self::TYPE_DATETIME, self::COLUMN_CREATED_TIMESTAMP, 'createdAt');
        $this->uses('last_accessed', self::TYPE_DATETIME, self::COLUMN_UPDATED_TIMESTAMP);
        $this->uses('public', self::TYPE_BOOLEAN);
        $this->uses('ext', self::TYPE_STRING, null, 'extension');
        $this->uses('expired_at', self::TYPE_DATETIME, null, 'expiryAt');

        // Derived field: Mime type automatically joined via the extension

        $this->joinsVia('allowedFileExtension', MimetypeMapper::class, 'fileTypeID', 'fileTypeID', 'extension', 'ext', self::TYPE_MODEL, self::COLUMN_JOIN_READONLY, 'mimetype');

        $this->registerCallback(self::CALLBACK_POSTSAVE, [$this, 'postSave']);
        $this->registerCallback(self::CALLBACK_POSTDELETE, [$this, 'deleteStorage']);
    }

    /**
     * Post-delete callback
     *
     * TODO this shouldn't be necessary: storage files should be cleaned up by
     *      another process
     *
     * @param Model $storage
     */
    public function deleteStorage(Model $storage)
    {
        $this->getStorageFilesystemProxy()->delete($storage);
    }

    /**
     * Clear callback hooks
     *
     * @deprecated
     */
    public function dePostSave()
    {
        unset($this->callback_queue[self::CALLBACK_POSTSAVE]);
        unset($this->callback_queue[self::CALLBACK_PRESAVE]);
    }

    /**
     * @param Model $storage
     *
     * @return true
     */
    protected function postSave(Model $storage)
    {
        // Ram media files into Video Queue table for transcoding
        if ($storage->getMimetype()->isVideo() || $storage->getMimetype()->isAudio()) {
            $mapper = QueueMapper::getInstance();
            $videoQ = $mapper->createModel();
            $videoQ->setStorage($storage);
            $videoQ->setStatus(QueueMapper::STATUS_PENDING);
            $videoQ->setUser($this->getSessionUser());

            $mapper->save($videoQ);
        }

        return true;
    }

    /**
     * @param string $hash
     *
     * @return Model
     * @throws MapperException
     */
    public function findByHash($hash)
    {
        /** @var Model $model */
        $model = $this->findOneByColumn('hash', $hash);

        return $model;
    }

    /**
     * Always join onto Mime
     *
     * @return string
     */
    protected function getSelectString()
    {
        $table = $this->database->getQuoter()->quoteIdentifier($this->table);

        return "
          SELECT {$this->getColumnString()},
                 {$this->getJoinColumnString('allowedFileExtension')}
          FROM $table
          LEFT JOIN {$this->getJoinSql('allowedFileExtension', '', '', true)}
        ";
    }

    /**
     * @param PDOStatement $statement
     *
     * @return Collection
     * @throws MapperException
     */
    protected function hydrateSelect(PDOStatement $statement)
    {
        $c = $this->createCollection();

        while ($r = $statement->fetch(PDO::FETCH_ASSOC)) {
            $storage = $this->hydrate($r);

            /** @var Mimetype $mimeType */
            $mimeType = $this->hydrateJoin($r, 'allowedFileExtension');

            $storage->setMimetype($mimeType);

            $c->add($storage);
        }

        return $c;
    }

    /**
     * @param Builder $predicate
     * @param array   $columnList
     * @param array   $options
     *
     * @return array
     */
    protected function buildWherePart(Builder $predicate, $columnList, $options)
    {
        // if the mime is specified, add a LIKE predicate to the query
        if (isset($options['mime'])) {
            $mime = str_replace('*', '%', $options['mime']);
            $predicate->like('allowedFileTypes.mime', $mime);
        }

        return parent::buildWherePart($predicate, $columnList, $options);
    }

    /**
     * For safety reasons don't allow the storage mapper's `delete` to be called
     * directly: instead, `actualDelete` must be called.
     *
     * This protects against inadvertent deletion of the files which back onto
     * storage objects..
     *
     * @param Model|Collection<Model> $object
     *
     * @return never-return
     * @throws MapperException
     */
    public function delete($object)
    {
        throw new MapperException("Attempt to delete Storage file denied");
    }


    /**
     * Actually delete a storage object.
     *
     * @param Model|Collection<Model> $object
     * @return bool
     * @throws MapperException if the file couldn't be deleted.
     */
    public function actualDelete($object): bool
    {
        try {
            // postDelete will throw a file exception if the file can't be deleted
            return parent::delete($object);
        } catch (MapperException $e) {
            throw new MapperException("Could not delete Storage file", 0, $e);
        }
    }


    /**
     * @inheritdoc
     *
     * @deprecated The receiver from DI (filesystem.receiver) should be used
     *             instead, and injected where necessary.
     */
    public function acceptStream(
        string $basename,
        mixed $fp,
        bool $public = false,
        ?Alaress_Date_Format $expiryAt = null,
        ?User $uploadingUser = null,
    ): Model {
        /** @var Receiver $receiver */
        $receiver = self::getDi()->get('filesystem.receiver');
        return $receiver->acceptStream(
            $basename,
            $fp,
            $public,
            $expiryAt,
            $uploadingUser,
        );
    }


    /**
     * @inheritdoc
     *
     * @deprecated The receiver from DI (filesystem.receiver) should be used
     *             instead, and injected where necessary.
     */
    public function accept(
        string $basename,
        string $tempLocation,
        bool $public = true,
        ?Alaress_Date_Format $expiryAt = null,
        ?User $uploadingUser = null,
    ): Model {
        /** @var Receiver $receiver */
        $receiver = self::getDi()->get('filesystem.receiver');
        return $receiver->accept(
            $basename,
            $tempLocation,
            $public,
            $expiryAt,
            $uploadingUser,
        );
    }

    // Getters & Setters

    /**
     * Simplified method for retrieving a proxy for the mapper's filesystem.
     *
     * This shouldn't be required long-term: the storage mapper shouldn't
     * necessarily need to know how files are stored. This is only a convenience
     * for classes which can't easily have the filesystem proxy from the DI
     * container injected.
     *
     * (Look, it's not wonderful but it IS better than having the filesystem in
     * the model!)
     *
     * @deprecated The proxy filesystem from DI (filesystem.storageProxy) should
     *             be used instead, and injected where necessary.
     *
     * @return FilesystemProxy
     */
    public function getStorageFilesystemProxy()
    {
        return self::getDi()->get('filesystem.storageProxy');
    }
}

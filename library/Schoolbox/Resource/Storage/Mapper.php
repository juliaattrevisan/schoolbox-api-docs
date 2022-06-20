<?php

/**
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Schoolbox\Resource\Storage;

use Alaress\Database\Predicate\Builder;
use Alaress_Byte_Format;
use Alaress_Date_Format;
use League\Flysystem\FileNotFoundException;
use League\Flysystem\FilesystemInterface;
use PDO;
use PDOStatement;
use Schoolbox;
use Schoolbox\Collection;
use Schoolbox\File\Exception as FileException;
use Schoolbox\File\Mimetype;
use Schoolbox\File\MimetypeMapper;
use Schoolbox\GenericCollection;
use Schoolbox\Mapper as BaseMapper;
use Schoolbox\Mapper\Exception as MapperException;
use Schoolbox\Resource\Storage\Sanitizer;
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

//        $requireSafety = true;
//
//        $user = $this->getSessionUser();
//        if ($user) {
//            $requireSafety = $user->isSuperuser() == false;
//        } elseif (PHP_SAPI == 'cli') {
//            $requireSafety = false;
//        }
//
//        if ($requireSafety) {
//            $this->registerCallback(self::CALLBACK_PRESAVE, [$this, 'safetyCheck']);
//        } else {
//            $this->registerCallback(self::CALLBACK_PRESAVE, function ($model) {
//                /** @var Model $model */
//                if (!$model->getMimetype() instanceof Mimetype) {
//                    // For super users only, we allow this
//                    $model->setMimetype(MimetypeMapper::getInstance()->getUnknownMime());
//                }
//            });
//        }

        $this->registerCallback(self::CALLBACK_POSTSAVE, [$this, 'postSave']);
        $this->registerCallback(self::CALLBACK_POSTDELETE, [$this, 'deleteStorage']);
    }

    /**
     * Pre-save callback
     *
     * @param Model $storage
     *
     * @throws FileException
     */
    public function safetyCheck(Model $storage)
    {
        $fileMime    = $storage->getMimetype();
        $errorSuffix = "Try a different file or contact your IT administrator.";

        // Assert: Mime set

        if (!$fileMime instanceof Mimetype) {
            throw new FileException(
                "This file has no detectable type. {$errorSuffix}",
                FileException::CODE_MIMETYPE_NOT_FOUND
            );
        }

        // Assert: Mime type allowed

        if (!$fileMime->isEnabled() || $fileMime->isDeleted()) {
            throw new FileException(
                "This file is not a valid file type. {$errorSuffix}",
                FileException::CODE_MIMETYPE_NOT_ALLOWED
            );
        }

        // Assert: Acceptable size

        if ($fileMime->getActualMaxFileSize() < $storage->getSize()) {
            throw new FileException(
                sprintf(
                    "This file exceeds the file size restriction of %s. %s",
                    Alaress_Byte_Format::bytesToString($fileMime->getActualMaxFileSize()),
                    $errorSuffix,
                ),
            );
        }
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
     * @param Model $previous
     *
     * @return bool
     * @throws FileException
     * @throws FileNotFoundException
     */
    protected function postSave(Model $storage, Model $previous = null)
    {
        // TODO move all of this into the new accept() method
        // Don't re-copy this file if it's already in storage, and either:
        // * config flag OFF, or:
        // * previous exists and size hasn't changed?
//        $filesystemProxy = new FilesystemProxy($this->getManager()->getStorageFilesystem(), new HashGenerator());
//        if ($previous) {
//            if (!$this->getConfigLoader()->get(Schoolbox\Config\ConfigMapper::NAME_OTFS_VERIFY_PREVIOUS_FILE)) {
//                return true;
//            }
//            if (
//                $filesystemProxy->fileExists($previous)
//                && $filesystemProxy->fileSize($storage) === $filesystemProxy->fileSize($previous)
//            ) {
//                return true;
//            }
//        }

        // Don't persist here: it was persisted in accept()
//        $saveResult = $this->getManager()->save($storage);

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

//    /**
//     * Ensures that path is always calculated on all hydrated objects.
//     * This used to be in `$this->hydrateSelect` - however, that method doesn't
//     * get called if this object is hydrated through a join from another mapper.
//     *
//     * @param array         $row
//     * @param string        $alias
//     * @param callable|null $callback
//     *
//     * @return null|Model
//     * @throws MapperException
//     */
//    public function hydrate(array $row, $alias = '', $callback = null)
//    {
//        /** @var null|Model $storage */
//        $storage = parent::hydrate($row, $alias, $callback);
//        if ($storage instanceof Model) {
//            $storage->setPath($this->getManager()->calcPath($storage));
//        }
//
//        return $storage;
//    }

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
//        // This might seem hacky, but to ensure all the safety checks pass, this data needs to be a tempfile
//        // (just like an upload)
//
//        $tempLocation = sb_tempnam('otfs');
//
//        /* Temporary location didn't get created. There might be a number of reasons why that might
//         * be the case; no write access to the specified location, disk full, etc.
//         *
//         * CLI Tools:
//         * If you ended up following a fatal from a cli tool, you should try running it with sudo
//         * Otherwise your logged-in account might not have write access to $tempLocation
//         */
//        if ('' === (string)$tempLocation) {
//            throw new FileException("Temporary location couldn't be created; check sudo, perms, disk usage");
//        }
//
//        // Dump the stream into our tempfile
//
//        file_put_contents($tempLocation, $fp);
//
//        return $this->accept($basename, $tempLocation, $public, $expiryAt, $uploadingUser);
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
//        /** @var MimetypeMapper $mtm */
//        $mtm     = $this->getJoinMapper('allowedFileExtension');
//        $storage = $this->createModel();
//
//        // Set file type metadata
//        $storage->setFilename(mb_pathinfo($basename, PATHINFO_FILENAME));
//        $storage->setExtension(mb_pathinfo($basename, PATHINFO_EXTENSION));
//
//        try {
//            $storage->setMimetype($mtm->getByFilename($basename));
//        } catch (FileException $e) {
//            // We are going to allow the mime to remain NULL, which will shortly be caught in the safetyCheck
//        }
//
//        // Run the safety check
//        $user = $uploadingUser ?? $this->getSessionUser();
//        $requireSafety = true;
//        if ($user) {
//            $requireSafety = $user->isSuperuser();
//        } elseif (PHP_SAPI === 'cli') {
//            $requireSafety = false;
//        }
//        if ($requireSafety) {
//            $this->safetyCheck($storage);
//        } elseif (!$storage->getMimetype() instanceof Mimetype) {
//            // For superusers only, we allow an unknown mimetype
//            $storage->setMimetype($mtm->getUnknownMime());
//        }
//
//        // By default files uploaded this way are public
//        $storage->setPublic($public);
//
//        // TODO: Find a way to remove the need for this truncation
//        // TODO handle all of this in the Manager class:
//        //      it knows how to move files from temp to storage
//        //      (whether local or S3) efficiently
//        $tempLocationInFilesystem = str_replace(sys_get_temp_dir(), '', $tempLocation);
//        /** @var Sanitizer\ISanitizer $sanitizer */
//        $sanitizer                = self::getDi()->get('storage.sanitizer');
//        /** @var FilesystemInterface $tempFilesystem */
//        $tempFilesystem           = self::getDi()->get('filesystem.temp');
//        $helper                   = new Sanitizer\Helper($sanitizer);
//        $sanitized                = $helper->sanitize(
//            $tempFilesystem,
//            $tempLocationInFilesystem,
//            $storage->getMime(),
//        );
//        if (isset($sanitized)) {
//            // write the new content
//            $this->getStorageFilesystemProxy()->write($storage, $sanitized);
//        } else {
//            // write the old content
//            // TODO handle this efficiently with the mount manager?
//            $this->getStorageFilesystemProxy()->writeStream(
//                $storage,
//                $tempFilesystem->readStream($tempLocationInFilesystem),
//            );
//        }
//
//        if (null !== $expiryAt) {
//            $storage->setExpiryAt($expiryAt);
//        }
//
//        $this->save($storage);
//
//        return $storage;
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
     * @deprecated The proxy filesystem from DI (filesystem.mountManager) should
     *             be used instead, and injected where necessary.
     *
     * @return FilesystemProxy
     */
    public function getStorageFilesystemProxy()
    {
        return self::getDi()->get('filesystem.storageProxy');
    }
}

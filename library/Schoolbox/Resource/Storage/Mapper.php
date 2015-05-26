<?php
/**
 * 
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2015, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace Schoolbox\Resource\Storage;

use Alaress_Byte_Format;
use PDO;
use PDOStatement;
use Schoolbox;
use Schoolbox\File;
use Schoolbox\Collection;
use Schoolbox\File\MimetypeMapper;
use Schoolbox\Video;
use Schoolbox\Video\QueueMapper;
/**
 * Class Mapper
 *
 * Storage Mapper. Maps storage table to Schoolbox storage objects
 *
 * @package Schoolbox\Resource\Storage
 *
 * @method Model hydrate()
 * @method Model createModel()
 */
class Mapper extends Schoolbox\Mapper implements Receiver
{

    /** @var Manager */
    protected $mgr;

    /**
     * @return void
     */
    public function setup()
    {
        // TODO this does not belong here, but where does it go
        $factory = new Schoolbox\Resource\FilesystemFactory();
        $this->setManager(new Manager($factory->build($this->config_loader->get('storage_dir')), new Alaress_Byte_Format()));
    }


    /**
     * @return void
     */
    protected function mapping()
    {
        $this->hasTable('storage');
        $this->hasPrototype(new Model($this->mgr->getFilesystem(Manager::FS_STORAGE)));

        $this->uses('hash', self::TYPE_STRING, self::COLUMN_IDENTIFIER);
        $this->uses('filename', self::TYPE_STRING);
        $this->uses('filesize', self::TYPE_INTEGER, null, 'size');
        $this->uses('uploaded_on', self::TYPE_DATETIME, self::COLUMN_CREATED_TIMESTAMP, 'createdAt');
        $this->uses('last_accessed', self::TYPE_DATETIME, self::COLUMN_UPDATED_TIMESTAMP);
        $this->uses('public', self::TYPE_BOOLEAN);
        $this->uses('ext', self::TYPE_STRING, null, 'extension');

        // derived field; mimetype automatically joined via the extension
        $this->joinsVia('allowedFileExtension', MimetypeMapper::class, 'fileTypeID', 'fileTypeID', 'extension', 'ext', self::TYPE_MODEL, self::COLUMN_JOIN_READONLY, 'mimetype');

        $this->registerCallback(self::CALLBACK_PRESAVE,    [ $this->mgr, 'safetyCheck' ]);
        $this->registerCallback(self::CALLBACK_POSTSAVE,   [ $this, self::CALLBACK_POSTSAVE ]);
        $this->registerCallback(self::CALLBACK_POSTDELETE, [ $this->mgr, 'delete' ]);
    }

    /**
     * Call back hook to clear postsave queue
     * @deprecated
     */
    public function dePostSave() {
        unset($this->callback_queue[self::CALLBACK_POSTSAVE]);
        unset($this->callback_queue[self::CALLBACK_PRESAVE]);
    }

    /**
     * @param Model $storage
     * @param Model $previous
     * @return bool
     */
    protected function postSave(Model $storage, Model $previous = null)
    {
        if ($previous) {
            // not the first time we've seen this file? no copying to take place
            return true;
        }

        /* Ram media files into Video Queue table for transcoding
         */
        $saveResult = $this->mgr->save($storage);

        if ($storage->getMimeType()->isVideo() || $storage->getMimetype()->isAudio()) {
            $mapper = QueueMapper::getInstance();
            $videoQ = $mapper->createModel();
            $videoQ->setHash($storage->getHash());
            $videoQ->setStatus(QueueMapper::STATUS_PENDING);

            $mapper->save($videoQ);
        }

        return $saveResult;
    }

    /**
     * @param string $hash
     * @return Model
     */
    public function findByHash($hash)
    {
        return $this->findOneByColumn('hash', $hash);
    }


    /**
     * Always join onto Mime
     *
     * @return string
     */
    protected function getSelectString()
    {
        $table = $this->database->quoteIdentifier($this->table);

        return "
          SELECT {$this->getColumnString()},
                 {$this->getJoinColumnString('allowedFileExtension')}
          FROM $table
          LEFT JOIN {$this->getJoinSql('allowedFileExtension', '', '', true)}
        ";
    }


    /**
     * @param PDOStatement $statement
     * @return Collection
     */
    protected function hydrateSelect(PDOStatement $statement)
    {
        $c = $this->createCollection();

        while ($r = $statement->fetch(PDO::FETCH_ASSOC)) {
            $storage = $this->hydrate($r);
            $storage->setPath($this->mgr->calcPath($storage));
            $storage->setMimetype($this->hydrateJoin($r, 'allowedFileExtension'));

            $c->add($storage);
        }

        return $c;
    }


    /**
     * @param array $columnList
     * @param array $options
     * @return array
     */
    protected function buildWherePart($columnList, $options)
    {
        $parts = parent::buildWherePart($columnList, $options);

        if (isset($options['mime'])) {
            $mime = str_replace('*', '%', $options['mime']);
            $parts['where'][] = sprintf("`allowedFileTypes`.`mime` LIKE %s",
                $this->database->quote($mime)
            );
        }

        return $parts;
    }


    /**
     * @param Model $object
     * @return bool
     * @throws Schoolbox\Mapper\Exception
     */
    public function delete($object)
    {
        // protect storage objects from accidental deletion by forcing a second, undocument parameter that must be TRUE
        $args = func_get_args();

        if (count($args) < 2 || $args[1] !== true) {
            throw new Schoolbox\Mapper\Exception("Attempt to delete Storage file denied");
        }

        try {
            // postDelete will throw a file exception if the file can't be deleted
            return parent::delete($object);
        } catch (File\Exception $e) {
            throw new Schoolbox\Mapper\Exception("Could not delete Storage file", 0, $e);
        }
    }


    /**
     * Accept a datastream upload
     *
     * @param string   $filename
     * @param resource $fp
     * @param bool     $public
     * @return Model
     */
    public function acceptStream($filename, $fp, $public = false)
    {
        // this might seem hacky, but to ensure all the safety checks pass, this data needs to be a tempfile (just like an upload)
        $tempLocation = tempnam(sys_get_temp_dir(), 'sb');

        // dump the stream into our tempfile
        file_put_contents($tempLocation, $fp);

        return $this->accept($filename, $tempLocation, $public);
    }


    /**
     * Accept a new file upload
     *
     * @param string $basename
     * @param string $tempLocation
     * @param bool   $public
     * @throws File\Exception
     * @return Model
     */
    public function accept($basename, $tempLocation, $public = true)
    {
        /** @var MimetypeMapper $mtm */
        $mtm = $this->getJoinMapper('allowedFileExtension');

        // clear hash so it will be recalculated
        $storage = $this->createModel()->setHash(null);

        // explicitly use temporary upload filesystem
        $storage->setFilesystem($this->mgr->getFilesystem(Manager::FS_TEMP));

        // TODO find a way to remove the need for this truncation
        $tempLocation = str_replace(sys_get_temp_dir(), '', $tempLocation);
        $storage->setPath($tempLocation);

        // set filetype metadata
        $storage->setFilename(pathinfo($basename, PATHINFO_FILENAME));
        $storage->setExtension(pathinfo($basename, PATHINFO_EXTENSION));
        try {
            $storage->setMimetype($mtm->getByFilename($basename));
        } catch (File\Exception $e) {
            // noop. we are going to allow the mime to remain NULL, which will shortly be caught in the safetyCheck
        }

        // by default, files uploaded this way are public
        $storage->setPublic($public);

        // aaaaaand save. file won't be re-saved if it already exists in storage
        $this->save($storage);

        return $storage;
    }


    /**
     * @return Manager
     */
    public function getManager()
    {
        return $this->mgr;
    }


    /**
     * @param Manager $mgr
     * @return self
     */
    public function setManager($mgr)
    {
        $this->mgr = $mgr;
        return $this;
    }


    /**
     * @param bool $bool
     * @return self
     */
    public function checkPrevious($bool)
    {
        $this->checkPrevious = $bool;
        return $this;
    }

}

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
use League\Flysystem\MountManager;
use Schoolbox\File;

/**
 * Class Manager
 *
 * Mappers are to Models as this Manager is to files.
 * In principle, Mappers DO NOT influence the filesystem in anyway - that's what this class is for.
 * This is essentially a Schoolbox abstraction around Flysystem's MountManager.
 *
 * @package Schoolbox\Resource\Storage
 */
class Manager
{

    const FS_STORAGE = 'storage';
    const FS_TEMP    = 'upload';

    /** @var Alaress_Byte_Format */
    protected $abf;

    /** @var MountManager */
    protected $mgr;


    /**
     * @param MountManager        $mgr
     * @param Alaress_Byte_Format $abf
     */
    public function __construct(MountManager $mgr, Alaress_Byte_Format $abf)
    {
        $this->mgr = $mgr;
        $this->abf = $abf;
    }


    /**
     * @param Model $storage
     * @throws File\Exception
     */
    public function safetyCheck(Model $storage)
    {
        // safety checks!
        $mime = $storage->getMimetype();
        $base = $storage->getBasename();
        $suff = "Try a different file or contact your IT administrator.";

        // assert: mime set
        if (!$mime instanceof File\Mimetype) {
            throw new File\Exception("Your file, '$base', has no detectable type. $suff", File\Exception::CODE_MIMETYPE_NOT_FOUND);
        }

        // assert: mime allowed
        if (!$mime->isEnabled() || $mime->isDeleted()) {
            throw new File\Exception("Your file, '$base', is not a valid file type. $suff", File\Exception::CODE_MIMETYPE_NOT_ALLOWED);
        }

        // assert: acceptable size
        if ($mime->getMaxFileSize() < $storage->getSize()) {
            $humanSize = $this->abf->bytesToString($mime->getMaxFileSize());
            throw new File\Exception("The size of the uploaded file, '$base', exceeds the file size restriction of $humanSize. $suff", File\Exception::CODE_FILE_TOO_LARGE);
        }

    }


    /**
     * Convert the likes of "a6a0cf26c2966cb" to "a6a0c/f26c2/966cb/a6a0cf26c2966cb" for balanced storage.
     *
     * 5 hex digits = 16^5 = 1,048,576 <= the maximum number of inodes that would exist in any one directory,
     * which is well within the acceptable limits of filesystems we use.
     *
     * @param Model $storage
     * @return string
     */
    public function calcPath(Model $storage)
    {
        return sprintf("%s%s", chunk_split($storage->getHash(), 5, '/'), $storage->getHash());
    }


    /**
     * @param Model $storage
     * @throws File\Exception
     * @return bool
     */
    public function save(Model $storage)
    {
        $tempPath = $storage->getPath();

        $storage->setFilesystem($this->mgr->getFilesystem(self::FS_STORAGE))
                ->setPath($this->calcPath($storage));

        // this should already be taken care of in the mapper, but better to be safe
        if ($storage->getPath() == $tempPath) {
            return true;
        }

        try {
            // attempt to copy the file
            if (!$this->copy($tempPath, $storage)) {
                // copy failed, let's get out of here
                throw new File\Exception("Failed to copy uploaded file, '{$storage->getBasename()}', to storage");
            }

        } catch (\League\Flysystem\FileExistsException $e) {
            // the target file already exists, and that's ok
            // should probably log the dedupe event though...
        }

        return true;
    }

    /**
     * @param string $tmpFilePath
     * @param Model  $storage
     * @return bool
     */
    protected function copy($tmpFilePath, Model $storage)
    {
        return $this->mgr->copy(self::FS_TEMP."://$tmpFilePath", self::FS_STORAGE.'://'.$storage->getPath());
    }


    /**
     * @param Model $storage
     * @return bool
     */
    public function delete(Model $storage)
    {
        return $storage->delete();
    }


    /**
     * Passthrough method
     *
     * @param string $prefix
     * @return \League\Flysystem\FilesystemInterface
     */
    public function getFilesystem($prefix)
    {
        return $this->mgr->getFilesystem($prefix);
    }

}
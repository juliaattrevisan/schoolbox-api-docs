<?php

/**
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Alaress\Image\Cache;

use League\Flysystem;
use LogicException;
use Schoolbox\File;
use Schoolbox\Mapper;
use Schoolbox\Resource;
use Schoolbox\Resource\Storage;

/**
 * Hardcoded to use the local file system, regardless of system's configured storage implementation
 *
 * TODO kill this completely
 */
class LocalFS
{
    /**
     * @param string $dir
     * @param string $filename
     * @return Storage\Model
     * @throws LogicException
     * @throws File\Exception
     * @throws Mapper\Exception
     */
    public static function getStorageModel(string $dir, string $filename): Storage\Model
    {
        $fs = new Flysystem\Filesystem(new Flysystem\Adapter\Local($dir));

        // setup an object to work with so it can get into cache
        $file = Resource\Storage\Mapper::getInstance()->createModel();
        $file->setFilesystem($fs)
             ->setPath($filename)
             ->setMimetype(File\MimetypeMapper::getInstance()->getByFilename($filename))
             ->setHash(null)
             ->getHash();
        return $file;
    }
}

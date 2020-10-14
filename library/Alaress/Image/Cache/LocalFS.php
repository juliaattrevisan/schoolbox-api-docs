<?php
/**
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Alaress\Image\Cache;

use League\Flysystem as Fly;
use LogicException;
use Schoolbox\File;
use Schoolbox\File\MimetypeMapper;
use Schoolbox\Mapper;
use Schoolbox\Resource;

/**
 * Hardcoded to use the local file system, regardless of system's configured storage implementation
 */
class LocalFS extends Storage
{
    /**
     * @param string $dir
     * @param string $filename
     * @param string $key
     * @throws File\Exception
     * @throws LogicException
     * @throws Mapper\Exception
     */
    public function __construct(string $dir, string $filename, string $key)
    {
        $fs = new Fly\Filesystem(new Fly\Adapter\Local($dir));

        // setup an object to work with so it can get into cache
        $file = Resource\Storage\Mapper::getInstance()->createModel();
        $file->setFilesystem($fs)
             ->setPath($filename)
             ->setMimetype(MimetypeMapper::getInstance()->getByFilename($filename))
             ->setHash(null)
             ->getHash();

        parent::__construct($file, $key);
    }
}

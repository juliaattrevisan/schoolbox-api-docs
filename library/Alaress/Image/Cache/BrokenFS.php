<?php
/**
 * 
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2015, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace Alaress\Image\Cache;

use League\Flysystem as Fly;
use Schoolbox\File\MimetypeMapper;
use Schoolbox\Resource;

/**
 * Class BrokenFS
 *
 * @deprecated anything that implements this won't be expected to work in Cloud
 * @package Alaress\Image\Cache
 */
class BrokenFS extends Storage
{

    /**
     * @param string $dir
     * @param string $filename
     * @param string $key
     * @throws \Schoolbox\File\Exception
     */
    public function __construct($dir, $filename, $key)
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
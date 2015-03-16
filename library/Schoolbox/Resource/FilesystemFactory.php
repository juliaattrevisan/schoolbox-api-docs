<?php
/**
 * 
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2015, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace Schoolbox\Resource;


use League\Flysystem\Adapter\Local;
use League\Flysystem\Filesystem;
use League\Flysystem\MountManager;
use Schoolbox\Resource\Storage\Manager;

/**
 * Class FilesystemFactory
 *
 * @package Schoolbox\Resource
 *          TODO expand this to be configurable
 */
class FilesystemFactory
{

    /**
     * @param string $storageDir
     * @return MountManager
     */
    public function build($storageDir)
    {
        return $this->buildSpecific($storageDir, sys_get_temp_dir());
    }


    /**
     * @param string $storageDir
     * @param string $tempDir
     * @return MountManager
     */
    public function buildSpecific($storageDir, $tempDir)
    {
        return new MountManager([
            Manager::FS_TEMP    => new Filesystem(new Local($tempDir)),
            Manager::FS_STORAGE => new Filesystem(new Local($storageDir))
        ]);
    }

}

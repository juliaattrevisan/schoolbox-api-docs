<?php
/**
 * 
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2015, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace Schoolbox\Resource;

use Aws\S3\S3Client;
use League\Flysystem\AwsS3v3\AwsS3Adapter;
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
        $config = new \Schoolbox\Config\Loader();

        $client = S3Client::factory([
            'credentials' => [
            'key'    => $config->get('otfs_s3_cred_key'),
            'secret' => $config->get('otfs_s3_cred_secret'),
        ],
            'region' => $config->get('otfs_s3_region'),
            'version' => $config->get('otfs_s3_version'),
        ]);

        return new MountManager([
            Manager::FS_TEMP    => new Filesystem(new Local($tempDir)),
            Manager::FS_STORAGE => new Filesystem(new Local($storageDir)),
            Manager::FS_STORAGE => new Filesystem(new AwsS3Adapter($client, $config->get('otfs_s3_bucket'), $config->get('otfs_s3_prefix') . INSTANCE_ID))
        ]);

    }

}

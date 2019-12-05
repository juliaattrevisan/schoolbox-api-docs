<?php
/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
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
        // TODO SA-8354: inject this
        $config = new \Schoolbox\Config\Loader();

        // Enable S3 only if all of the variables have been set in the config

        $otfs_s3_cred_key = trim($config->get('otfs_s3_cred_key'));
        $otfs_s3_cred_secret = trim($config->get('otfs_s3_cred_secret'));
        $otfs_s3_region = trim($config->get('otfs_s3_region'));
        $otfs_s3_version = trim($config->get('otfs_s3_version'));
        $otfs_s3_bucket = trim($config->get('otfs_s3_bucket'));
        $otfs_s3_prefix = trim($config->get('otfs_s3_prefix'));

        if (!empty($otfs_s3_cred_key) &&
            !empty($otfs_s3_cred_secret) &&
            !empty($otfs_s3_region) &&
            !empty($otfs_s3_version) &&
            !empty($otfs_s3_bucket) &&
            !empty($otfs_s3_prefix))
        {
            // Use AWS S3 for storage

            $client = S3Client::factory([
                'credentials' => [
                'key'    => $otfs_s3_cred_key,
                'secret' => $otfs_s3_cred_secret,
            ],
                'region' => $otfs_s3_region,
                'version' => $otfs_s3_version,
            ]);

            $fs_storage = new Filesystem(new AwsS3Adapter($client, $otfs_s3_bucket, $otfs_s3_prefix . INSTANCE_ID));
        }
        else
        {
            // Use local storage

            $fs_storage = new Filesystem(new Local($storageDir));
        }

        return new MountManager([
            Manager::FS_TEMP    => new Filesystem(new Local($tempDir)),
            Manager::FS_STORAGE => $fs_storage
        ]);
    }
}

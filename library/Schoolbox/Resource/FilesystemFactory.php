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
use LogicException;
use Schoolbox\Config\ConfigMapper;
use Schoolbox\Resource\Storage\Manager;
use UnexpectedValueException;
use function trim;

/**
 * Class FilesystemFactory
 *
 * @package Schoolbox\Resource
 *          TODO expand this to be configurable
 */
class FilesystemFactory
{
    public const FILE_SYSTEM_ADAPTER_LOCAL = 'local';
    public const FILE_SYSTEM_ADAPTER_AWS_S3 = 'awsS3';

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
     * @throws UnexpectedValueException If configured filesystem adapter is not recognised
     * @throws LogicException           If root path to local filesystem is not readable
     */
    public function buildSpecific($storageDir, $tempDir)
    {
        // TODO SA-8354: inject this
        $config = new \Schoolbox\Config\Loader();

        $adapter = $config->get(ConfigMapper::NAME_OTFS_ADAPTER);
        switch ($adapter) {
            case self::FILE_SYSTEM_ADAPTER_LOCAL:
                // Use local storage
                $fs_storage = new Filesystem(new Local($storageDir));
                break;
            case self::FILE_SYSTEM_ADAPTER_AWS_S3:
                // Use AWS S3 for storage
                $otfs_s3_cred_key    = trim($config->get('otfs_s3_cred_key') ?? "");
                $otfs_s3_cred_secret = trim($config->get('otfs_s3_cred_secret') ?? "");
                $otfs_s3_region      = trim($config->get('otfs_s3_region') ?? "");
                $otfs_s3_version     = trim($config->get('otfs_s3_version') ?? "");
                $otfs_s3_bucket      = trim($config->get('otfs_s3_bucket') ?? "");

                $client = S3Client::factory([
                    'credentials' => [
                        'key'    => $otfs_s3_cred_key,
                        'secret' => $otfs_s3_cred_secret,
                    ],
                    'region'      => $otfs_s3_region,
                    'version'     => $otfs_s3_version,
                ]);

                $fs_storage = new Filesystem(new AwsS3Adapter($client, $otfs_s3_bucket));
                break;
            default:
                throw new UnexpectedValueException("Unrecognised filesystem adapter: '$adapter'");
        }

        return new MountManager([
            Manager::FS_TEMP    => new Filesystem(new Local($tempDir)),
            Manager::FS_STORAGE => $fs_storage
        ]);
    }
}

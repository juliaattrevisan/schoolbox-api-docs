<?php

/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Schoolbox\Resource;

use Aws\S3\S3Client;
use GuzzleHttp;
use InvalidArgumentException;
use League\Flysystem\AwsS3V3\AwsS3V3Adapter as AwsS3Adapter;
use League\Flysystem\Local\LocalFilesystemAdapter as Local;
use League\Flysystem\Filesystem;
use League\Flysystem\MountManager;
use LogicException;
use Schoolbox\Config\ConfigMapper;
use Schoolbox\IConfigLoader;
use Schoolbox\Resource\Storage\S3\Adapter;
use UnexpectedValueException;

use function trim;

use const PHP_SAPI;

/**
 * Class FilesystemFactory
 *
 * @package Schoolbox\Resource
 *          TODO expand this to be configurable
 */
class FilesystemFactory
{
    public const FILE_SYSTEM_STORAGE = 'storage';
    public const FILE_SYSTEM_TEMP    = 'upload';

    public const FILE_SYSTEM_ADAPTER_LOCAL = 'local';
    public const FILE_SYSTEM_ADAPTER_AWS_S3 = 'awsS3';

    // All storage files in S3 will have this prefix in their key
    public const PREFIX_STORAGE = 'storage/';

    /**
     * When opening a connection to S3, fail if connection takes longer than this number of seconds during a web request.
     *
     * Most likely this will be due to transient DNS failure. We want to detect this and kill the request quickly
     * so the server doesn't OOM.
     * @var int
     */
    private const S3_CONNECT_TIMEOUT_WEB = 5;


    /**
     * @param string $storageDir
     * @return MountManager
     */
    public function build($storageDir)
    {
        return $this->buildSpecific($storageDir, sys_get_temp_dir());
    }

    /**
     * Build the storage filesystem, as defined by config.
     *
     * @param IConfigLoader $config
     * @return Filesystem
     */
    public function buildStorage(IConfigLoader $config): Filesystem
    {
        $adapter        = $config->get(ConfigMapper::NAME_OTFS_ADAPTER);
        $enableReUpload = $config->get(ConfigMapper::NAME_OTFS_VERIFY_PREVIOUS_FILE);
        $fsConfig       = $enableReUpload ? ['disable_asserts' => true] : null;
        return match ($adapter) {
            self::FILE_SYSTEM_ADAPTER_LOCAL  => new Filesystem(new Local($config->get('storage_dir')), $fsConfig),
            self::FILE_SYSTEM_ADAPTER_AWS_S3 => $this->buildAwsS3FileSystem($fsConfig, self::PREFIX_STORAGE),
            default                          => throw new UnexpectedValueException(
                "Unrecognised filesystem adapter: '$adapter'",
            ),
        };
    }

    /**
     * Return a temp filesystem.
     *
     * @return Filesystem
     */
    public function buildTemp(): Filesystem
    {
        return new Filesystem(new Local(sys_get_temp_dir()));
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
        $enableReUpload = $config->get(ConfigMapper::NAME_OTFS_VERIFY_PREVIOUS_FILE);
        $fsConfig = $enableReUpload ? ['disable_asserts' => true] : null;
        switch ($adapter) {
            case self::FILE_SYSTEM_ADAPTER_LOCAL:
                // Use local storage
                $fs_storage = new Filesystem(new Local($storageDir), $fsConfig);
                break;
            case self::FILE_SYSTEM_ADAPTER_AWS_S3:
                // Use AWS S3 for storage
                $fs_storage = $this->buildAwsS3FileSystem($fsConfig, self::PREFIX_STORAGE);
                break;
            default:
                throw new UnexpectedValueException("Unrecognised filesystem adapter: '$adapter'");
        }

        return new MountManager([
            self::FILE_SYSTEM_TEMP    => new Filesystem(new Local($tempDir)),
            self::FILE_SYSTEM_STORAGE => $fs_storage
        ]);
    }

    /**
     * Returns S3 connection timeout in seconds.
     *
     * For CLI, set to '0' (ie don't timeout).
     * For WEB, set to some sane value
     *
     * @return int
     */
    public static function getConnectTimeout(): int
    {
        return PHP_SAPI === 'cli' ? 0 : self::S3_CONNECT_TIMEOUT_WEB;
    }


    /**
     * Returns an AWS S3 file system, optionally with root set to $prefix
     *
     * @param array|null $fsConfig
     * @param string     $prefix
     * @return Filesystem
     * @throws InvalidArgumentException
     */
    public function buildAwsS3FileSystem(array $fsConfig = null, string $prefix = ''): Filesystem
    {
        $config              = new \Schoolbox\Config\Loader();
        $otfs_s3_cred_key    = trim($config->get('otfs_s3_cred_key') ?? "");
        $otfs_s3_cred_secret = trim($config->get('otfs_s3_cred_secret') ?? "");
        $otfs_s3_region      = trim($config->get('otfs_s3_region') ?? "");
        $otfs_s3_version     = trim($config->get('otfs_s3_version') ?? "");
        $otfs_s3_bucket      = trim($config->get('otfs_s3_bucket') ?? "");

        $client = new S3Client([
            'credentials' => [
                'key'    => $otfs_s3_cred_key,
                'secret' => $otfs_s3_cred_secret,
            ],
            'region'      => $otfs_s3_region,
            'version'     => $otfs_s3_version,
            'http'    => [
                GuzzleHttp\RequestOptions::CONNECT_TIMEOUT => self::getConnectTimeout(),
            ],
        ]);

        // Register the S3 stream wrapper, so that the S3 filesystem adapter
        // may use it
        $client->registerStreamWrapper();

        return new Filesystem(
            new Adapter(new AwsS3Adapter($client, $otfs_s3_bucket, $prefix)),
            $fsConfig
        );
    }
}

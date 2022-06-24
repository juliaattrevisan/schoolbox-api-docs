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
use League\Flysystem\AwsS3V3\AwsS3V3Adapter as AwsS3Adapter;
use League\Flysystem\Config;
use League\Flysystem\Local\LocalFilesystemAdapter as Local;
use League\Flysystem\Filesystem;
use League\Flysystem\UnixVisibility\PortableVisibilityConverter;
use League\Flysystem\Visibility;
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
     * Build the storage filesystem, as defined by config.
     *
     * @param IConfigLoader $config
     * @return Filesystem
     */
    public function buildStorage(IConfigLoader $config): Filesystem
    {
        $adapter = $config->get(ConfigMapper::NAME_OTFS_ADAPTER);
        return match ($adapter) {
            self::FILE_SYSTEM_ADAPTER_LOCAL  => new Filesystem(
                new Local(
                    $config->get('storage_dir'),
                    // Allow group-writable public files and folders
                    PortableVisibilityConverter::fromArray(
                        [
                            'file' => [Visibility::PUBLIC => 0664,],
                            'dir'  => [Visibility::PUBLIC => 0775,],
                        ],
                        Visibility::PUBLIC,
                    ),
                ),
                [
                    Config::OPTION_VISIBILITY           => Visibility::PUBLIC,
                    Config::OPTION_DIRECTORY_VISIBILITY => Visibility::PUBLIC,
                ],
            ),
            self::FILE_SYSTEM_ADAPTER_AWS_S3 => $this->buildAwsS3FileSystem($config, self::PREFIX_STORAGE),
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
     * @param IConfigLoader $config
     * @param string        $prefix
     * @return Filesystem
     */
    public function buildAwsS3FileSystem(IConfigLoader $config, string $prefix = ''): Filesystem
    {
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
            new Adapter(
                new AwsS3Adapter($client, $otfs_s3_bucket, $prefix),
                $otfs_s3_bucket,
            ),
        );
    }
}

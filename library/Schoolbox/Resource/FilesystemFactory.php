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
use Schoolbox\Resource\Storage\S3\ResumableStreamWrapper;
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
            self::FILE_SYSTEM_ADAPTER_LOCAL  => $this->buildLocalFileSystem($config),
            self::FILE_SYSTEM_ADAPTER_AWS_S3 => $this->buildAwsS3FileSystem($config, self::PREFIX_STORAGE),
            default                          => throw new UnexpectedValueException(
                "Unrecognised filesystem adapter: '$adapter'",
            ),
        };
    }

    /**
     * Build the fallback storage filesystem.
     *
     * This will only be set if using the S3 adapter, and an and an alternate
     * host is set. In that case, it will be an S3 adapter pointed to the
     * non-alternate host.
     *
     * If not using S3, or using S3 with no alternate host: this will be null.
     *
     * @param IConfigLoader $config
     * @return Filesystem|null
     */
    public function buildFallbackStorage(IConfigLoader $config): ?Filesystem
    {
        $adapter = $config->get(ConfigMapper::NAME_OTFS_ADAPTER);
        return match ($adapter) {
            self::FILE_SYSTEM_ADAPTER_AWS_S3 => $this->buildAwsS3FileSystem($config, self::PREFIX_STORAGE),
            default                          => null,
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
     * Build a local file system.
     *
     * @param IConfigLoader $config
     * @return Filesystem
     */
    public function buildLocalFileSystem(IConfigLoader $config): Filesystem
    {
        $visibility = self::buildDefaultVisibility(self::FILE_SYSTEM_ADAPTER_LOCAL);
        return new Filesystem(
            new Local(
                $config->get('storage_dir'),
                // Allow group-writable public files and folders
                PortableVisibilityConverter::fromArray(
                    [
                        'file' => [Visibility::PUBLIC => 0664,],
                        'dir'  => [Visibility::PUBLIC => 0775,],
                    ],
                    $visibility,
                ),
            ),
            [
                Config::OPTION_VISIBILITY           => $visibility,
                Config::OPTION_DIRECTORY_VISIBILITY => $visibility,
            ],
        );
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
        $otfs_s3_cred_key     = trim($config->get('otfs_s3_cred_key') ?? "");
        $otfs_s3_cred_secret  = trim($config->get('otfs_s3_cred_secret') ?? "");
        $otfs_s3_region       = trim($config->get('otfs_s3_region') ?? "");
        $otfs_s3_version      = trim($config->get('otfs_s3_version') ?? "");
        $otfs_s3_bucket       = trim($config->get('otfs_s3_bucket') ?? "");
        $otfs_s3_alt_host     = trim($config->get('otfs_s3_alt_host') ?? "");
        $s3AltAccessKey       = trim($config->get('otfs_s3_alt_access_key') ?? "");
        $s3AltSecretAccessKey = trim($config->get('otfs_s3_alt_secret_key') ?? "");
        $otfs_s3_alt_bucket   = trim($config->get('otfs_s3_alt_bucket') ?? "");
        $s3AltRegion         = trim($config->get('otfs_s3_alt_region') ?? "");

        $cfg = [
            'credentials' => [
                'key'    => $otfs_s3_cred_key,
                'secret' => $otfs_s3_cred_secret,
            ],
            'region'      => $otfs_s3_region,
            'version'     => $otfs_s3_version,
            'http'    => [
                GuzzleHttp\RequestOptions::CONNECT_TIMEOUT => self::getConnectTimeout(),
            ],
        ];
        if (!empty($otfs_s3_alt_host)) {
            $cfg['endpoint'] = $otfs_s3_alt_host;
            $cfg['credentials']['key'] = $s3AltAccessKey;
            $cfg['credentials']['secret'] = $s3AltSecretAccessKey;
            $cfg['region'] = $s3AltRegion;
        }

        // Add chaos middleware for testing if enabled
        if (getenv('SCHOOLBOX_S3_CHAOS_TESTING') === 'true') {
            $chaosChance = (int)(getenv('SCHOOLBOX_S3_CHAOS_CHANCE') ?: 50);
            $chaosMinBytes = (int)(getenv('SCHOOLBOX_S3_CHAOS_MIN_BYTES') ?: 1024);
            $cfg['http_handler'] = new \Schoolbox\Resource\Storage\S3\ChaosGuzzleMiddleware($chaosChance, $chaosMinBytes);
        }

        $client = new S3Client($cfg);

        // Register the S3 stream wrapper, so that the S3 filesystem adapter
        // may use it
        ResumableStreamWrapper::register($client);

        $visibility = self::buildDefaultVisibility(self::FILE_SYSTEM_ADAPTER_AWS_S3);
        $bucket = !empty($otfs_s3_alt_host) && !empty($otfs_s3_alt_bucket) ? $otfs_s3_alt_bucket : $otfs_s3_bucket;
        return new Filesystem(
            new Adapter(
                new AwsS3Adapter($client, $bucket, $prefix),
                $bucket,
            ),
            [
                Config::OPTION_VISIBILITY           => $visibility,
                Config::OPTION_DIRECTORY_VISIBILITY => $visibility,
            ],
        );
    }

    /**
     * Helper for determining a filesystem type's default visibility.
     *
     * @param string $adapter
     * @return string
     */
    public static function buildDefaultVisibility(string $adapter): string
    {
        return match ($adapter) {
            self::FILE_SYSTEM_ADAPTER_LOCAL  => Visibility::PUBLIC,
            self::FILE_SYSTEM_ADAPTER_AWS_S3 => Visibility::PRIVATE,
            default                          => throw new UnexpectedValueException(
                "Unrecognised filesystem adapter: '$adapter'",
            ),
        };
    }
}

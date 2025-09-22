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
            self::FILE_SYSTEM_ADAPTER_AWS_S3 => $this->buildAwsS3FileSystem($config, self::PREFIX_STORAGE, true),
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
     * @param bool          $ignoreAltHost If true: any alt host settings will be ignored, and the file system will
     *                                     always point to the base host.
     * @return Filesystem
     */
    public function buildAwsS3FileSystem(IConfigLoader $config, string $prefix = '', bool $ignoreAltHost = false): Filesystem
    {
        $s3AccessKey    = trim($config->get(ConfigMapper::NAME_OTFS_S3_CRED_KEY) ?? "");
        $s3SecretKey    = trim($config->get(ConfigMapper::NAME_OTFS_S3_CRED_SECRET) ?? "");
        $s3Region       = trim($config->get(ConfigMapper::NAME_OTFS_S3_REGION) ?? "");
        $s3Version      = trim($config->get(ConfigMapper::NAME_OTFS_S3_VERSION) ?? "");
        $s3Bucket       = trim($config->get(ConfigMapper::NAME_OTFS_S3_BUCKET) ?? "");
        $s3AltHost      = trim($config->get(ConfigMapper::NAME_OTFS_S3_ALT_HOST) ?? "");
        $s3AltAccessKey = trim($config->get(ConfigMapper::NAME_OTFS_S3_ALT_ACCESS_KEY) ?? "");
        $s3AltSecretKey = trim($config->get(ConfigMapper::NAME_OTFS_S3_ALT_SECRET_KEY) ?? "");
        $s3AltBucket    = trim($config->get(ConfigMapper::NAME_OTFS_S3_ALT_BUCKET) ?? "");
        $s3AltRegion    = trim($config->get(ConfigMapper::NAME_OTFS_S3_ALT_REGION) ?? "");

        $cfg = [
            'credentials' => [
                'key'    => $s3AccessKey,
                'secret' => $s3SecretKey,
            ],
            'region'      => $s3Region,
            'version'     => $s3Version,
            'http'        => [
                GuzzleHttp\RequestOptions::CONNECT_TIMEOUT => self::getConnectTimeout(),
            ],
        ];
        $bucket = $s3Bucket;
        if (!$ignoreAltHost && !empty($s3AltHost)) {
            $cfg['endpoint']              = $s3AltHost;
            $cfg['credentials']['key']    = $s3AltAccessKey;
            $cfg['credentials']['secret'] = $s3AltSecretKey;
            $cfg['region']                = $s3AltRegion;
            if (!empty($s3AltBucket)) {
                $bucket = $s3AltBucket;
            }
        }

        // Add chaos middleware for testing if enabled
        if (getenv('SCHOOLBOX_S3_CHAOS_TESTING') === 'true') {
            $chaosChance         = (int)(getenv('SCHOOLBOX_S3_CHAOS_CHANCE') ?: 50);
            $chaosMinBytes       = (int)(getenv('SCHOOLBOX_S3_CHAOS_MIN_BYTES') ?: 1024);
            $cfg['http_handler'] = new Storage\S3\ChaosGuzzleMiddleware($chaosChance, $chaosMinBytes);
        }

        $client = new S3Client($cfg);

        // Register the S3 stream wrapper, so that the S3 filesystem adapter
        // may use it.
        // Also need to provide a protocol name: since the alt and base hosts
        // could be different, different protocols are required.
        $protocol = $ignoreAltHost ? 's3fallback' : 's3';
        ResumableStreamWrapper::register($client, $protocol);

        $visibility = self::buildDefaultVisibility(self::FILE_SYSTEM_ADAPTER_AWS_S3);
        return new Filesystem(
            new Adapter(
                new AwsS3Adapter($client, $bucket, $prefix),
                $bucket,
                $protocol,
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

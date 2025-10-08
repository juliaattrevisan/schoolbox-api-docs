<?php

/**
 * @copyright Copyright 2021, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

namespace Schoolbox\Resource\Storage\S3;

use Aws\S3\S3Client;
use GuzzleHttp;
use Schoolbox\IConfigLoader;
use Schoolbox\Resource\FilesystemFactory;
use Schoolbox\Config\ConfigMapper;

/**
 * Flysystem is unable to chunk a file from storage which makes streaming of
 * videos from S3 impossible. This makes video playback from S3 a non-starter as
 * you have to wait for the entire video to download to the server before it can
 * start being streamed to the user.
 *
 * This class is a kludge to connect to S3 directly and download the file using
 * their native async methods with a progress callback that streams it out to
 * the user as we receive it.
 *
 * @deprecated replaced with a S3 filesystem adapter which always returns az`
 *             seekable stream
 */
class Stream
{
    /**
     * Open a file located on AWS S3 storage as a stream
     *
     * @param string $path
     * @param IConfigLoader $config
     * @return bool|resource
     */
    public static function open($path, IConfigLoader $config)
    {
        // Retrieve AWS S3 configuration from database
        $region  = $config->get(ConfigMapper::NAME_OTFS_S3_REGION);
        $version = $config->get(ConfigMapper::NAME_OTFS_S3_VERSION);
        $key     = $config->get(ConfigMapper::NAME_OTFS_S3_CRED_KEY);
        $secret  = $config->get(ConfigMapper::NAME_OTFS_S3_CRED_SECRET);
        $bucket  = $config->get(ConfigMapper::NAME_OTFS_S3_BUCKET);
        $altHost = $config->get(ConfigMapper::NAME_OTFS_S3_ALT_HOST);
        $s3AltAccessKey = trim($config->get(ConfigMapper::NAME_OTFS_S3_ALT_ACCESS_KEY) ?? "");
        $s3AltSecretAccessKey = trim($config->get(ConfigMapper::NAME_OTFS_S3_ALT_SECRET_KEY) ?? "");
        $altBucket = trim($config->get(ConfigMapper::NAME_OTFS_S3_ALT_BUCKET) ?? "");
        $altRegion = trim($config->get(ConfigMapper::NAME_OTFS_S3_ALT_REGION) ?? "");

        // Connect to Amazon S3
        $cfg = [
            'version'     => $version,
            'region'      => $region,
            'credentials' => [
                'key'    => $key,
                'secret' => $secret,
            ],
            'http'    => [
                GuzzleHttp\RequestOptions::CONNECT_TIMEOUT => FilesystemFactory::getConnectTimeout(),
            ],
        ];

        if (!empty($altHost)) {
            $cfg['endpoint'] = $altHost;
            $cfg['credentials']['key'] = $s3AltAccessKey;
            $cfg['credentials']['secret'] = $s3AltSecretAccessKey;
            $cfg['region'] = $altRegion;
        }
        $client = new S3Client($cfg);

        ResumableStreamWrapper::register($client);

        $context = stream_context_create([
            's3' => ['seekable' => true],
        ]);

        // Return seekable stream
        $prefix = FilesystemFactory::PREFIX_STORAGE;
        $finalBucket = !empty($altHost) && !empty($altBucket) ? $altBucket : $bucket;
        return fopen("s3://{$finalBucket}/{$prefix}{$path}", "r", false, $context);
    }
}

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

        $region  = $config->get('otfs_s3_region');
        $version = $config->get('otfs_s3_version');
        $key     = $config->get('otfs_s3_cred_key');
        $secret  = $config->get('otfs_s3_cred_secret');
        $bucket  = $config->get('otfs_s3_bucket');

        // Connect to Amazon S3

        $client = new S3Client([
            'version'     => $version,
            'region'      => $region,
            'credentials' => [
                'key'    => $key,
                'secret' => $secret,
            ],
            'http'    => [
                GuzzleHttp\RequestOptions::CONNECT_TIMEOUT => FilesystemFactory::getConnectTimeout(),
            ],
        ]);

        $client->registerStreamWrapper();

        $context = stream_context_create([
            's3' => ['seekable' => true],
        ]);

        // Return seekable stream
        $prefix = FilesystemFactory::PREFIX_STORAGE;
        return fopen("s3://{$bucket}/{$prefix}{$path}", "r", false, $context);
    }
}

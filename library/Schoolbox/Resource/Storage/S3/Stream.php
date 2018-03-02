<?php
/**
 * Flysystem is unable to chunk a file from storage which makes streaming of videos from S3 impossible. This makes
 * video playback from S3 a non-starter as you have to wait for the entire video to download to the server before it
 * can start being streamed to the user.
 * This class is a kludge to connect to S3 directly and download the file using their native async methods with a
 * progress callback that streams it out to the user as we receive it.
 *
 * @author      Damian Sloane <damian.sloane@alaress.com.au>
 * @copyright   Copyright 2017, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */

namespace Schoolbox\Resource\Storage\S3;

use Aws\S3\S3Client;

class Stream
{
    /**
     * Open a file located on AWS S3 storage as a stream
     *
     * @param string $path
     * @param \Config $config
     * @return bool|resource
     */
    public static function open($path, $config)
    {
        // Retrieve AWS S3 configuration from database

        $region  = $config->get('otfs_s3_region');
        $version = $config->get('otfs_s3_version');
        $key     = $config->get('otfs_s3_cred_key');
        $secret  = $config->get('otfs_s3_cred_secret');
        $bucket  = $config->get('otfs_s3_bucket');
        $prefix  = $config->get('otfs_s3_prefix');

        // Create the base path for the S3 storage

        $basePath = "{$bucket}/{$prefix}" . INSTANCE_ID;

        // Connect to Amazon S3

        $client = new S3Client([
            'version'     => $version,
            'region'      => $region,
            'credentials' => [
                'key'    => $key,
                'secret' => $secret,
            ],
        ]);

        $client->registerStreamWrapper();

        $context = stream_context_create([
            's3' => ['seekable' => true],
        ]);

        // Return seekable stream

        return fopen("s3://{$basePath}/{$path}", "r", false, $context);
    }
}

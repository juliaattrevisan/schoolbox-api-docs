<?php
/**
 * Flysystem is unable to chunk a file from storage which makes streaming of videos from S3 impossible. This makes
 * video playback from S3 a non-starter as you have to wait for the entire video to download to the server before it
 * can start being streamed to the user.
 * This class is a kludge to connect to S3 directly and download the file using their native async methods with a
 * progress callback that streams it out to the user as we receive it.
 *
 * @author      Damian Sloane <damian.sloane@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Schoolbox\Resource\Storage\S3;

use Aws\S3\S3Client;
use Schoolbox\IConfigLoader;
use Schoolbox\Resource\Storage\Model as ResourceStorageModel;

class Stream
{
    /**
     * @var IConfigLoader
     */
    private $config;

    /**
     * @var S3Client
     */
    private $s3Client;

    /**
     * @var ResourceStorageModel
     */
    private $resource;

    /**
     * @var string|string
     */
    private $signedUrlExpiration;

    /**
     * @var string|string
     */
    private $attachmentType;

    /**
     * @var string|string
     */
    private $contentType;

    /**
     * Stream constructor.
     *
     * @param IConfigLoader        $config
     * @param ResourceStorageModel $resource
     * @param string               $signedUrlExpiration
     * @param string               $attachmentType
     * @param string               $contentType
     */
    public function __construct(IConfigLoader $config, ResourceStorageModel $resource,
                                string $signedUrlExpiration, string $attachmentType, string $contentType)
    {
        $this->config = $config;
        $this->resource = $resource;

        $this->s3Client = new S3Client([
            'credentials' => [
                'key'    => trim($config->get('otfs_s3_cred_key') ?? ''),
                'secret' => trim($config->get('otfs_s3_cred_secret') ?? ''),
            ],
            'region'      => trim($config->get('otfs_s3_region') ?? ''),
            'version'     => trim($config->get('otfs_s3_version') ?? ''),
        ]);

        $this->signedUrlExpiration = $signedUrlExpiration;
        $this->attachmentType = $attachmentType;
        $this->contentType = $contentType;
    }

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
        ]);

        $client->registerStreamWrapper();

        $context = stream_context_create([
            's3' => ['seekable' => true],
        ]);

        // Return seekable stream

        return fopen("s3://{$bucket}/{$path}", "r", false, $context);
    }

    /**
     * Overriding Response Header Values
     * https://docs.aws.amazon.com/cli/latest/reference/s3api/get-object.html
     * https://docs.aws.amazon.com/aws-sdk-php/v3/api/api-s3-2006-03-01.html#getobject
     *
     * @return mixed+
     */
    private function getObject()
    {
        return $this->s3Client->getCommand('GetObject', [
            'Bucket'                     => trim($this->config->get('otfs_s3_bucket') ?? ''),
            'Key'                        => $this->resource->getPath(),
            # By default, the browser should never cache the file. Only cache if user explicitly requests it.
            'ResponseCacheControl'       => ($_GET['cache'] ?? '0') ? 'private' : 'no-store',
            'ResponseContentDisposition' => "$this->attachmentType filename=\"{$this->resource->getBasename()}\"",
            'ResponseContentType'        => $this->contentType,
            'ResponseExpires'            => $this->cacheExpiration,
        ]);
    }

    /**
     * @return \Psr\Http\Message\UriInterface
     */
    public function getPresignedUrl()
    {
        return $this->s3Client->createPresignedRequest($this->getObject(), $this->signedUrlExpiration)->getUri();
    }
}

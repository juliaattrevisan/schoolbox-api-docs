<?php
/**
 * @copyright Copyright 2020, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */
declare(strict_types=1);

namespace Schoolbox\Resource\Storage\S3;

use Aws\CommandInterface;
use Aws\S3\S3Client;
use Schoolbox\IConfigLoader;

class PreSignedUrl
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
     * @var string|string
     */
    private $expires;

    /**
     * @var string|string
     */
    private $attachmentType;

    /**
     * @var string|string
     */
    private $contentType;

    /**
     * @var string
     */
    private $resourcePath;

    /**
     * @var string
     */
    private $resourceBaseName;

    /**
     * @var string
     */
    private $cacheControl;


    /**
     * PreSignedUrl constructor.
     *
     * @param IConfigLoader $config
     * @param string        $expires
     * @param string        $attachmentType
     * @param string        $contentType
     * @param string        $resourcePath
     * @param string        $resourceBaseName
     * @param string        $cacheControl
     */
    public function __construct(IConfigLoader $config,
                                string $expires,
                                string $attachmentType,
                                string $contentType,
                                string $resourcePath,
                                string $resourceBaseName,
                                string $cacheControl
    ) {
        $this->config               = $config;
        $this->expires              = $expires;
        $this->attachmentType       = $attachmentType;
        $this->contentType          = $contentType;
        $this->resourcePath         = $resourcePath;
        $this->resourceBaseName     = $resourceBaseName;
        $this->cacheControl         = $cacheControl;

        $this->s3Client = new S3Client([
            'credentials' => [
                'key'    => trim($config->get('otfs_s3_cred_key') ?? ''),
                'secret' => trim($config->get('otfs_s3_cred_secret') ?? ''),
            ],
            'region'      => trim($config->get('otfs_s3_region') ?? ''),
            'version'     => trim($config->get('otfs_s3_version') ?? ''),
        ]);
    }

    /**
     * Overriding Response Header Values
     * https://docs.aws.amazon.com/cli/latest/reference/s3api/get-object.html
     * https://docs.aws.amazon.com/aws-sdk-php/v3/api/api-s3-2006-03-01.html#getobject
     *
     * @return CommandInterface
     */
    private function getObject(): CommandInterface
    {
        return $this->s3Client->getCommand('GetObject', [
            'Bucket'                     => trim($this->config->get('otfs_s3_bucket') ?? ''),
            'Key'                        => $this->resourcePath,
            # By default, the browser should never cache the file. Only cache if user explicitly requests it.
            'ResponseCacheControl'       => $this->cacheControl,
            'ResponseContentDisposition' => "$this->attachmentType filename=\"{$this->resourceBaseName}\"",
            'ResponseContentType'        => $this->contentType,
            'ResponseExpires'            => $this->expires,
        ]);
    }

    /**
     * Generate object's pre-signed URL
     *
     * @return \Psr\Http\Message\UriInterface
     */
    public function generate(): \Psr\Http\Message\UriInterface
    {
        return $this->s3Client->createPresignedRequest($this->getObject(), $this->expires)->getUri();
    }

    /**
     * Download the S3 object based from pre-signed URL
     *
     * @param string $preSignedUrl
     */
    public function download(string $preSignedUrl): void
    {
        // Instruct browser to cache redirect to presigned URL.
        // Browser will request a new presigned URL when old one expires.
        header('HTTP/1.1 301 Moved Permanently');
        header('Cache-Control: ' . $this->cacheControl);
        header('Expires: ' . $this->expires);
        header('Location: ' . $preSignedUrl, true, 301);
        header_remove('pragma');

        exit;
    }
}

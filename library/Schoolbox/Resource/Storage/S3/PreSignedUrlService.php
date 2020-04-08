<?php
/**
 * @copyright Copyright 2020, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */
declare(strict_types=1);

namespace Schoolbox\Resource\Storage\S3;

use Alaress_Date_Format as AlaressDateFormat;
use Aws\CommandInterface;
use Aws\S3\S3Client;
use Schoolbox\IConfigLoader;

class PreSignedUrlService
{
    /**
     * @var S3Client
     */
    private $s3Client;

    /**
     * @var string
     */
    private $bucket;

    /**
     * PreSignedUrlService constructor.
     *
     * @param IConfigLoader $config
     */
    public function __construct(IConfigLoader $config)
    {
        $this->s3Client = new S3Client([
            'credentials' => [
                'key'    => trim($config->get('otfs_s3_cred_key') ?? ''),
                'secret' => trim($config->get('otfs_s3_cred_secret') ?? ''),
            ],
            'region'      => trim($config->get('otfs_s3_region') ?? ''),
            'version'     => trim($config->get('otfs_s3_version') ?? ''),
        ]);
        $this->bucket   = trim($config->get('otfs_s3_bucket') ?? '');
    }

    /**
     * @param AlaressDateFormat        $expireAt
     * @param string                   $attachmentType
     * @param string                   $contentType
     * @param string                   $resourcePath
     * @param string                   $resourceBaseName
     * @param string                   $cacheControl
     *
     * @return PreSignedUrlDataObject
     */
    public function generate(AlaressDateFormat $expireAt,
                             string $attachmentType,
                             string $contentType,
                             string $resourcePath,
                             string $resourceBaseName,
                             string $cacheControl): PreSignedUrlDataObject
    {
        $expires       = $expireAt->__toString(); // whatever formatting most appropriate
        $commandObject = $this->getObject($resourcePath, $cacheControl, $attachmentType, $resourceBaseName, $contentType, $expires);
        $url           = $this->s3Client->createPresignedRequest($commandObject, $expires)->getUri()->__toString();

        return new PreSignedUrlDataObject($url, $cacheControl, $expires);
    }

    /**
     * @param PreSignedUrlDataObject $dataObject
     */
    public function download(PreSignedUrlDataObject $dataObject): void
    {
        // Instruct browser to cache redirect to presigned URL.
        // Browser will request a new presigned URL when old one expires.
        header('HTTP/1.1 301 Moved Permanently');
        header('Location: ' . $dataObject->getUrl(), true, 301);
        header('Cache-Control: ' . $dataObject->getCacheControl());
        header('Expires: ' . $dataObject->getExpires());
        header_remove('pragma');
        exit;
    }

    /**
     * @param string $resourcePath
     * @param string $cacheControl
     * @param string $attachmentType
     * @param string $resourceBaseName
     * @param string $contentType
     * @param string $expires
     *
     * @return CommandInterface
     */
    private function getObject(string $resourcePath,
                               string $cacheControl,
                               string $attachmentType,
                               string $resourceBaseName,
                               string $contentType,
                               string $expires): CommandInterface
    {
        return $this->s3Client->getCommand('GetObject', [
            'Bucket'                     => $this->bucket,
            'Key'                        => $resourcePath,
            'ResponseCacheControl'       => $cacheControl,
            'ResponseContentDisposition' => "$attachmentType filename=\"{$resourceBaseName}\"",
            'ResponseContentType'        => $contentType,
            'ResponseExpires'            => $expires,
        ]);
    }
}

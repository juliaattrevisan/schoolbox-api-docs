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
use Schoolbox\Resource\FilesystemFactory;
use Schoolbox\Resource\Storage\Model;
use Schoolbox\Resource\Storage\Transform\Mapper as TransformMapper;

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
     * @param Model             $storage
     * @param AlaressDateFormat $expireAt
     * @param string            $size
     * @param string            $cacheControl
     * @param string            $attachmentType
     *
     * @return PreSignedUrlDataObject
     */
    public function generate(
        Model $storage,
        AlaressDateFormat $expireAt,
        string $size = null,
        string $cacheControl = 'private',
        string $attachmentType = ''
    ): PreSignedUrlDataObject
    {
        $expires        = $expireAt->__toString();
        $storagePath    = $storage->getPath();
        $itemPath       = ($size && strpos($storagePath, '-') === false) ? $storagePath . '-' . $size : $storagePath;

        $commandObject = $this->getObject(
            $itemPath,
            $cacheControl,
            $attachmentType,
            $storage->getBasename(),
            $storage->getMime() ?: 'application/octet-stream',
            $expires
        );


        $url = $this->s3Client->createPresignedRequest($commandObject, $expires)->getUri()->__toString();

        return new PreSignedUrlDataObject($storage->getHash(), $url, $cacheControl, $expires, $storage->getPath(), $size);
    }

    /**
     * @param PreSignedUrlDataObject $dataObject
     *
     * @return bool
     */
    private function fileExists(PreSignedUrlDataObject $dataObject): bool
    {
        $transformationMapper = new TransformMapper();

        $size = $dataObject->getSize();

        if (is_null($size) || (($size && $transformationMapper->checkIfRecordExists($dataObject->getHash(), $size)))) {
            return true;
        }

        return false;
    }

    /**
     * @param PreSignedUrlDataObject $dataObject
     */
    public function download(PreSignedUrlDataObject $dataObject): void
    {
        if ($this->fileExists($dataObject)) {
            // Instruct browser to cache redirect to presigned URL.
            // Browser will request a new presigned URL when old one expires.
            header('HTTP/1.1 301 Moved Permanently');
            header('Location: ' . $dataObject->getUrl(), true, 301);
            header('Cache-Control: ' . $dataObject->getCacheControl());
            header('Expires: ' . $dataObject->getExpires());
            header_remove('pragma');
            exit;
        }
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
        $basename = utf8_encode($resourceBaseName);

        return $this->s3Client->getCommand('GetObject', [
            'Bucket'                     => $this->bucket,
            'Key'                        => FilesystemFactory::PREFIX_STORAGE . $resourcePath,
            'ResponseCacheControl'       => $cacheControl,
            'ResponseContentDisposition' => "$attachmentType filename=\"{$basename}\"",
            'ResponseContentType'        => $contentType,
            'ResponseExpires'            => $expires,
        ]);
    }
}

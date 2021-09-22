<?php

/**
 * @copyright Copyright 2020, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Resource\Storage\S3;

use Alaress_Date_Format as AlaressDateFormat;
use Aws\S3\S3Client;
use GuzzleHttp;
use Schoolbox\IConfigLoader;
use Schoolbox\Resource\FilesystemFactory;
use Schoolbox\Resource\Storage;
use Schoolbox\Resource\Storage\Model;
use Schoolbox\Resource\Storage\Transform\Mapper as TransformMapper;
use Schoolbox\Video\QueueMapper as VideoQueueMapper;
use Schoolbox\Video\ResourceVideo;

class PreSignedUrlService
{
    # Supported ContentDisposition response header dispositions
    public const DISPOSITION_INLINE = 'inline';            // Display inline
    public const DISPOSITION_ATTACHMENT = 'attachment';    // Prompt browser to download

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
            'http'    => [
                GuzzleHttp\RequestOptions::CONNECT_TIMEOUT => FilesystemFactory::getConnectTimeout(),
            ],
        ]);
        $this->bucket   = trim($config->get('otfs_s3_bucket') ?? '');
    }

    /**
     * @param Model             $storage
     * @param AlaressDateFormat $expireAt
     * @param string            $path
     * @param string            $cacheControl
     * @param string            $disposition
     * @param string|null       $mimeTypeOverride If non-null, override the storage object's mime type
     *
     * @return PreSignedUrlDataObject
     */
    public function generate(
        Model $storage,
        AlaressDateFormat $expireAt,
        string $path,
        string $cacheControl = 'private',
        string $disposition = self::DISPOSITION_INLINE,
        string $mimeTypeOverride = null,
        string $fileNameOverride = null
    ): PreSignedUrlDataObject {
        $expires  = $expireAt->__toString();
        $fileName = utf8_encode($fileNameOverride ?? $storage->getBasename());

        $commandObject = $this->s3Client->getCommand('GetObject', [
            'Bucket'                     => $this->bucket,
            'Key'                        => FilesystemFactory::PREFIX_STORAGE . $path,
            'ResponseCacheControl'       => $cacheControl,
            'ResponseContentDisposition' => "$disposition; filename=\"{$fileName}\"",
            'ResponseContentType'        => $mimeTypeOverride ?? $storage->getMime() ?: 'application/octet-stream',
            'ResponseExpires'            => $expires,
        ]);

        $url = $this->s3Client->createPresignedRequest($commandObject, $expires)->getUri()->__toString();

        return new PreSignedUrlDataObject($storage->getHash(), $url, $cacheControl, $expires, $path);
    }

    /**
     * Returns whether the data object's file exists in the database.
     *
     * If so, we assume it exists in the FileSystem.
     * It is much faster to check the database than the filesystem, especially for AWS S3.
     *
     * @param PreSignedUrlDataObject $dataObject
     *
     * @return bool
     */
    private function fileExists(PreSignedUrlDataObject $dataObject): bool
    {
        $itemPath   = $dataObject->getItemPath();
        $hash       = $dataObject->getHash();

        $cacheKey = (strpos($itemPath, '-') !== false) ? substr($itemPath, strpos($itemPath, '-') + 1) : null;

        if ($cacheKey === ResourceVideo::TRANSCODE) {
            return $this->fileExistsInVideoQueue($hash);
        }

        return $cacheKey
            ? TransformMapper::getInstance()->checkIfRecordExists($hash, $cacheKey)
            : Storage\Mapper::getInstance()->countByColumn('hash', $hash) > 0;
    }

    /**
     * Check whether the transcoded audio/video exists in video_queue table by checking the hash and
     * must have a status of completed.
     *
     * @param string $hash
     *
     * @return bool
     */
    private function fileExistsInVideoQueue(string $hash): bool
    {
        return VideoQueueMapper::getInstance()->countByColumnList([
                'hash'      => $hash,
                'status'    => VideoQueueMapper::STATUS_COMPLETED,
            ]) > 0;
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
}

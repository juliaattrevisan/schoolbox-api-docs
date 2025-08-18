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
        $s3AccessKey          = trim($config->get('otfs_s3_cred_key') ?? '');
        $s3SecretKey          = trim($config->get('otfs_s3_cred_secret') ?? '');
        $s3Region             = trim($config->get('otfs_s3_region') ?? '');
        $s3Version            = trim($config->get('otfs_s3_version') ?? '');
        $s3Bucket             = trim($config->get('otfs_s3_bucket') ?? '');
        $s3AltHost            = trim($config->get('otfs_s3_alt_host') ?? '');
        $s3AltAccessKey       = trim($config->get('otfs_s3_alt_access_key') ?? '');
        $s3AltSecretAccessKey = trim($config->get('otfs_s3_alt_secret_key') ?? '');
        $s3AltBucket          = trim($config->get('otfs_s3_alt_bucket') ?? '');
        $s3AltRegion          = trim($config->get('otfs_s3_alt_region') ?? '');

        $cfg = [
            'credentials' => [
                'key'    => $s3AccessKey,
                'secret' => $s3SecretKey,
            ],
            'region'      => $s3Region,
            'version'     => $s3Version,
            'http'    => [
                GuzzleHttp\RequestOptions::CONNECT_TIMEOUT => FilesystemFactory::getConnectTimeout(),
            ],
        ];

        if (!empty($s3AltHost)) {
            $cfg['endpoint'] = $s3AltHost;
            $cfg['credentials']['key'] = $s3AltAccessKey;
            $cfg['credentials']['secret'] = $s3AltSecretAccessKey;
            $cfg['region'] = $s3AltRegion;
        }
        $this->s3Client = new S3Client($cfg);
        $this->bucket = !empty($s3AltHost) && !empty($s3AltBucket) ? $s3AltBucket : $s3Bucket;
    }

    /**
     * @param Model             $storage
     * @param AlaressDateFormat $expireAt
     * @param string            $path
     * @param array             $cacheControlItems
     * @param string            $disposition
     * @param string|null       $mimeTypeOverride If non-null, override the storage object's mime type
     * @param string|null       $fileNameOverride
     * @return PreSignedUrlDataObject
     */
    public function generate(
        Model $storage,
        AlaressDateFormat $expireAt,
        string $path,
        array $cacheControlItems = ['private', 'must-revalidate'],
        string $disposition = self::DISPOSITION_INLINE,
        ?string $mimeTypeOverride = null,
        ?string $fileNameOverride = null
    ): PreSignedUrlDataObject {
        $duration = $expireAt->getUnixTimeStamp() - time();
        if ($duration < 0) {
            throw new InvalidPresignedUrlDurationExecption("Presigned URL duration must be greater than 0");
        }

        if ($duration > 604800) { // 60 * 60 * 24 * 7 = 604800 = 7 days
            throw new InvalidPresignedUrlDurationExecption("Presigned URL duration must be less than 7 days");
        }
        $expires  = $expireAt->__toString();
        $fileName = mb_convert_encoding($fileNameOverride ?? $storage->getBasename(), 'UTF-8', 'ISO-8859-1');
        $cacheControl = implode(',', $cacheControlItems);

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
            header('HTTP/1.1 302 Moved Temporarily');
            header('Location: ' . $dataObject->getUrl(), true, 302);
            header('Cache-Control: ' . $dataObject->getCacheControl());
            header('Expires: ' . $dataObject->getExpires());
            header_remove('pragma');
            exit;
        }
    }
}

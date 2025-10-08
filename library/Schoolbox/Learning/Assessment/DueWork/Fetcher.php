<?php

/**
 * @copyright   Copyright 2023, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Learning\Assessment\DueWork;

use Alaress\Exception\UnreachableException;
use League\Flysystem\FilesystemException;
use League\Flysystem\UnableToReadFile;
use Schoolbox\Config\ConfigMapper;
use Schoolbox\IConfigLoader;
use Schoolbox\Resource\FilesystemFactory;
use Schoolbox\Resource\Storage\FilesystemProxy;
use Schoolbox\Resource\Storage\Model;
use Schoolbox\Resource\Storage\S3\InvalidPresignedUrlDurationExecption;
use Schoolbox\Resource\Storage\S3\PreSignedUrlService;

/**
 * Download a file whose name is the given $basename
 */
class Fetcher
{
    /**
     * Constructor.
     *
     * @param IConfigLoader   $config
     * @param FilesystemProxy $filesystemProxy
     */
    public function __construct(private IConfigLoader $config, private FilesystemProxy $filesystemProxy)
    {
    }


    /**
     * Download a file whose name is the given $basename and does all the S3 stuff for you.
     * This is the guts of the old fetch.php but it means you can enclose it among route perms
     * before serving the file rather than access oldschool controllers directly and bypassing
     * any auth checks
     *
     * @param string $basename
     * @param Model  $resource
     * @param bool   $useCache if true, allow the response from S3 presigned to
     *                         be cached.
     * @param bool   $forceDownload  set to true if you want to force download directly, rather than
     *                               display inline in your browser
     * @return void
     * @throws FilesystemException
     * @throws UnableToReadFile
     * @throws UnreachableException
     */
    public function fetch(
        string $basename,
        \Schoolbox\Resource\Storage\Model $resource,
        bool $useCache,
        bool $forceDownload
    ): void {
        $disposition = $forceDownload ? PreSignedUrlService::DISPOSITION_ATTACHMENT : PreSignedUrlService::DISPOSITION_INLINE;
        $contentType = ($resource->getMime()) ?: 'application/octet-stream';
        $cacheControl = $useCache ? ['private', 'must-revalidate'] : ['no-store'];
        $bypassPresignedUrls = $resource->getMimetype()->getMime() === 'text/html' && $disposition === PreSignedUrlService::DISPOSITION_INLINE;

        if (
            !$bypassPresignedUrls
            && $this->config->get(ConfigMapper::NAME_OTFS_ADAPTER) === FilesystemFactory::FILE_SYSTEM_ADAPTER_AWS_S3
            && $this->config->get(ConfigMapper::NAME_S3_PRESIGNED_URL_ENABLED)
        ) {
            $preSignedUrlService = new PreSignedUrlService($this->config);

            try {
                $preSignedUrlDataObject = $preSignedUrlService->generate(
                    $resource,
                    \Alaress_Date_Format::now()->setTimezone('UTC')->addDays(1),
                    FilesystemProxy::getPathFromHash($resource->getHash()),
                    $cacheControl,
                    $disposition,
                    null,
                    $basename
                );
            } catch (InvalidPresignedUrlDurationExecption $e) {
                throw new UnreachableException($e);
            }

            $preSignedUrlService->download($preSignedUrlDataObject);
        }

        //send headers
        header("Content-Type: $contentType");
        header("Cache-control: private");
        header("Content-Ranges: none");
        header("Content-Disposition: $disposition; filename=\"$basename\"");
        header("Content-Length: " . $this->filesystemProxy->fileSize($resource));
        fpassthru($this->filesystemProxy->readStream($resource));
    }
}

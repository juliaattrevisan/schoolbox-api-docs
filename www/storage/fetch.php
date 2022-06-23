<?php

/**
 * Returns objects from the storage engine as plain files
 *
 * @author      James Leckie <james.leckie@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

use Alaress\Exception\UnreachableException;
use Schoolbox\Application\BasicRequest\Kernel;
use Schoolbox\Config\ConfigMapper;
use Schoolbox\Error;
use Schoolbox\IConfigLoader;
use Schoolbox\Resource\FilesystemFactory;
use Schoolbox\Resource\Storage\FilesystemProxy;
use Schoolbox\Resource\Storage\S3\InvalidPresignedUrlDurationExecption;
use Schoolbox\Resource\Storage\S3\PreSignedUrlService;

require_once __DIR__ . '/../../conf/core/require.php';
$kernel = new Kernel();

try {
    if (isset($_GET['hash']) && $_GET['hash']) {

        /** @var SitePage $page */
        $page = $kernel->get('sitePage');
        $page->initSession();

        $resource = Schoolbox\Resource\Storage\Mapper::getInstance()->findByHash($_GET['hash']);
        $disposition = (!$resource->getMimetype()->getInline() || ($_GET['save'] ?? false))
            ? PreSignedUrlService::DISPOSITION_ATTACHMENT
            : PreSignedUrlService::DISPOSITION_INLINE;
        $contentType = ($resource->getMime()) ?: 'application/octet-stream';
        $basename = $resource->getBasename();
        $cacheControl = ($_GET['cache'] ?? '0') ? ['private', 'must-revalidate'] : ['no-store'];
        $bypassPresignedUrls = $resource->getMimetype()->getMime() === 'text/html' && $disposition === PreSignedUrlService::DISPOSITION_INLINE;

        /* Optionally override filename
         *
         * fetch.php can't distinguish between identical files that were uploaded
         * with different names; we also can't infer what those different names
         * might have been or where they are located e.g. task_file - just tell me
         * what the filename was supposed to be, and it shall be so
         */
        if (isset($_GET['name'])) {
            $basename = urldecode($_GET['name']);
        }

        /** @var IConfigLoader $config */
        $config = $kernel->get('config');
        if (
            !$bypassPresignedUrls
            && $config->get(ConfigMapper::NAME_OTFS_ADAPTER) === FilesystemFactory::FILE_SYSTEM_ADAPTER_AWS_S3
        ) {
            if ($config->get(ConfigMapper::NAME_S3_PRESIGNED_URL_ENABLED)) {
                $preSignedUrlService = new PreSignedUrlService($config);
                try {
                    $preSignedUrlDataObject = $preSignedUrlService->generate(
                        $resource,
                        Alaress_Date_Format::now()->setTimezone('UTC')->addDays(1),
                        FilesystemProxy::getPathFromHash($resource->getHash()),
                        $cacheControl,
                        $disposition
                    );
                } catch (InvalidPresignedUrlDurationExecption $e) {
                    throw new UnreachableException($e);
                }

                $preSignedUrlService->download($preSignedUrlDataObject);
            }
        }

        //send headers
        /** @var FilesystemProxy $filesystemProxy */
        $filesystemProxy = $kernel->get('filesystem.storageProxy');
        header("Content-Type: $contentType");
        header("Cache-control: private");
        header("Content-Ranges: none");
        header("Content-Disposition: $disposition; filename=\"$basename\"");
        header("Content-Length: " . $resource->getSize());
        fpassthru($filesystemProxy->readStream($resource));
        exit;
    }
} catch (Exception $ex) {
}
//
// File not found error
error(Error\Mapper::ERROR_FILE_NOT_FOUND);

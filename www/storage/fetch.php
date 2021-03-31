<?php
/**
 * Returns objects from the storage engine as plain files
 *
 * @author      James Leckie <james.leckie@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

use League\Flysystem;
use Schoolbox\Application\BasicRequest\Kernel;
use Schoolbox\Config\ConfigMapper;
use Schoolbox\Error;
use Schoolbox\IConfigLoader;
use Schoolbox\Resource\Storage\S3\Adapter as S3Adapter;
use Schoolbox\Resource\Storage\S3\PreSignedUrlService;

require_once __DIR__ . '/../../conf/core/require.php';
$kernel = new Kernel();

try {
    if (isset($_GET['hash']) && $_GET['hash']) {
        $page = new SitePage;

        $resource = Schoolbox\Resource\Storage\Mapper::getInstance()->findByHash($_GET['hash']);
        $attachmentType = (!$resource->getMimetype()->getInline() || $_GET['save']) ? 'attachment;' : '';
        $contentType = ($resource->getMime()) ?: 'application/octet-stream';
        $basename = $resource->getBasename();
        $fileSystem = $resource->getFilesystem();
        $cacheControl = ($_GET['cache'] ?? '0') ? 'private' : 'no-store';
        $bypassPresignedUrls = $resource->getMimetype()->getMime() === 'text/html' && !$attachmentType;

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

        if (
            !$bypassPresignedUrls
            && $fileSystem instanceof Flysystem\Filesystem
            && $fileSystem->getAdapter() instanceof S3Adapter
        ) {
            /** @var IConfigLoader $config */
            $config = $kernel->get('config');

            if ($config->get(ConfigMapper::NAME_S3_PRESIGNED_URL_ENABLED)) {
                $preSignedUrlService = new PreSignedUrlService($config);

                $preSignedUrlDataObject = $preSignedUrlService->generate(
                    $resource,
                    Alaress_Date_Format::now()->addDays(1),
                    $resource->getPath(),
                    $cacheControl,
                    $attachmentType
                );

                $preSignedUrlService->download($preSignedUrlDataObject);
            }
        }

        //send headers
        header("Content-Type: $contentType");
        header("Cache-control: private");
        header("Content-Ranges: none");
        header("Content-Disposition: $attachmentType filename=\"$basename\"");
        header("Content-Length: ".$resource->getSize());
        fpassthru($resource->readStream());
        exit;
    }
}
catch (Exception $ex) {}
//
// File not found error
error(Error\Mapper::ERROR_FILE_NOT_FOUND);

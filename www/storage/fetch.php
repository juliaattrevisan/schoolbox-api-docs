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
use Schoolbox\Error;
use Schoolbox\IConfigLoader;
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

        if ($fileSystem instanceof Flysystem\Filesystem && $fileSystem->getAdapter() instanceof Flysystem\AwsS3v3\AwsS3Adapter) {
            /** @var IConfigLoader $config */
            $config = $kernel->get('config');

            if ($config->get('otfs_s3_presigned_urls_enabled')) {
                $preSignedUrlService = new PreSignedUrlService($config);

                $preSignedUrlDataObject = $preSignedUrlService->generate(
                    Alaress_Date_Format::now()->addDays(1),
                    $attachmentType,
                    $contentType,
                    $resource->getPath(),
                    $basename,
                    $cacheControl
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

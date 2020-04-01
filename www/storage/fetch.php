<?php
/**
 * Returns objects from the storage engine as plain files
 *
 * @author      James Leckie <james.leckie@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

use Aws\CommandInterface;
use League\Flysystem;
use Schoolbox\Error;
use Schoolbox\IConfigLoader;
use Schoolbox\Resource\Storage\S3\Stream;

require_once __DIR__ . '/../../conf/core/require.php';
$kernel = new \Schoolbox\Application\BasicRequest\Kernel();

try {
    if (isset($_GET['hash']) && $_GET['hash']) {
        $page = new SitePage;

        $cacheControl = 'private';
        $resource = Schoolbox\Resource\Storage\Mapper::getInstance()->findByHash($_GET['hash']);
        $attach = (!$resource->getMimetype()->getInline() || $_GET['save']) ? 'attachment;' : '';
        $contentType = ($resource->getMime()) ?: 'application/octet-stream';

        $fileSystem = $resource->getFilesystem();
        if($fileSystem instanceof Flysystem\Filesystem && $fileSystem->getAdapter() instanceof Flysystem\AwsS3v3\AwsS3Adapter) {
            /** @var IConfigLoader $config */
            $config = $kernel->get('config');
            if($config->get('otfs_s3_presigned_urls_enabled')){

                $signedUrlExpiration = Alaress_Date_Format::now()->addDays(1)->__toString();

                $presignedUrl = (new Stream(
                    $config,
                    $signedUrlExpiration,
                    $attach,
                    $contentType,
                    $resource->getPath(),
                    $resource->getBasename()
                ))->getPresignedUrl();

                // Instruct browser to cache redirect to presigned URL.
                // Browser will request a new presigned URL when old one expires.
                header('HTTP/1.1 301 Moved Permanently');
                header('Cache-Control: private');
                header("Expires: $signedUrlExpiration");
                header("Location: $presignedUrl", true, 301);
                header_remove('pragma');
                exit;
            }
        }

        //send headers
        header("Content-Type: $contentType");

        $basename = $resource->getBasename();

        header("Cache-control: $cacheControl");
        header("Content-Ranges: none");
        header("Content-Disposition: $attach filename=\"$basename\"");
        header("Content-Length: ".$resource->getSize());
        fpassthru($resource->readStream());
        exit;
    }
}
catch (Exception $ex) {}
//
// File not found error
error(Error\Mapper::ERROR_FILE_NOT_FOUND);

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
                $s3Client = new Aws\S3\S3Client([
                    'credentials' => [
                        'key'    => trim($config->get('otfs_s3_cred_key') ?? ''),
                        'secret' => trim($config->get('otfs_s3_cred_secret') ?? ''),
                    ],
                    'region'      => trim($config->get('otfs_s3_region') ?? ''),
                    'version'     => trim($config->get('otfs_s3_version') ?? ''),
                ]);

                /*
                 * Overriding Response Header Values
                 * https://docs.aws.amazon.com/cli/latest/reference/s3api/get-object.html
                 * https://docs.aws.amazon.com/aws-sdk-php/v3/api/api-s3-2006-03-01.html#getobject
                 */
                $expires = Alaress_Date_Format::now()->addDays(1)->__toString();
                 /** @var CommandInterface $cmd */
                $cmd = $s3Client->getCommand('GetObject', [
                    'Bucket'                     => trim($config->get('otfs_s3_bucket') ?? ''),
                    'Key'                        => $resource->getPath(),
                    # By default, the browser should never cache the file. Only cache if user explicitly requests it.
                    'ResponseCacheControl'       => ($_GET['cache'] ?? '0') ? "private" : "no-store",
                    'ResponseContentDisposition' => "$attach filename=\"{$resource->getBasename()}\"",
                    'ResponseContentType'        => $contentType,
                    'ResponseExpires'            => $expires,
                ]);

                // Get the actual presigned-url
                $presignedUrl = $s3Client->createPresignedRequest($cmd, $expires)->getUri();

                // Instruct browser to cache redirect to presigned URL.
                // Browser will request a new presigned URL when old one expires.
                header('HTTP/1.1 301 Moved Permanently');
                header('Cache-Control: private');
                header("Expires: $expires");
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

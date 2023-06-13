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
    /* Since you can have identical file contents made by different authors
     * they can circumstantially be mistaken for one another
     *
     * It's called fileId purely because it matches the Module's params
     */
    $submissionFileId = $_GET['fileId'] ?? null;

    /* Generically exit early if no submission id provided
     */
    if (null === $submissionFileId) {
        error(Error\Mapper::ERROR_FILE_NOT_FOUND);
    }

    /* If you supplied both, check that you are one of the following:
     *   - a staff
     *   - the student themselves
     *
     * If you're either of those, you can see the contents; for now we need to use SitePage
     * for its session implementation I suppose
     *
     * Otherwise get out; you're not allowed
     */
    /** @var SitePage $page */
    $page = $kernel->get('sitePage');
    $page->initSession();

    /* Are you a staff?
     */
    $isStaff = $_SESSION['user']->getRole()->isStaff();

    /* Are you the file's owner?
     */
    $submissionFileMapper = \Schoolbox\Assessment\Component\SubmissionBox\Submission\SingleFile\Mapper::getInstance();

    try {
        $submissionFile = $submissionFileMapper->findById($submissionFileId);
    } catch (\Schoolbox\Mapper\Exception $e) {
        error(Error\Mapper::ERROR_FILE_NOT_FOUND);
    }

    $isOwner = $_SESSION['user']->getId() === $submissionFile->getSubmission()->getOwner()->getId();

    /* Generically exit early if you're neither staff nor owner
     */
    if (!$isStaff && !$isOwner) {
        error(Error\Mapper::ERROR_FILE_NOT_FOUND);
    }

    /* Otherwise you supplied all the pieces
     * and you pass the perms checks
     *
     * We didn't code to interfaces, so fetch this in the data type required
     * for minimal change
     */
    $resource = Schoolbox\Resource\Storage\Mapper::getInstance()->findByHash($submissionFile->getStorage()->getHash());

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
    $basename = $submissionFile->getBasename();

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
    header("Content-Length: " . $filesystemProxy->fileSize($resource));
    fpassthru($filesystemProxy->readStream($resource));
    exit;
} catch (Exception $ex) {
}
//
// File not found error
error(Error\Mapper::ERROR_FILE_NOT_FOUND);

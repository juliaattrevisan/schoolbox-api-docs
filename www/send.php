<?php

/************************************************************
 *                                                           *
 *         Copyright (c) 2002-2003 Lektec Consulting         *
 *                                                           *
 ************************************************************/

//this script opens files for download and send them to the user with correct filename
//future enhancements could include storing the content type to help browser file recognition
//rather than just octet stream

//includes
use Alaress\Exception\UnreachableException;
use Schoolbox\Application\BasicRequest\Kernel;
use Schoolbox\Config\ConfigMapper;
use Schoolbox\Error\Mapper;
use Schoolbox\IConfigLoader;
use Schoolbox\Permission\Access\Access;
use Schoolbox\Permission\Access\UserAccess;
use Schoolbox\Resource\FilesystemFactory;
use Schoolbox\Resource\LogMapper;
use Schoolbox\Resource\Storage\FilesystemProxy;
use Schoolbox\Resource\Storage\Model as Storage;
use Schoolbox\Resource\Storage\S3\InvalidPresignedUrlDurationExecption;
use Schoolbox\Resource\Storage\S3\PreSignedUrlService;

require_once __DIR__ . '/../conf/core/require.php';
$kernel = new Kernel();

/** @var SitePage $page */
$page = $kernel->get('sitePage');
$page->initSession();

if (is_numeric($_GET['id'])) {
    $access      = Access::getInstance();
    $fileHandler = false;
    $fileMapper  = Schoolbox\Resource\File\Mapper::getInstance();

    if (isset($_GET['folder'])) {
        try {
            $currentPermission = $access->getFolderAccess($_SESSION['user'], $_GET['id']);
        } catch (Schoolbox\Mapper\Exception $e) {
            error(Mapper::ERROR_FILE_NOT_FOUND);
        }
        if ($currentPermission < UserAccess::ACCESS_READ) {
            error(Mapper::ERROR_PERM_DOWNLOAD_FILE);
        }

        $config = [
            'sort'      => 'date',
            'sortdir'   => 'DESC',
            'size'      => 1,
            'getHidden' => ($currentPermission > UserAccess::ACCESS_READ),
        ];

        $file = $fileMapper->findForFolders([$_GET['id']], $config)->first();
    } else {
        try {
            $file           = $fileMapper->findById($_GET['id']);
            $requiredAccess = $file->isHidden() ? UserAccess::ACCESS_WRITE : UserAccess::ACCESS_READ;

            if ($access->getFolderAccess($_SESSION['user'], $file->getFolder()->getId()) < $requiredAccess) {
                error(Mapper::ERROR_PERM_DOWNLOAD_FILE);
            }

            LogMapper::getInstance()->logRead($file, $_SESSION['user']);
        } catch (Schoolbox\Mapper\Exception $e) {
            error(Mapper::ERROR_FILE_NOT_FOUND);
        }
    }

    $disposition = (!$file->getMimetype()->getInline() || !empty($_GET['save']))
        ? PreSignedUrlService::DISPOSITION_ATTACHMENT
        : PreSignedUrlService::DISPOSITION_INLINE;
    $contentType    = ($file->getMime()) ?: 'application/octet-stream';
    $basename       = $file->getBasename();
    /** @var Storage $storage */
    $storage      = $file->getStorage();
    $cacheControl = ($_GET['cache'] ?? '0') ? ['private', 'must-revalidate'] : ['no-store'];

    /** @var IConfigLoader $config */
    $config = $kernel->get('config');
    if ($config->get(ConfigMapper::NAME_OTFS_ADAPTER) === FilesystemFactory::FILE_SYSTEM_ADAPTER_AWS_S3) {
        if ($config->get(ConfigMapper::NAME_S3_PRESIGNED_URL_ENABLED)) {
            $preSignedUrlService = new PreSignedUrlService($config);
            try {
                $preSignedUrlDataObject = $preSignedUrlService->generate(
                    $storage,
                    Alaress_Date_Format::now()->setTimezone('UTC')->addDays(1),
                    FilesystemProxy::getPathFromHash($storage->getHash()),
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
    header("Pragma: ");
    header("Cache-control: private");
    header("Content-Ranges: none");
    header("Content-Disposition: $disposition; filename=\"$basename\"");
    header("Content-Length: {$filesystemProxy->fileSize($file)}");
    fpassthru($filesystemProxy->readStream($file));
    exit;
}

error(Mapper::ERROR_FILE_NOT_FOUND);

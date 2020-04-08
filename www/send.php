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
use League\Flysystem;
use Schoolbox\Application\BasicRequest\Kernel;
use Schoolbox\Error\Mapper;
use Schoolbox\IConfigLoader;
use Schoolbox\Permission\Access\UserAccess;
use Schoolbox\Resource\LogMapper;
use Schoolbox\Resource\Storage\S3\PreSignedUrlService;

require_once __DIR__ . '/../conf/core/require.php';
$kernel = new Kernel();

if (is_numeric($_GET['id'])) {
    $page = new SitePage;
    $groupHandler = new Group;
    $fileHandler = false;
    $fileMapper = Schoolbox\Resource\File\Mapper::getInstance();


    if (isset($_GET['folder'])) {

        if ($groupHandler->getPermission($_GET['id']) < UserAccess::ACCESS_READ) {
            error(Mapper::ERROR_PERM_DOWNLOAD_FILE);
        }

        $config = array(
            'sort' => 'date',
            'sortdir' => 'DESC',
            'size' => 1,
        );

        $file = $fileMapper->findForFolders([ $_GET['id'] ], $config)->first();

    } else {
        try {
            $file = $fileMapper->findById($_GET['id']);

            if ($groupHandler->getPermission($file->getFolder()->getId()) < UserAccess::ACCESS_READ) {
                error(Mapper::ERROR_PERM_DOWNLOAD_FILE);
            }

            LogMapper::getInstance()->logRead($file, $_SESSION['user']);
        } catch (Schoolbox\Mapper\Exception $e) {
            error(Mapper::ERROR_FILE_NOT_FOUND);
        }
    }

    $attachmentType = (!$file->getMimetype()->getInline() || $_GET['save']) ? 'attachment;' : '';
    $contentType = ($file->getMime()) ?: 'application/octet-stream';
    $basename = $file->getBasename();
    $fileSystem = $file->getStorage()->getFilesystem();
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
                $file->getPath(),
                $basename,
                $cacheControl
            );

            $preSignedUrlService->download($preSignedUrlDataObject);
        }
    }


    //send headers
    header("Content-Type: $contentType");
    header("Pragma: ");
    header("Cache-control: private");
    header("Content-Ranges: none");
    header("Content-Disposition: $attachmentType filename=\"" . $basename . "\"");
    header("Content-Length: {$file->getSize()}");
    fpassthru($file->readStream());
    exit;
}

error(Mapper::ERROR_FILE_NOT_FOUND);

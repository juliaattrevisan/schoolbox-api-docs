<?php

use Schoolbox\Resource\Attachment\Collector\CollectorFactory;
use Schoolbox\Resource\Storage\FilesystemProxy;
use Schoolbox\Resource\Storage\Model;

require_once __DIR__ . '/../../conf/core/require.php';
$kernel = new \Schoolbox\Application\WebRequest\Kernel();

/** @var SitePage $page */
$page = $kernel->get('sitePage');
$page->initSession();

if (!Config::get('webdav_enable')) {
    error(2);
}

$fileHandler = PortfolioWebDAV::factory();


if (isset($_POST['description']) || isset($_FILES['file']['tmp_name']) || isset($_POST['attachment'])) {
    $file = array(
        'parent' => $_POST['parent'],
        'description' => $_POST['description'] ?? '',
    );

    if (is_numeric($_POST['fileID'])) {
        // check user owns file
        $current = $fileHandler->getByID($_POST['fileID']);

        if ($current['owner'] != $_SESSION['id']) {
            $page->error(47);
        }

        $file['id'] = $_POST['fileID'];
        $fileHandler->update($file);
    } else {
        // if file was ajax-uploaded
        if (isset($_POST['attachment'])) {
            try {
                /** @var FilesystemProxy $filesystemProxy */
                $filesystemProxy = $kernel->get('filesystem.storageProxy');
                $factory         = new CollectorFactory();
                /** @var Model $result */
                $result = $factory->build($_POST['attachment'])->getFirst();
                $fileHandler->create(
                    $file,
                    [
                        'name'     => $result->getBasename(),
                        'resource' => $filesystemProxy->readStream($result),
                    ]
                );
            } catch (\Schoolbox\Mapper\Exception $e) {
                error(41);
                exit; // shut UP, storm.
            } catch (\InvalidArgumentException $e) {
                error(43);
                exit;
            }
        } elseif (isset($_FILES['file']['tmp_name'])) {
            $allowedFileHandler = new AllowedFile();
            $allowedFileHandler->handleError($allowedFileHandler->fileSecurityCheck($_FILES['file']));
            $fileHandler->create($file, $_FILES['file']);
        }
    }

    SitePage::localRedir('/drives/' . $_POST['parent']);
} elseif (is_numeric($_GET['delete'] ?? false)) {
    if (!$file = $fileHandler->getByID($_GET['delete'])) {
        error(2);
    }

    if ($file['owner'] != $_SESSION['id']) {
        $page->error(48);
    }

    $fileHandler->delete($_GET['delete']);

    if (isset($_REQUEST['ajax'])) {
        die(json_encode(['result' => 'success']));
    } else {
        SitePage::localRedir('/drives/' . $file['parent']);
    }
} else {
    if (is_numeric($_POST['move']) && is_numeric($_POST['categoryID'])) {
        /* Move file from one category to another, after checking permission on both from and to categories
          */
        if (!$file = $fileHandler->getByID($_POST['move'])) {
            echo "Failed";
        }

        if ($file['owner'] != $_SESSION['id']) {
            echo "Insufficient permssions";
        }

        $fileHandler->update(array('id' => $_POST['move'], 'parent' => $_POST['categoryID']));
    }
}

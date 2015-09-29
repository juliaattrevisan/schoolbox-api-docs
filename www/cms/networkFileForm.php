<?php
require_once(dirname(__FILE__) . '/../../include/Bootstrap.php');

use Schoolbox\Resource\Attachment\Collector\CollectorFactory;
use Schoolbox\Resource\Storage\Model;

$page = new SitePage();

if (!Cfg::get('webdav_enable')) {
    error(2);
}

$fileHandler = PortfolioWebDAV::factory();


if (isset($_POST['description']) || isset($_FILES['file']['tmp_name']) || isset($_POST['attachment'])) {

    $file = array(
        'parent' => $_POST['parent'],
        'description' => $_POST['description'],
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
            $factory = new CollectorFactory();
            /** @var Model $result */
            $result   = $factory->build($_POST['attachment'])->getFirst();
            $filename = tempnam(Config::get('zip_dir'), '');
            $fp       = fopen($filename, 'wb');
            stream_copy_to_stream($result->readStream(), $fp);
            $fileHandler->create(
                $file,
                [
                    'name'     => $result->getBasename(),
                    'tmp_name' => $filename,
                ]
            );
        } elseif (isset($_FILES['file']['tmp_name'])) {
            $allowedFileHandler = new AllowedFile;
            $allowedFileHandler->handleError($allowedFileHandler->fileSecurityCheck($_FILES['file']));
            $fileHandler->create($file, $_FILES['file']);
        }
    }

    SitePage::localRedir('/cms/network/' . $_POST['parent']);
} elseif (is_numeric($_GET['delete'])) {

    if (!$file = $fileHandler->getByID($_GET['delete'])) {
        error(2);
    }

    if ($file['owner'] != $_SESSION['id']) {
        $page->error(48);
    }

    $fileHandler->delete($_GET['delete']);

    SitePage::localRedir('/cms/network/' . $file['parent']);
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

<?php
require_once( dirname(__FILE__) . '/../../include/Bootstrap.php' );

use Schoolbox\Resource\Attachment\Collector\CollectorFactory;
use \Schoolbox\Resource\CategoryMapper;
use \Schoolbox\Resource\FileMapper;
use \Schoolbox\Resource\File;
use \Schoolbox\Resource\Link;
use \Schoolbox\Resource\LinkMapper;

/* Force session usage due to flash uploader
 * http://www.uploadify.com/forums/discussion/43/using-sessions-tricking-basic-authentication/p1
 */
if (isset( $_POST[session_name()] )) {
    session_id($_POST[session_name()]);
}

if (isset( $_GET[session_name()] )) {
    session_id($_GET[session_name()]);
}

$ajax = isset($_GET['ajax']) && $_GET['ajax'];

if ($ajax) {
    header("Content-Type: application/json");
}

$page = new SitePage();
$page->setAjax($ajax);

$fileHandler = new File();
$groupHandler = new Group();


/* Upload single file */
if (is_string($_POST['description']) && isset($_POST['category'])) {
// get a file object or die
    try {
        // if we're editing an existing file, ensure the file exists
        if (is_numeric($_POST['fileID'])) {
            $file = FileMapper::getInstance()->findById($_POST['fileID']);

        } else {
            $file = FileMapper::getInstance()->createModel();

        }

        // if file was ajax-uploaded
        if (isset($_POST['attachment'])) {
            $factory = new CollectorFactory(FileMapper::getInstance());
            /** @var File $result */
            $result  = $factory->build($_POST['attachment'])->getFirst();

            if ($file->getId()) {
                $result->setId($file->getId());
            }

            // Yes, we may have just overridden the result of findById(), but that was also for additional safety checks
            $file = $result;
        }

    } catch (\Schoolbox\Mapper\Exception $e) {
        error(41);
        exit; // shut UP, storm.
    } catch (\Schoolbox\Resource\Exception $e) {
        error(43);
        exit;
    }

    // if file was synchronously uploaded
    if ($_FILES['file']['name'] && is_uploaded_file($_FILES['file']['tmp_name'])) {
        $file->setTempFileLocation($_FILES['file']['tmp_name']);
        $file->setFilename($_FILES['file']['name']);
        $file->setFilesize(filesize($_FILES['file']['tmp_name']));
    } else {
        $originalLocation = $file->getFileLocation();
        if ($originalLocation) {
            $file->setTempFileLocation($originalLocation);
        }
    }

    // set attributes
    $file->setOwner($_SESSION['id']);
    $file->setDescription($_POST['description']);
    $file->setComment($_POST['comment']);
    $file->setHidden($_POST['hidden'] ? true : false);
    $file->setModified(Alaress_Date_Format::now());

    // set copy source

    // this is the collection we'll be saving
    $collection = new \Schoolbox\Collection();

    // we can iterate, even if we're only iterating one
    $destinations = is_array($_POST['category']) ? $_POST['category'] : array($_POST['category']);

    foreach ($destinations as $cat) {
        if ($groupHandler->getPermission($cat) < WRITE_ACCESS) {
            error(6);
        }

        $clone = clone $file;
        $clone->setFolder(CategoryMapper::getInstance()->createModel()->setId($cat));

        $collection->add($clone);
    }

    try {
        $affectedIds = FileMapper::getInstance()->create($collection);

        /* For some reason this is necessary for upload indication to reach completed status */
        if ($_POST['uploadifydie'] == true) {
            exit(true);
        }

        if (isset($_POST['return'])) {
            SitePage::localRedir($_POST['return']);

        } elseif ($ajax) {
            header(':', true, 201);
            die(json_encode(array('id' => $affectedIds[0])));

        } else {
            $page->backToTheFuture(2);
        }

    } catch (Exception $e) {
        // Output errors
        if ($e->getCode() == \Schoolbox\File\Exception::CODE_MIMETYPE_NOT_FOUND) {
            $code = 11;
            $msg  = "Your file, '{$file->getFilename()}' is not a valid filetype";

        } elseif ($e->getCode() == \Schoolbox\File\Exception::CODE_FILE_TOO_LARGE) {
            $code = 10;
            $msg  = 'The size of the uploaded file exceeds the file size restrictions of '.Alaress_Byte_Format::bytesToString($file->getMimetype()->getMaxFileSize()).'.';

        } else {
            $code = 9;
            $msg  = $e->getMessage();
        }

        if ($ajax) {
            header(':', true, 400);
            die(json_encode(array('code' => $code, 'error' => $msg)));
        } else {
            error($code, $msg);
        }
    }
} /* Move file from one category to another */
elseif (is_numeric($_POST['move']) && is_numeric($_POST['categoryID'])) {
try {
        $file = FileMapper::getInstance()->findById($_POST['move']);

        if ($groupHandler->getPermission($file->getFolder()->getId()) < WRITE_ACCESS) {
            $result = "Insufficient permssions";
        } elseif ($groupHandler->getPermission($_POST['categoryID']) < WRITE_ACCESS) {
            $result = "Insufficient permssions";
        } else {
            $file->setFolder(CategoryMapper::getInstance()->createModel($_POST['categoryID']));
            $file->setModified(Alaress_Date_Format::now());

            FileMapper::getInstance()->save($file);
            $result = "success";
        }
} catch (Exception $e) {
            $result = "File not found";
        }

    header("Content-type: application/json");
    print json_encode(array( 'result' => $result ));
} /* Delete a single file */
elseif (is_numeric($_GET['delete'])) {
    try {
        $file = FileMapper::getInstance()->findById($_GET['delete']);

        if ($groupHandler->getPermission($file->getFolder()->getId()) < WRITE_ACCESS) {
            error(6);
        }

        FileMapper::getInstance()->delete($file);
    } catch (Exception $e) {
        error(2);
    }

    if ($_GET['redirect'] == '') {
        $page->backToTheFuture(1);
    } else {
        SitePage::localRedir($_GET['redirect']);
    }
} /* Delete a list of files */
elseif (is_numeric($_GET['deleteList'])) {
    $fileList = explode(',', rtrim($_GET['fileList'], ','));

    if (is_array($fileList)) {
        foreach ($fileList as $fileID) {
            if (is_numeric($fileID)) {try {
                    $file = FileMapper::getInstance()->findById($fileID);

                    if ($groupHandler->getPermission($file->getFolder()->getId()) < WRITE_ACCESS) {
                        error(6);
                    }

                    FileMapper::getInstance()->delete($file);
            } catch (Exception $e) {
                        error(2);
            }
            }
        }
    }

    $linkList = explode(',', rtrim($_GET['linkList'], ','));
    if (is_array($linkList)) {
        foreach ($linkList as $linkID) {
            try {
                /** @var Link $link */
                $link = LinkMapper::getInstance()->findById($linkID);
                if ($groupHandler->getPermission($link->getFolder()->getId()) < WRITE_ACCESS) {
                    error(6);
                }
                LinkMapper::getInstance()->delete($link);
            } catch (Exception $e) {
                error(2);
            }
        }
    }

    $page->backToTheFuture(1);
} /* Exceeded PHP max file size */
elseif ($_SERVER['REQUEST_METHOD'] == 'POST' && $_SERVER['CONTENT_LENGTH'] > 0 && empty($_POST) && empty($_FILES)) {
    error(10);
} /* Unknown action */
else {
    error(2);
}

<?php
require_once(dirname(__FILE__) . '/../../include/Bootstrap.php');

use Schoolbox\Resource\File\IFile;
use \Schoolbox\Resource\My\File\Mapper as MyFileMapper;
use \Schoolbox\Resource\My\File\Model as MyFile;
use Schoolbox\Resource\Attachment\Collector\CollectorFactory;

$ajax = isset($_GET['ajax']) && $_GET['ajax'];

if ($ajax) {
    header("Content-Type: application/json");
}

$page = new SitePage();
$page->setAjax($ajax);

/** @var \Schoolbox\User $user */
if (isset($_POST['user'])) {
    try {
        $user = \Schoolbox\UserMapper::getInstance()->findById($_POST['user']);
    } catch (\Schoolbox\Mapper\Exception $e) {
        error(41);
        exit; // shut UP, storm.
    }
} else {
    $user = $_SESSION['user'];
}

// PERMISSION: have to be session user or staff
$groupHandler = new Group();
if (!($groupHandler->isStaff() || $_SESSION['user']->getId() == $user->getId())) {
    if ($ajax) {
        header(':', true, 400);
        die(json_encode('You do not have write permission to this ePortfolio.'));
    } else {
        error(47);
    }
}

// Upload a new file
if (isset($_POST['description'])) {
    /** @var MyFile $file */
    $file = null;

    try {
        // if we're editing an existing file, ensure the file exists
        if (is_numeric($_POST['fileID'])) {
            $file = MyFileMapper::getInstance()->findById($_POST['fileID']);

        } else {
            $file = MyFileMapper::getInstance()->createModel();
        }

        if (isset($_POST['attachment'])) {
            $factory = new CollectorFactory(MyFileMapper::getInstance());
            /** @var MyFile $result */
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
    }

    // if file was synchronously uploaded
    if ($_FILES['file']['name'] && is_uploaded_file($_FILES['file']['tmp_name'])) {
        try {
            // fake this momentarily
            $file->setFilename($_FILES['file']['name']);

            // dump into storage
            $storage = Schoolbox\Resource\Storage\Mapper::getInstance()->accept($_FILES['file']['name'], $_FILES['file']['tmp_name'], false);

            // and link onto a fresh object
            $file = MyFileMapper::getInstance()->createModel()->setStorage($storage);
        } catch (Exception $e) {
            outputException($e, $file, $ajax);
        }
    }

    // set attributes
    if (is_null($file->getFilename())) {
        $file->setFilename($file->getStorage()->getBasename());
    }

    $file->setCreatedAt(Alaress_Date_Format::now());
    $file->setOwner($user);
    $file->setTitle($_POST['description']);
    $file->setFolder(\Schoolbox\Resource\My\Folder\Mapper::getInstance()->createModel()->setId($_POST['parent']));

    try {

        /* Spawn a new file if creating; reuse the existing one if modifying
         */
        if (!is_numeric($file->getId())) {
            $affectedIds = MyFileMapper::getInstance()->create($file);
        }
        else {
            $affectedIds = array($file->getId());
            MyFileMapper::getInstance()->save($file);
        }

        /* If any checkboxes were selected, publish to eportfolio My Files components on various pages
         */
        if (isset($_POST['published']) && is_array($_POST['published']) && count($_POST['published'])) {
            $now = new Alaress_Date_Format($_SERVER['REQUEST_TIME'], 'U');

            foreach ($_POST['published'] as $ownerId => $pageIds) {
                if (is_array($pageIds) && count($pageIds)) {
                    foreach ($pageIds as $currentPageId => $unused) {
                        $authenticator = new Eportfolio_Authenticator($_SESSION['id'], $ownerId, $currentPageId);
                        if ($authenticator->canModify()) {
                            $publishedFile = new \Component\Eportfolio\FileList();
                            $publishedFile->setPageId($currentPageId);
                            $publishedFile->setFileId($file->getId());
                            $publishedFile->setCreatedAt($now);
                            $publishedFile->setUpdatedAt($now);

                            \Component\Eportfolio\FileListMapper::getInstance()->save($publishedFile);
                        }
                    }

                    // Image component:
                    // set uploaded file as the current image
                    $componentId = $_POST['component_instance_id'];
                    if (!empty($componentId)) {
                        $components = Component_InstanceMapper::getInstance()
                            ->findByInstanceId($componentId);
                        if (!empty($components[$componentId])) {
                            $component = $components[$componentId];
                            if ($component instanceof Component_Instance
                                && in_array($component->getIdentifier(), array_keys($pageIds))
                                && $component->getDefinition()->getClass()
                                == 'Component_Eportfolio_ImageController'
                            ) {
                                // set this component's image to this one
                                foreach ($component->getConfig() as $config) {
                                    if ($config->getKey() == 'eportfolioImage') {
                                        $configInstance = new Component_Config();
                                        $configInstance->setId(null);
                                        $configInstance->setConfigDefinitionId($config->getId());
                                        $configInstance->setInstanceId($component->getId());
                                        $configInstance->setValue($file->getId());
                                        Component_ConfigMapper::getInstance()->save($configInstance);
                                        break;
                                    }
                                }
                            }
                        }
                    }
                }
            }
        }

        if (isset($_POST['return'])) {
            SitePage::localRedir($_POST['return'] . '&fileID=' . $fileID);

        } elseif ($ajax) {
            header(':', true, 201);
            die(json_encode(array('id' => $affectedIds[0])));

        } else {
            $page->backToTheFuture(2);
        }
    } catch (\Schoolbox\File\Exception $e) {
        outputException($e, $file, $ajax);
    } catch (\Schoolbox\Mapper\Exception $e) {
        header('Content-Type: text/html');
        die($e);
    }
} // delete a single file
elseif (is_numeric($_GET['delete'])) {
    try {
        $parent = deleteMyFile($_GET['delete']);
    } catch (Schoolbox\Resource\Exception $e) {
        error($e->getCode());
    }
    if (isset($_REQUEST['ajax'])) {
        die(json_encode(['result' => 'success']));
    } else {
        SitePage::localRedir("/my-files/$parent");
    }
} // delete multiple files
elseif (isset($_GET['fileList']) && is_numeric($_GET['deleteList'])) {
    $fileList = array_values(array_filter(explode(',', $_GET['fileList'])));
    $errorList = array();
    $parent = 0;
    if (is_array($fileList) && count($fileList)) {
        foreach ($fileList as $file) {
            try {
                // it's fine for this to be overwritten each iteration
                $parent = deleteMyFile($file);
            } catch (Schoolbox\Resource\Exception $e) {
                $errorList[] = $e->getCode();
            }
        }
        if (count($errorList)) {
            error($errorList[0]);
        }
        SitePage::localRedir("/my-files/$parent");
    } else {
        error(2);
    }
} // relocate a file
elseif (is_numeric($_POST['move']) && is_numeric($_POST['categoryID'])) {
    /* Move file from one category to another, after checking permission on both from and to categories
      */
    try {
        /** @var MyFile $file */
        $file = MyFileMapper::getInstance()->findById($_POST['move']);

        if ($file->getOwner()->getId() != $_SESSION['id']) {
            die('Insufficient permissions');
        }
        $file->getFolder()->setId($_POST['categoryID']);
        MyFileMapper::getInstance()->save($file);
    } catch (Schoolbox\Resource\Exception $e) {
        die('Failed');
    }
}


/**
 * @param int $fileId
 * @throws Schoolbox\Resource\Exception
 * @return int
 */
function deleteMyFile($fileId)
{
    try {
        /** @var MyFile $file */
        $file      = MyFileMapper::getInstance()->findById($fileId);
        $ownerId   = $file->getOwner()->getId();
        $sessionId = $_SESSION['user']->getId();
        $isSuper   = $_SESSION['user']->isSuperuser();

        if ($ownerId == $sessionId || $isSuper) {
            if (MyFileMapper::getInstance()->delete($file)) {
                return $file->getFolder()->getId();
            }
            throw new Schoolbox\Resource\Exception("Error", 2); // fail to delete
        }
        throw new Schoolbox\Resource\Exception("Error", 48); // permissions insufficient
    } catch (Schoolbox\Mapper\Exception $e) {
        throw new Schoolbox\Resource\Exception("Error", 41); // no any device exists
    }
}


/**
 * @param Exception $e
 * @param IFile     $file
 * @param bool      $ajax
 */
function outputException(Exception $e, IFile $file, $ajax = false) {

    // Output errors
    if ($e->getCode() == \Schoolbox\File\Exception::CODE_MIMETYPE_NOT_FOUND) {
        $code = 11;
        $msg  = "Your file, '{$file->getFilename()}' is not a valid filetype";

    } elseif ($e->getCode() == \Schoolbox\File\Exception::CODE_FILE_TOO_LARGE) {
        $code = 10;
        $msg  = 'The size of the uploaded file exceeds the file size restrictions of '
            . Alaress_Byte_Format::bytesToString($file->getMimetype()->getMaxFileSize()) . '.';

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
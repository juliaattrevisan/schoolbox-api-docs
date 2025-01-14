<?php

use Aura\Router\Generator;
use Schoolbox\Application\WebRequest\Kernel;
use Schoolbox\Learning\Assessment\Redirect;
use Schoolbox\Permission\Access\UserAccess;
use Schoolbox\Error\Mapper as ErrorMapper;
use Schoolbox\Resource\Attachment\Collector\CollectorFactory;
use Schoolbox\Resource\Folder\Mapper as FolderMapper;
use Schoolbox\Resource\File\IFile;
use Schoolbox\Resource\File\Model as File;
use Schoolbox\Resource\File\Mapper as FileMapper;
use Schoolbox\Resource\Link;
use Schoolbox\Resource\LinkMapper;

require_once __DIR__ . '/../../conf/core/require.php';
$kernel = new Kernel();

$ajax = isset($_GET['ajax']) && $_GET['ajax'];

if ($ajax) {
    header("Content-Type: application/json");
}

/** @var SitePage $page */
$page = $kernel->get('sitePage');
$page->initSession();
$page->setAjax($ajax);
/** @var Generator $routeGenerator */
$routeGenerator = $page->getDi()->get('router.generator');

$groupHandler = new Group();

$modifyExisting = is_numeric($_POST['fileID'] ?? null);

/* Upload single file */
if (is_string($_POST['description'] ?? null) && isset($_POST['category'])) {
// get a file object or die
    try {
        // if we're editing an existing file, ensure the file exists
        if ($modifyExisting) {
            $file = FileMapper::getInstance()->findById($_POST['fileID'] ?? null);
        } else {
            $file = FileMapper::getInstance()->createModel();
        }
    } catch (\Schoolbox\Mapper\Exception $e) {
        throw ErrorMapper::getInstance()->generateException(ErrorMapper::ERROR_FILE_NOT_FOUND);
    }

    // if file was uploaded from Post File page
    if (!$ajax) {
        if (isset($_POST['attachment'])) {
            try {
                $factory = new CollectorFactory(FileMapper::getInstance());
                /** @var File $result */
                $result = $factory->build($_POST['attachment'])->getFirst();

                // if the attachment is an already-existing file
                if ($result->getId() && $result->getId() == $file->getId()) {
                    // mirror, mirror (the file on disk was not changed)
                    $file = $result;
                } else {
                    // target attachment is from SBFS, but a different file: override storage
                    $file->setStorage($result->getStorage());
                }
            } catch (\Schoolbox\Resource\Exception $e) {
                throw ErrorMapper::getInstance()->generateException(ErrorMapper::ERROR_FILE_UPLOAD_INCOMPLETE);
            } catch (\InvalidArgumentException $e) {
                throw ErrorMapper::getInstance()->generateException(ErrorMapper::ERROR_FILE_UPLOAD_INCOMPLETE);
            }
        } else {
            throw ErrorMapper::getInstance()->generateException(ErrorMapper::ERROR_INFORMATION_MISSING);
        }
    }

    // if file was uploaded from File Component / Resource Browser
    if (isset($_FILES['file']['name'], $_FILES['file']['tmp_name']) && is_uploaded_file($_FILES['file']['tmp_name'])) {
        try {
            // fake this momentarily
            $file->setFilename($_FILES['file']['name']);

            // do a storage upload, then wrap it in the SBFS file from earlier
            $storage = Schoolbox\Resource\Storage\Mapper::getInstance()->accept($_FILES['file']['name'], $_FILES['file']['tmp_name'], false);

            $file->setStorage($storage);
        } catch (Exception $e) {
            outputException($e, $file, $ajax);
        }
    }

    // set attributes

    $file->setFilename($file->getStorage()->getFilename());

    if (!$modifyExisting) {
        /* Only set the ownership to the current user if it's a new upload -
         * prevents ownership being overwritten if another user modifies the file
         */
        $file->setOwner($_SESSION['user']);
    }
    $file->setTitle($_POST['description'] ?? '');
    $file->setDescription($_POST['comment'] ?? '');
    // If not set default to whatever in admin settings
    $visible = $_POST['visible'] ?? !$kernel->get('config')->get('file_default_to_hidden');

    $file->setHidden(!$visible);
    $file->setModifiedAt(Alaress_Date_Format::now());

    try {
        // sooo, this case is much simpler
        if ($modifyExisting) {
            if ($groupHandler->getPermission($_POST['category']) < UserAccess::ACCESS_WRITE || 0 == $_POST['category']) {
                throw ErrorMapper::getInstance()->generateException(ErrorMapper::ERROR_PERM_CATEGORY_WRITE);
            }

            $file->setModifiedBy($_SESSION['user']);
            $file->setFolder(FolderMapper::getInstance()->createModel()->setId($_POST['category']));
            $affectedIds = FileMapper::getInstance()->save($file);
        } else {
            // this is the collection we'll be saving
            $collection = new \Schoolbox\Collection();

            // we can iterate, even if we're only iterating one
            $destinations = is_array($_POST['category']) ? $_POST['category'] : array($_POST['category']);

            foreach ($destinations as $cat) {
                if ($groupHandler->getPermission($cat) < UserAccess::ACCESS_WRITE || 0 == $cat) {
                    throw ErrorMapper::getInstance()->generateException(ErrorMapper::ERROR_PERM_CATEGORY_WRITE);
                }

                $clone = clone $file;
                $clone->setFolder(FolderMapper::getInstance()->createModel()->setId($cat));

                $collection->add($clone);
            }

            $affectedIds = FileMapper::getInstance()->create($collection);
        }

        // Did caller request to inject the file into a specific component?
        if (isset($_POST['component']) && is_numeric($_POST['component']) && $_POST['component'] > 0) {
            // Retrieve component
            $componentInstance = Component_InstanceMapper::getInstance()->findByInstanceId($_POST['component'], $_POST['category']);

            // Ensure this component resides on the specified page (extra sanity test, not strictly required)
            if ($componentInstance->getIdentifier() != $_POST['category']) {
                throw new DomainException("Component Instance ({$componentInstance->getId()}) does not reside on folder ({$componentInstance->getIdentifier()})");
            }

            // Different components will behave differently (it's not enough to merely specify the ConfigDef.Id in the URl)
            switch ($componentInstance->getDefinition()->getName()) {
                case 'Image':
                    $configDefinition = $componentInstance->getConfigByKey('image');
                    $value = $file->getTitle();
                    break;
                // You can insert other component types here. (use definition.name to identify)
                default:
                    //noop
                    $value = $configDefinition = null;
            }

            // Is this a component we know how to configure?
            if ($configDefinition) {
                try {
                    $configInstance = Component_ConfigMapper::getInstance()->findOneByColumnList([
                        'component_config_definition_id' => $configDefinition->getId(),
                        'component_instance_id'          => $componentInstance->getId(),
                    ]);
                } catch (\Schoolbox\Mapper\Exception $e) {
                    $configInstance = Component_ConfigMapper::getInstance()->createModel();
                    $configInstance->setConfigDefinitionId($configDefinition->getId());
                    $configInstance->setInstanceId($componentInstance->getId());
                }
                $configInstance->setValue($value);
                Component_ConfigMapper::getInstance()->save($configInstance);
            }
        }

        /* For some reason this is necessary for upload indication to reach completed status */
        if (($_POST['uploadifydie'] ?? false) == true) {
            exit(true);
        }

        if (isset($_POST['return'])) {
            SitePage::localRedir($_POST['return']);
        } elseif ($ajax) {
            http_response_code(201);
            die(json_encode([
                'id'                    => $affectedIds[0],
                'name'                  => $file->getTitle(),
                'url'                   => '/send.php?id=' . $affectedIds[0],
                'mime'                  => [
                    'mimeType'          => $file->getStorage()->getMimetype()->getMime(),
                    'iconFile'          => $file->getStorage()->getMimetype()->getIconFile(),
                    'description'       => $file->getStorage()->getMimetype()->getDescription(),
                    'url'               => '/images/mime/' . $file->getStorage()->getMimetype()->getIconFile(),
                ],
                'hidden'                => $file->isHidden(),
                'type'                  => $file->getStorage()->getMimetype()->getType(),
                'hash'                  => $file->getHash(),
                'size'                  => $file->getSize(),
                'dateModified'          => [
                    'timestamp' => $file->getModifiedAt()->getUnixTimeStamp(),
                    'formatted' => $file->getModifiedAt()->getShort(),
                ],
                'editUrl'               => '/cms/file/' . $affectedIds[0],
                'visibilityUrl' => $routeGenerator->generate('resourceBrowser.patchFileInfo', ['fileId' => $affectedIds[0]]),
            ]));
        } else {
            $page->backToTheFuture(2);
        }
    } catch (Exception $e) {
        outputException($e, $file, $ajax);
    }
} elseif (is_numeric($_POST['move'] ?? null) && is_numeric($_POST['categoryID'] ?? null)) {
    /* Move file from one category to another */
    try {
        $file = FileMapper::getInstance()->findById($_POST['move']);

        if (0 == $_POST['categoryID']) {
            $result = "Cannot attach files to root";
        } elseif ($groupHandler->getPermission($file->getFolder()->getId()) < UserAccess::ACCESS_WRITE) {
            $result = "Insufficient permssions";
        } elseif ($groupHandler->getPermission($_POST['categoryID']) < UserAccess::ACCESS_WRITE) {
            $result = "Insufficient permssions";
        } else {
            $file->setFolder(FolderMapper::getInstance()->createModel($_POST['categoryID']));
            $file->setModifiedBy($_SESSION['user']);
            $file->setModifiedAt(Alaress_Date_Format::now());

            FileMapper::getInstance()->save($file);
            $result = "success";
        }
    } catch (Exception $e) {
        $result = "File not found";
    }

    header("Content-type: application/json");
    print json_encode(array('result' => $result));
} elseif (is_numeric($_GET['delete'] ?? null)) {
    /* Delete a single file */
    try {
        $file = FileMapper::getInstance()->findById($_GET['delete']);

        if ($groupHandler->getPermission($file->getFolder()->getId()) < UserAccess::ACCESS_WRITE) {
            throw ErrorMapper::getInstance()->generateException(ErrorMapper::ERROR_PERM_CATEGORY_WRITE);
        }

        FileMapper::getInstance()->delete($file);
    } catch (\Schoolbox\Mapper\Exception $e) {
        throw ErrorMapper::getInstance()->generateException(ErrorMapper::ERROR_INFORMATION_MISSING);
    }

    if (isset($_REQUEST['ajax'])) {
        die(json_encode(['result' => 'success']));
    }
    $encodedReturnUrl = $_GET[Redirect::RETURN_URL] ?? null;
    if (!$encodedReturnUrl) {
        $page::backToTheFuture(1);
    } else {
        SitePage::localRedir(Redirect::decode($encodedReturnUrl));
    }
} elseif (is_numeric($_GET['deleteList'] ?? null)) {
    /* Delete a list of files */
    $fileList = array_filter(explode(',', rtrim($_GET['fileList'] ?? '', ',')));

    if (is_array($fileList)) {
        foreach ($fileList as $fileID) {
            if (is_numeric($fileID)) {
                try {
                    $file = FileMapper::getInstance()->findById($fileID);

                    if ($groupHandler->getPermission($file->getFolder()->getId()) < UserAccess::ACCESS_WRITE) {
                        throw ErrorMapper::getInstance()->generateException(ErrorMapper::ERROR_PERM_CATEGORY_WRITE);
                    }

                    FileMapper::getInstance()->delete($file);
                } catch (Exception $e) {
                    throw ErrorMapper::getInstance()->generateException(ErrorMapper::ERROR_INFORMATION_MISSING);
                }
            }
        }
    }

    $linkList = array_filter(explode(',', rtrim($_GET['linkList'] ?? '', ',')));
    if (is_array($linkList)) {
        foreach ($linkList as $linkID) {
            try {
                /** @var Link $link */
                $link = LinkMapper::getInstance()->findById($linkID);
                if ($groupHandler->getPermission($link->getFolder()->getId()) < UserAccess::ACCESS_WRITE) {
                    throw ErrorMapper::getInstance()->generateException(ErrorMapper::ERROR_PERM_CATEGORY_WRITE);
                }
                LinkMapper::getInstance()->delete($link);
            } catch (Exception $e) {
                throw ErrorMapper::getInstance()->generateException(ErrorMapper::ERROR_INFORMATION_MISSING);
            }
        }
    }

    $page->backToTheFuture(1);
} elseif ($_SERVER['REQUEST_METHOD'] == 'POST' && $_SERVER['CONTENT_LENGTH'] > 0 && empty($_POST) && empty($_FILES)) {
    /* Exceeded PHP max file size */
    throw ErrorMapper::getInstance()->generateException(ErrorMapper::ERROR_FILE_SIZE);
} else {
    /* Unknown action */
    throw ErrorMapper::getInstance()->generateException(ErrorMapper::ERROR_INFORMATION_MISSING);
}


/**
 * @param Exception $e
 * @param IFile     $file
 * @param bool      $ajax
 */
function outputException(Exception $e, IFile $file, $ajax = false)
{

    // Output errors
    if ($e->getCode() == \Schoolbox\File\Exception::CODE_MIMETYPE_NOT_FOUND) {
        $code = 11;
    } elseif ($e->getCode() == \Schoolbox\File\Exception::CODE_FILE_TOO_LARGE) {
        $code = 10;
    } else {
        $code = 9;
    }

    $msg  = $e->getMessage();

    if ($ajax) {
        http_response_code(400);
        die(json_encode(array('code' => $code, 'error' => $msg)));
    } else {
        throw ErrorMapper::getInstance()->generateException($code, null, $msg);
    }
}

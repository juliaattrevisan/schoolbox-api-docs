<?php
require_once(dirname(__FILE__) . '/../../include/Bootstrap.php');

use \Schoolbox\MyFileMapper;
use \Schoolbox\MyFile;

$page = new SitePage();

$userId = isset($_POST['user']) ? $_POST['user'] : $_SESSION['id'];

// PERMISSION: have to be session user or staff
$groupHandler = new Group();
if(!($groupHandler->isStaff() || $_SESSION['id'] == $userId)) {
	error(47);
}

//$fileHandler = new MyFile($userId);

// Upload a new file
if (isset($_POST['description']) || isset($_FILES['file']['tmp_name'])) {

    $file = array(
        'parent' => $_POST['parent'],
        'description' => $_POST['description'],
    );

    if (is_numeric($_POST['fileID'])) {
        // check user owns file
        /** @var MyFile $currentFile */
		$currentFile = MyFileMapper::getInstance()->findById($_POST['fileID']);

        if ($currentFile->getOwner()->getId() != $userId) {
            $page->error(47);
        }
    } else {
        $allowedFileHandler = new AllowedFile;
        $allowedFileHandler->handleError($allowedFileHandler->fileSecurityCheck($_FILES['file']));

		/* TODO: this should be made consistent with File -_-
		 */
		$mime = \Schoolbox\File\MimetypeMapper::getInstance()->getByFilename($_FILES['file']['name']);

        $currentFile = new MyFile();
		$currentFile->setTempFileLocation($_FILES['file']['tmp_name']);
		$currentFile->setFilename($_FILES['file']['name']);
		$currentFile->setFilesize(filesize($_FILES['file']['tmp_name']));
		$currentFile->setMime($mime);
		$currentFile->setCreatedAt(new Alaress_Date_Format(time(), 'U'));
		$currentFile->setDeleted(false);
    }

    $parent = new \Schoolbox\MyFolder();
    $parent->setId($_POST['parent']);

    $currentFile->setParent($parent);
    $currentFile->setOwner($_SESSION['user']);
    $currentFile->setDescription($_POST['description']);

    MyFileMapper::getInstance()->save($currentFile);


    /* If any checkboxes were selected, publish to eportfolio My Files components on various pages
     */
	if (isset($_POST['published']) && is_array($_POST['published']) && count($_POST['published'])) {
		$now = new Alaress_Date_Format($_SERVER['REQUEST_TIME'], 'U');

		foreach($_POST['published'] as $ownerId => $pageIds) {
			if (is_array($pageIds) && count($pageIds)) {
				foreach($pageIds as $currentPageId => $unused) {
					$authenticator = new Eportfolio_Authenticator($_SESSION['id'], $ownerId, $currentPageId);
					if ($authenticator->canModify()) {
						$publishedFile = new \Component\Eportfolio\FileList();
						$publishedFile->setPageId($currentPageId);
						$publishedFile->setFileId($currentFile->getId());
						$publishedFile->setCreatedAt($now);
						$publishedFile->setUpdatedAt($now);

						\Component\Eportfolio\FileListMapper::getInstance()->save($publishedFile);
					}
				}
			}
		}
	}

    if ( isset( $_POST['return'] ) ) {
        SitePage::localRedir($_POST['return'] . '&fileID=' . $fileID);
    }
    else {
        $page->backToTheFuture(2);
    }
}
// delete a single file
elseif (is_numeric($_GET['delete'])) {
    try {
        $parent = deleteMyFile($_GET['delete']);
    } catch (Schoolbox\Resource\Exception $e) {
        error($e->getCode());
    }
    SitePage::localRedir("/cms/portfolio/$parent");
}
// delete multiple files
elseif (isset($_GET['fileList']) && is_numeric($_GET['deleteList'])) {
	$fileList = array_values(array_filter(explode(',', $_GET['fileList'])));
	$errorList = array();
	$parent = 0;
	if(is_array($fileList) && count($fileList)) {
		foreach($fileList as $file) {
            try {
                // it's fine for this to be overwritten each iteration
                $parent = deleteMyFile($file);
            } catch (Schoolbox\Resource\Exception $e) {
                $errorList[] = $e->getCode();
			}
		}
		if(count($errorList)) {
			error($errorList[0]);
		}
        SitePage::localRedir("/cms/portfolio/$parent");
	}
	else {
		error(2);
	}
}
// relocate a file
else if (is_numeric($_POST['move']) && is_numeric($_POST['categoryID'])) {
    /* Move file from one category to another, after checking permission on both from and to categories
      */
    try {
        /** @var MyFile $file */
        $file = MyFileMapper::getInstance()->findById($_POST['move']);

        if ($file->getOwner()->getId() != $_SESSION['id']) {
            die('Insufficient permissions');
        }
        $file->getParent()->setId($_POST['categoryID']);
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
function deleteMyFile($fileId) {
    try {
        /** @var MyFile $file */
        $file      = MyFileMapper::getInstance()->findById($fileId);
        $ownerId   = $file->getOwner()->getId();
        $sessionId = $_SESSION['user']->getId();
        $isSuper   = $_SESSION['user']->isSuperuser();

        if ($ownerId == $sessionId || $isSuper) {
            if (MyFileMapper::getInstance()->delete($file)) {
                return $file->getParent()->getId();
            }
            throw new Schoolbox\Resource\Exception("Error", 2); // fail to delete
        }
        throw new Schoolbox\Resource\Exception("Error", 48); // permissions insufficient
    } catch(Schoolbox\Mapper\Exception $e) {
        throw new Schoolbox\Resource\Exception("Error", 41); // no any device exists
    }
}

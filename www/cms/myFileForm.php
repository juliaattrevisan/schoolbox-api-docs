<?php
require_once(dirname(__FILE__) . '/../../include/Bootstrap.php');

$page = new SitePage();

require_once $_SERVER['DOCUMENT_ROOT'] . '/../library/my-file.php';
$fileHandler = new MyFile();

// Upload a new file
if (isset($_POST['description']) || isset($_FILES['file']['tmp_name'])) {

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

        $fileID = $file['id'] = $_POST['fileID'];
        $fileHandler->update($file);
    }
    else {
        $allowedFileHandler = new AllowedFile;
        $allowedFileHandler->handleError($allowedFileHandler->fileSecurityCheck($_FILES['file']));

        $fileID = $fileHandler->create($file, $_FILES['file']);
    }

	/* If any checkboxes were selected, publish to eportfolio My Files components on various pages
	 */
	if (isset($_POST['published']) && is_array($_POST['published']) && count($_POST['published'])) {
		$now = new Alaress_Date_Format($_SERVER['REQUEST_TIME'], 'U');

		foreach($_POST['published'] as $ownerId => $pageIds) {
			if (is_array($pageIds) && count($pageIds)) {
				foreach($pageIds as $currentPageId => $componentInstanceIds) {
					$authenticator = new Eportfolio_Authenticator($_SESSION['id'], $ownerId, $currentPageId);

					if (is_array($componentInstanceIds) && count($componentInstanceIds) && $authenticator->canModify()) {
						foreach($componentInstanceIds as $currentComponentInstanceId => $value) {
							$publishedFile = new \Component\Eportfolio\FileList();
							$publishedFile->setComponentInstanceId($currentComponentInstanceId);
							$publishedFile->setFileId($fileID);
							$publishedFile->setCreatedAt($now);
							$publishedFile->setUpdatedAt($now);

							\Component\Eportfolio\FileListMapper::getInstance()->save($publishedFile);
						}
					}
				}
			}
		}
	}

    if ( isset( $_POST['return'] ) ) {
        SitePage::localRedir($_POST['return'] . '&fileID=' . $fileID);
    }
    else {
        SitePage::localRedir('/cms/portfolio/' . $_POST['parent']);
    }
}
// delete a single file
elseif (is_numeric($_GET['delete'])) {
	$e = deleteMyFile($fileHandler, $_GET['delete']);
	if(is_numeric($e)) {
		error($e);
	}
    SitePage::localRedir('/cms/portfolio/' . $e['parent']);
}
// delete multiple files
elseif (isset($_GET['fileList']) && is_numeric($_GET['deleteList'])) {
	$fileList = array_values(array_filter(explode(',', $_GET['fileList'])));
	$errorList = array();
	$parent = 0;
	if(is_array($fileList) && count($fileList)) {
		foreach($fileList as $file) {
			$e = deleteMyFile($fileHandler, $file);
			if(is_numeric($e)) {
				$errorList[] = $e;
			}
			else {
				// it's fine for this to be overwritten each iteration
				$parent = $e['parent'];
			}
		}
		if(count($errorList)) {
			error($errorList[0]);
		}
		SitePage::localRedir('/cms/portfolio/' . $parent);
	}
	else {
		error(2);
	}
}
// relocate a file
else if (is_numeric($_POST['move']) && is_numeric($_POST['categoryID'])) {
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


/**
 * @param MyFile $fileHandler
 * @param int $fileId
 * @return int errorCode | array file
 */
function deleteMyFile($fileHandler, $fileId) {
	if (!$file = $fileHandler->getByID($fileId)) {
		return 41; // file does not exist
	}
	if ($file['owner'] != $_SESSION['id']) {
		return 48; // don't have permission
	}
	return $fileHandler->delete($fileId)
		? $file
		: 2; // not enough info submitted (should theoretically never happen based on above checks)
}

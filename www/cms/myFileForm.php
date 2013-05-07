<?php
require_once(dirname(__FILE__) . '/../../include/Bootstrap.php');

$page = new SitePage();

require_once $_SERVER['DOCUMENT_ROOT'] . '/../library/my-file.php';
$fileHandler = new MyFile();

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
elseif (is_numeric($_GET['delete'])) {

    if (!$file = $fileHandler->getByID($_GET['delete'])) {
        error(2);
    }

    if ($file['owner'] != $_SESSION['id']) {
        $page->error(48);
    }

    $fileHandler->delete($_GET['delete']);

    SitePage::localRedir('/cms/portfolio/' . $file['parent']);
}
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

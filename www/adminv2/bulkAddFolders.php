<?php

$groupHandler = new Group();

if (!$groupHandler->isSuperuser()) {
    error(19);
}

$this->setTitle('Admin - Bulk Add Folders');
$this->setFile(TEMPLATE_content, 'adminv2/bulkAddFolders.html');
$this->setBlock(TEMPLATE_content, 'folder', 'folder_block');

// include the admin navigation
$nav = new AdminV2_Nav();
$nav->parse($this, 'folder');

/* Two types of upload.. either single user.. or via csv file..
 */
if (isset($_FILES['bulkadd']['tmp_name']) && $_FILES['bulkadd']['tmp_name']) {
	$bulkHandler = new Bulk_Category($_FILES['bulkadd']['tmp_name'], Scrub::text($_POST['template']));
	$allFolders = $bulkHandler->addCategories();

	if ($allFolders && count($allFolders)) {
		foreach ($allFolders as $currentFolder) {
			$this->setVar(array(
				'FOLDER_FOLDER' => $currentFolder['category'],
				'FOLDER_STATUS' => $currentFolder['status'],
				'FOLDER_ERROR' => $currentFolder['error'],
			));

			$this->parse('folder_block', 'folder', true);
		}
	}
	else {
		$this->setVar('folder_block', '');
	}
}
else {
	error(2);
}

<?php
require_once $_SERVER['DOCUMENT_ROOT'] . '/../include/sitePage.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/../library/generic.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/../library/group.php';

$page = new SitePage();
$groupHandler = new Group();

if ($groupHandler->isSuperuser()) {
	$tableIndex = 'fileTypeID';

	/* Set up generic table handler thing.
	 */
	$allowedFileTypesHandler = new GenericTable('allowedFileTypes', $tableIndex);
	$allowedFileExtensionHandler = new GenericTable('allowedFileExtension', $tableIndex);

	/* Delete or create/update
	 */
	if (is_numeric($_GET['delete'])) {
		$allowedFileTypesHandler->delete(scrubTEXT($_GET['delete']));
		$allowedFileExtensionHandler->delete(scrubTEXT($_GET['delete']));
	}
	else {
		$data = array();
		$allExtensions = array();

		$allColumns = array_keys($_POST);

		foreach($allColumns as $currentColumn) {

			/* Don't add extension column to allowedFileTypes, but remember it for
			 * addition into allowedFileExtension
			 */
			if ($currentColumn == 'extension') {
				$allExtensions = explode(',', $_POST[$currentColumn]);
			}
			else {
				$data[$currentColumn] = scrubTEXT($_POST[$currentColumn]);
			}
		}

		/* Create New or Update existing
		 */
		if (is_numeric($_POST[$tableIndex])) {
			$allowedFileTypesHandler->update($data);

			$allowedFileExtensionHandler->delete($data['fileTypeID']);

			$fileTypeID = $data['fileTypeID'];
		}
		else {
			$fileTypeID = $allowedFileTypesHandler->create($data);
		}

		/* Create/update valid file extensions table too
		 */
		foreach($allExtensions as $currentExtension) {
			$data = array(
				'fileTypeID' => $fileTypeID,
				'extension' => scrubTEXT(trim($currentExtension))
			);

			$allowedFileExtensionHandler->create($data);
		}
	}

	$page->backToTheFuture(1);
}
else {
	error(19);
}
?>
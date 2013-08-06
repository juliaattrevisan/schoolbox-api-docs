<?php
require_once $_SERVER['DOCUMENT_ROOT'] . '/../include/SitePage.php';

require_once('../include/pclzip.lib.php');

$page = new SitePage();
$fileHandler = new File();
$groupHandler = new Group();

if ($groupHandler->isSuperuser()) {

	/* Force session usage due to flash uploader
	 * http://www.uploadify.com/forums/discussion/43/using-sessions-tricking-basic-authentication/p1
	 */
	if (isset($_POST[session_name()])) {
		session_id($_POST[session_name()]);
	}

	if (isset($_GET[session_name()])) {
		session_id($_GET[session_name()]);
	}

	/* Extract files
	 */
	$archive = new PclZip($_FILES['file']['tmp_name']);

	$zipFileUploadHandler = new ZipFileUpload();
	$zipFileUploadHandler->extract($archive);
	$zipFileUploadHandler->traverse($zipFileUploadHandler->getPath(), $_POST['category']);
	$zipFileUploadHandler->cleanup();

	/* Error handling
	 */
	$allErrors = $zipFileUploadHandler->getErrors();

	if (count($allErrors)) {
		$page->setFile(TEMPLATE_content, 'cms/zipFileError.html');
		$page->setBlock(TEMPLATE_content, 'error', 'error_block');

		foreach ($allErrors as $currentError) {
			$page->setVar(array(
				'FILENAME' => $currentError['file'],
				'MESSAGE' => $currentError['message'],
			));

			$page->parse('error_block', 'error', true);
		}

		$page->outputPage();
	}
	else {
		SitePage::localRedir('/resources');
	}
}
else {
	error(19);
}

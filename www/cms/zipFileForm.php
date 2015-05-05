<?php
require_once $_SERVER['DOCUMENT_ROOT'] . '/../include/Bootstrap.php';

$page = new SitePage();
$groupHandler = new Group();

$isSuperuser = $groupHandler->isSuperuser();
$isAllowedTeacher = ($groupHandler->isStaff() && \Cfg::get('enable_teacher_zip_upload'));

/* Superusers and allowed teachers (via config option) are the only
 * permitted user types to upload zip folder hierarchies
 */
if ($isSuperuser || $isAllowedTeacher) {

	/* Check if a file was selected!
	 */
	if (!strlen($_FILES['file']['tmp_name'])) {
		error(2);
	}

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
	$archive = new ZipArchive();
	$archive->open($_FILES['file']['tmp_name']);

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

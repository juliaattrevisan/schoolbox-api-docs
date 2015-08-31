<?php
require_once __DIR__ . '/../../include/Bootstrap.php';

use Schoolbox\Resource\Attachment\Collector\CollectorFactory;
use Schoolbox\Resource\Storage;

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
	$filename = '';
	if (isset($_POST['attachment'])) {
		$factory = new CollectorFactory();
		try {
			/** @var Storage\Model $result */
			$result = $factory->build($_POST['attachment'])->getFirst();
		} catch (\Schoolbox\Resource\Exception $e) {
			error(2);
			exit;
		}
		// Ensure the file is available locally
		$filename = tempnam(sys_get_temp_dir(), 'zip');
		$fp       = fopen($filename, 'wb');
		stream_copy_to_stream($result->readStream(), $fp);
		fclose($fp);
	} elseif (strlen($_FILES['file']['tmp_name']) > 0) {
		$filename = $_FILES['file']['tmp_name'];
	} else {
		error(2);
		exit;
	}

	/* Extract files
	 */
	$archive = new ZipArchive();
	$archive->open($filename);

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

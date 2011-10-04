<?php
require_once(dirname(__FILE__) . '/../../include/Bootstrap.php');

$page = new SitePage;

$submission_box_handler = new Submission();

if (is_numeric($_POST['id']) && $_POST['due_date'] && $_POST['due_hour'] && $_POST['late_date'] && $_POST['late_hour']) {

	/* Prevent 12pm to 12am-next-day bug by checking that the hour submitted is
	 * not equal to 12. This stops the date incrementing to the next day.
	 */
	$dueDate = new Alaress_Date_Format($_POST['due_date'] . ' ' . $_POST['due_hour'] . ':' . $_POST['due_minute'] . ' ' . $_POST['due_period'], 'd/m/y');
	$due_date = $dueDate->getDatabase();

	$lateDate = new Alaress_Date_Format($_POST['late_date'] . ' ' . $_POST['late_hour'] . ':' . $_POST['late_minute'] . ' ' . $_POST['late_period'], 'd/m/y');
	$late_date = $lateDate->getDatabase();

	$openDate = new Alaress_Date_Format($_POST['open_date'] . ' ' . $_POST['open_hour'] . ':' . $_POST['open_minute'] . ' ' . $_POST['open_period'], 'd/m/y');
	$open_date = $openDate->getDatabase();

	if (!$open_date || !$due_date || !$late_date) {
		error(15);
	}

	/* Late date is greater than due date
	 */
	if (!empty($_POST['accept_submission']) && $due_date > $late_date) {
		error(15);
	}

	$submission_box = array(
		'id' => $_POST['id'],
		'accept_submission' => !empty($_POST['accept_submission']) ? true : false,
		'open_date' => $open_date,
		'date' => $due_date,
		'late_date' => $late_date,
		'name' => $_POST['name'],
		'description' => $_POST['description'],
		'type' => $_POST['type'],
	);

	$submission_box_handler->update($submission_box);

	$page->localRedir("/submission/listSubmission/");
}
else if ($_POST['due_date'] && $_POST['due_hour'] && $_POST['late_date'] && $_POST['late_hour']) {
	if (isset($_FILES['file']['name']) && $_FILES['file']['name']) {
		$allowedFileHandler = new AllowedFile();
		$allowedFileHandler->handleError($allowedFileHandler->fileSecurityCheck($_FILES['file']));

		$fileHandler = new File();

		$data = array(
			'description' => $_POST['name'],
			'comment' => $_POST['comment'],
			'category' => $_POST['category'],
			'owner' => $_SESSION['id'],
		);
		$fileId = $fileHandler->create($data, $_FILES['file']);
	}
	else {
		$fileId = $_POST['fileId'];
	}

	/* Prevent 12pm to 12am-next-day bug by checking that the hour submitted is
	 * not equal to 12. This stops the date incrementing to the next day.
	 */
	$dueDate = new Alaress_Date_Format($_POST['due_date'] . ' ' . $_POST['due_hour'] . ':' . $_POST['due_minute'] . ' ' . $_POST['due_period'], 'd/m/y');
	$due_date = $dueDate->getDatabase();

	$lateDate = new Alaress_Date_Format($_POST['late_date'] . ' ' . $_POST['late_hour'] . ':' . $_POST['late_minute'] . ' ' . $_POST['late_period'], 'd/m/y');
	$late_date = $lateDate->getDatabase();

	$openDate = new Alaress_Date_Format($_POST['open_date'] . ' ' . $_POST['open_hour'] . ':' . $_POST['open_minute'] . ' ' . $_POST['open_period'], 'd/m/y');
	$open_date = $openDate->getDatabase();

	if (!$open_date || !$due_date || !$late_date) {
		error(15);
	}

	/* Late date is greater than due date
	 */
	if (!empty($_POST['accept_submission']) && $due_date > $late_date) {
		error(15);
	}

	/* Create Submission Box
	 */
	$submission_box = array(
		'category' => $_POST['category'],
		'accept_submission' => !empty($_POST['accept_submission']) ? true : false,
		'open_date' => $open_date,
		'date' => $due_date,
		'late_date' => $late_date,
		'name' => $_POST['name'],
		'description' => $_POST['description'],
		'type' => $_POST['type'],
		'fid' => $fileId,
		'created' => 'NOW()',
		'owner' => $_SESSION['id'],
	);

	$submission_box_handler->create($submission_box);

	$page->localRedir("/submission/listSubmission/");
}
/* Exceeded PHP max file size */
elseif ( $_SERVER['REQUEST_METHOD'] == 'POST' && $_SERVER['CONTENT_LENGTH'] > 0 && empty($_POST) && empty($_FILES) ) {
    error(10);
}
else if (isset($_GET['deleteBox']) && is_numeric($_GET['deleteBox'])) {
	$submission_box_handler->delete($_GET['deleteBox']);

	$page->localRedir("/submission/listSubmission/");
}
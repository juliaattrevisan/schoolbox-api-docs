<?php
require_once(dirname(__FILE__) . '/../../include/Bootstrap.php');

$page = new SitePage;

$submission_box_handler = new Submission();

if (is_numeric($_POST['id']) && $_POST['due_date']) {

    // retrieve the file if there is one
    if (isset($_FILES['file']['name']) && $_FILES['file']['name']) {
        $allowedFileHandler = new AllowedFile();
        $allowedFileHandler->handleError($allowedFileHandler->fileSecurityCheck($_FILES['file']));
        $fileHandler = new File();
        $data = array(
            'owner'       => $_SESSION['id'],
            'description' => $_POST['name'],
            'type'        => $_POST['type'],
            'comment'     => $_POST['comment'],
            'category'    => $_POST['category'],
            'hidden'      => '1',
        );
        $fileId = $fileHandler->create($data, $_FILES['file']);
    }
    else {
        $fileId = 0;
    }

	/* Prevent 12pm to 12am-next-day bug by checking that the hour submitted is
	 * not equal to 12. This stops the date incrementing to the next day.
	 *
	 * Also set the time to the start or end of the day if all_day is set
	 */

    if(empty($_POST['due_hour'])) {
        $dueDate = new Alaress_Date_Format($_POST['due_date'] . ' 23:59:59', 'd/m/y');
    }
    else {
        $dueDate = new Alaress_Date_Format($_POST['due_date'] . ' ' . $_POST['due_hour'] . ':' . $_POST['due_minute'] . ' ' . $_POST['due_period'], 'd/m/y');
    }

    // if we're not accepting submissions. override the open date to now() and late date to due date
    if(empty($_POST['accept_submission'])) {
        $openDate = new Alaress_Date_Format(time(), 'U');
        $lateDate = $dueDate;
    }
    else {
        if(empty($_POST['open_hour'])) {
            $openDate = new Alaress_Date_Format($_POST['open_date'] . ' 00:00:00', 'd/m/y');
        }
        else {
            $openDate = new Alaress_Date_Format($_POST['open_date'] . ' ' . $_POST['open_hour'] . ':' . $_POST['open_minute'] . ' ' . $_POST['open_period'], 'd/m/y');
        }

        if(empty($_POST['late_hour'])) {
            $lateDate = new Alaress_Date_Format($_POST['late_date'] . ' 23:59:59', 'd/m/y');
        }
        else {
            $lateDate = new Alaress_Date_Format($_POST['late_date'] . ' ' . $_POST['late_hour'] . ':' . $_POST['late_minute'] . ' ' . $_POST['late_period'], 'd/m/y');
        }
    }

    $due_date = $dueDate->getDatabase();
    $late_date = $lateDate->getDatabase();
    $open_date = $openDate->getDatabase();

	if (!$open_date || !$due_date || !$late_date) {
		error(15);
	}

	/* Late date is greater than due date
	 */
	if (!empty($_POST['accept_submission']) && $due_date > $late_date) {
		error(15);
	}

    // if Total Mark selected, mark value must be specified
    if ($_POST['marktype'] == 2 && empty($_POST['markvalue'])) {
        error(15);
    }

	$submission_box = array(
		'id'                => $_POST['id'],
		'accept_submission' => !empty($_POST['accept_submission']) ? true : false,
        'multi_submission'  => !empty($_POST['multi_submission'])  ? true : false,
		'open_date'         => $open_date,
		'date'              => $due_date,
		'late_date'         => $late_date,
		'name'              => $_POST['name'],
		'description'       => $_POST['description'],
		'type'              => $_POST['type'],
        'mark_type'         => empty($_POST['marktype']) ? '4' : $_POST['marktype'], // legacy support
        'max_mark'          => !$_POST['markvalue'] ? '1' : $_POST['markvalue'],
        'notif_enabled'     => !empty($_POST['notif_enabled']) ? true : false,
	);

    // only set this field if there's actually something to put in it
    if($fileId) {
        $submission_box['fid'] = $fileId;
    }

	$submission_box_handler->update($submission_box);

    $page->backToTheFuture(2);
    // $page->localRedir("/submission/listSubmission/");
}
else if ($_POST['due_date']) {
	if (isset($_FILES['file']['name']) && $_FILES['file']['name']) {
		$allowedFileHandler = new AllowedFile();
		$allowedFileHandler->handleError($allowedFileHandler->fileSecurityCheck($_FILES['file']));

		$fileHandler = new File();

		$data = array(
            'owner'       => $_SESSION['id'],
            'description' => $_POST['name'],
            'type'        => $_POST['type'],
            'comment'     => $_POST['comment'],
            'category'    => $_POST['category'],
            'hidden'      => '1',
		);
		$fileId = $fileHandler->create($data, $_FILES['file']);
	}
	else {
		$fileId = $_POST['fileId'];
	}

    /* Prevent 12pm to 12am-next-day bug by checking that the hour submitted is
	 * not equal to 12. This stops the date incrementing to the next day.
	 *
	 * Also set the time to the start or end of the day if all_day is set
	 */

    if(empty($_POST['due_hour'])) {
        $dueDate = new Alaress_Date_Format($_POST['due_date'] . ' 23:59:59', 'd/m/y');
    }
    else {
        $dueDate = new Alaress_Date_Format($_POST['due_date'] . ' ' . $_POST['due_hour'] . ':' . $_POST['due_minute'] . ' ' . $_POST['due_period'], 'd/m/y');
    }

    // if we're not accepting submissions. override the open date to now() and late date to due date
    if(empty($_POST['accept_submission'])) {
        $openDate = new Alaress_Date_Format(time(), 'U');
        $lateDate = $dueDate;
    }
    else {
        if(empty($_POST['open_hour'])) {
            $openDate = new Alaress_Date_Format($_POST['open_date'] . ' 00:00:00', 'd/m/y');
        }
        else {
            $openDate = new Alaress_Date_Format($_POST['open_date'] . ' ' . $_POST['open_hour'] . ':' . $_POST['open_minute'] . ' ' . $_POST['open_period'], 'd/m/y');
        }

        if(empty($_POST['late_hour'])) {
            $lateDate = new Alaress_Date_Format($_POST['late_date'] . ' 23:59:59', 'd/m/y');
        }
        else {
            $lateDate = new Alaress_Date_Format($_POST['late_date'] . ' ' . $_POST['late_hour'] . ':' . $_POST['late_minute'] . ' ' . $_POST['late_period'], 'd/m/y');
        }
    }

    $due_date = $dueDate->getDatabase();
    $late_date = $lateDate->getDatabase();
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
		'category'          => $_POST['category'],
		'accept_submission' => !empty($_POST['accept_submission']) ? true : false,
        'multi_submission'  => !empty($_POST['multi_submission'])  ? true : false,
		'open_date'         => $open_date,
		'date'              => $due_date,
		'late_date'         => $late_date,
		'name'              => $_POST['name'],
		'description'       => $_POST['description'],
		'type'              => $_POST['type'],
        'mark_type'         => $_POST['marktype'],
		'fid'               => $fileId,
		'created'           => 'NOW()',
		'owner'             => $_SESSION['id'],
        'max_mark'          => !$_POST['markvalue'] ? '100' : $_POST['markvalue'] // if not set, default to 100
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
else {
    // not enough data submitted to the form
    error(2);
}
<?php

session_start();

require_once $_SERVER['DOCUMENT_ROOT'] . '/../include/sitePage.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/../library/submission.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/../library/submissionStudent.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/include/time.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/../library/assess/assess.php';

$submission_box_handler = new Submission;

if(is_numeric($_POST['id']) && $_POST['due_date'] && $_POST['due_hour'] && $_POST['late_date'] && $_POST['late_hour']) {

    /* Modify existing submission box */
    $open_date = new DateTime( str_replace('/','-',$_POST['open_date']));
    $due_date  = new DateTime( str_replace('/','-',$_POST['due_date']));
    $late_date = new Datetime( str_replace('/','-',$_POST['late_date']));

    if (!$open_date || !$due_date || !$late_date) {
        error(15);
    }

    if ($_POST['due_period'] == 'pm')
        $due_date->setTime($_POST['due_hour']+12, $_POST['due_minute']);
    else
        $due_date->setTime($_POST['due_hour'], $_POST['due_minute']);

    if ($_POST['open_period'] == 'pm')
        $open_date->setTime($_POST['open_hour']+12, $_POST['open_minute']);
    else
        $open_date->setTime($_POST['open_hour'], $_POST['open_minute']);
    
    if ($_POST['late_period'] == 'pm')
        $late_date->setTime($_POST['late_hour']+12, $_POST['late_minute']);
    else
        $late_date->setTime($_POST['late_hour'], $_POST['late_minute']);

    /* Late date is greater than due date */
    if (!empty($_POST['accept_submission']) && $due_date > $late_date) {
        error(15);
    }

    $submission_box = array(
        'id'                => $_POST['id'],
        'accept_submission' => !empty($_POST['accept_submission']) ? true : false,
        'open_date'         => $open_date->format(Cfg::get('date_database')),
        'date'              => $due_date->format(Cfg::get('date_database')),
        'late_date'         => $late_date->format(Cfg::get('date_database')),
        'description'       => $_POST['description'],
        'type'              => $_POST['type'],
    );

	$submission_box_handler->update($submission_box);

    localRedir("/submission/listSubmission/");
}
else if($_POST['due_date'] && $_POST['due_hour'] && $_POST['late_date'] && $_POST['late_hour']) {
    
    if( isset($_FILES['file']['name']) && $_FILES['file']['name'] ) {
        require_once $_SERVER['DOCUMENT_ROOT'] . '/../library/allowed-file.php';
        require_once $_SERVER['DOCUMENT_ROOT'] . '/../library/file.php';

        $allowedFileHandler = new AllowedFile;
        $allowedFileHandler->handleError($allowedFileHandler->fileSecurityCheck($_FILES['file']));

        $fileHandler = new File;
        $data = array(  'description'	=> $_POST['description'],
                'comment'		=> $_POST['comment'],
                'category'		=> $_POST['category'],
                'owner'		=> $_SESSION['id'],
        );
        $fileId = $fileHandler->create($data, $_FILES['file']);

    }
    else {
        $fileId = $_POST['fileId'];
    }

    /* Date and Time */
    $open_date = new DateTime( str_replace('/','-',$_POST['open_date']));
    $due_date  = new DateTime( str_replace('/','-',$_POST['due_date']));
    $late_date = new Datetime( str_replace('/','-',$_POST['late_date']));

    if (!$open_date || !$due_date || !$late_date) {
        error(15);
    }

    if ($_POST['due_period'] == 'pm')
        $due_date->setTime($_POST['due_hour']+12, $_POST['due_minute']);
    else
        $due_date->setTime($_POST['due_hour'], $_POST['due_minute']);

    if ($_POST['open_period'] == 'pm')
        $open_date->setTime($_POST['open_hour']+12, $_POST['open_minute']);
    else
        $open_date->setTime($_POST['open_hour'], $_POST['open_minute']);

    if ($_POST['late_period'] == 'pm')
        $late_date->setTime($_POST['late_hour']+12, $_POST['late_minute']);
    else
        $late_date->setTime($_POST['late_hour'], $_POST['late_minute']);

    /* Late date is greater than due date */
    if (!empty($_POST['accept_submission']) && $due_date > $late_date) {
        error(15);
    }
    
    /* Create Submission Box */
    $submission_box = array(
        'category'          => $_POST['category'],
        'accept_submission' => !empty($_POST['accept_submission']) ? true : false,
        'open_date'         => $open_date->format(Cfg::get('date_database')),
        'date'              => $due_date->format(Cfg::get('date_database')),
        'late_date'         => $late_date->format(Cfg::get('date_database')),
        'description'       => $_POST['description'],
        'type'              => $_POST['type'],
        'fid'               => $fileId,
        'created'           => 'NOW()',
        'owner'             => $_SESSION['id'],
    );

	$submission_box_handler->create($submission_box);

    localRedir("/submission/listSubmission/");
}
else if(isset($_GET['deleteBox']) && is_numeric($_GET['deleteBox'])) {
	$submission_box_handler->delete($_GET['deleteBox']);

	localRedir("/submission/listSubmission/");
}
?>
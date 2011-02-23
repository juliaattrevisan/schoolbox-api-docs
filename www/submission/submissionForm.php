<?php

session_start();

require_once $_SERVER['DOCUMENT_ROOT'] . '/../include/sitePage.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/../library/submission.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/../library/submissionStudent.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/include/time.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/../library/assess/assess.php';

$subBoxHandler = new Submission;

if($_POST['due_date'] && $_POST['due_hour'] && $_POST['late_date'] && $_POST['late_hour'] && is_numeric($_POST['id'])) {
    /* Modify existing submission box */
    list($odate,$omonth,$oyear) = explode('/',$_POST['open_date'],3);
    list($date,$month,$year) = explode('/',$_POST['due_date'],3);
    list($ldate,$lmonth,$lyear) = explode('/',$_POST['late_date'],3);
    
    if(!checkdate($omonth,$odate,$oyear) )
        error(15);//open date is wrong
    
    if(!checkdate($month,$date,$year) )
        error(15);//due date is wrong
    
    if(!checkdate($lmonth,$ldate,$lyear) )
        error(15);//late date is wrong
    
    if(mktime(0,0,0,$month,$date,$year) > mktime(0,0,0,$lmonth,$ldate,$lyear))
        error(15);//late date is greater than due date
    
    //validate time
    checkTime($_POST['open_hour'],$_POST['open_minute']);
    checkTime($_POST['due_hour'],$_POST['due_minute']);
    checkTime($_POST['late_hour'],$_POST['late_minute']);

    $openTime = ausDateToUnix($_POST['open_date'].' '.$_POST['open_hour'].':'.$_POST['open_minute'].' '.$_POST['open_period'], true);
    $dueTime = ausDateToUnix($_POST['due_date'].' '.$_POST['due_hour'].':'.$_POST['due_minute'].' '.$_POST['due_period'], true);
    $lateTime = ausDateToUnix($_POST['late_date'].' '.$_POST['late_hour'].':'.$_POST['late_minute'].' '.$_POST['late_period'], true);

    //modify submission box
    $subbox = array('id'        => $_POST['id'],
            'open_date' => outputDate(DATE_database, $openTime),
            'date'      => outputDate(DATE_database, $dueTime),
            'late_date' => outputDate(DATE_database, $lateTime),
            'description'=> $_POST['description'],
            'type'     => $_POST['type'],
    );
	$subBoxHandler->update($subbox);

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

    list($odate,$omonth,$oyear) = explode('/',$_POST['open_date'],3);
    list($date,$month,$year) = explode('/',$_POST['due_date'],3);
    list($ldate,$lmonth,$lyear) = explode('/',$_POST['late_date'],3);

    if(!checkdate($omonth,$odate,$oyear) )
        error(15);//open date is wrong

    if(!checkdate($month,$date,$year) )
        error(15);//due date is wrong

    if(!checkdate($lmonth,$ldate,$lyear) )
        error(15);//late date is wrong

    if(mktime(0,0,0,$month,$date,$year) > mktime(0,0,0,$lmonth,$ldate,$lyear))
        error(15);//late date is greater than due date

    //validate time
    checkTime($_POST['open_hour'],$_POST['open_minute']);
    checkTime($_POST['due_hour'],$_POST['due_minute']);
    checkTime($_POST['late_hour'],$_POST['late_minute']);

    $openTime = ausDateToUnix($_POST['open_date'].' '.$_POST['open_hour'].':'.$_POST['open_minute'].' '.$_POST['open_period'], true);
    $dueTime = ausDateToUnix($_POST['due_date'].' '.$_POST['due_hour'].':'.$_POST['due_minute'].' '.$_POST['due_period'], true);
    $lateTime = ausDateToUnix($_POST['late_date'].' '.$_POST['late_hour'].':'.$_POST['late_minute'].' '.$_POST['late_period'], true);

	/* Special action for Online Assessments
	 */
	if ($_POST['type'] == Assess::ONLINE_ASSESSMENT_TYPE_ID) {
		$assess = array(
			'category_id' => scrubTEXT($_POST['category']),
			'user_id' => $_SESSION['id'],
			'type' => Assess::ONLINE_ASSESSMENT_TYPE_ID,
			'title' => scrubTEXT($_POST['description']),
			'description' => scrubTEXT($_POST['description']),
			'start' => date('Y-m-d H:i:s', $openTime),
			'finish' => date('Y-m-d H:i:s', $dueTime),
			'duration' => -1,
			'allow_resit' => 0
		);

		$assessHandler = new Assess();
		$assessHandler->create($assess);
	}
	else {

		//modify submission box
		$subbox = array('category'        => $_POST['category'],
				'open_date' => outputDate(DATE_database, $openTime),
				'date'      => outputDate(DATE_database, $dueTime),
				'late_date' => outputDate(DATE_database, $lateTime),
				'description'=> $_POST['description'],
				'type'     => $_POST['type'],
				'fid'       => $fileId,
				'created'   => 'NOW()',
				'owner'		=> $_SESSION['id'],
		);

		$subBoxHandler->create($subbox);
	}

    localRedir("/submission/listSubmission/");
}
else if(isset($_GET['deleteBox']) && is_numeric($_GET['deleteBox'])) {
    $subBoxHandler->delete($_GET['deleteBox']);

	localRedir("/submission/listSubmission/");
}
?>
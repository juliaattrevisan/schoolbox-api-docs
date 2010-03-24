<?php
require_once $_SERVER['DOCUMENT_ROOT'] . '/../include/sitePage.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/../library/submission.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/../library/submissionStudent.php';


$subBoxHandler = new Submission;


if($_POST['due_date'] && $_POST['due_hour'] && $_POST['late_date'] && $_POST['late_hour'] && $_POST['sid']) {
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

    $openTime = strtotime($_POST['open_date'].' '.$_POST['open_hour'].':'.$_POST['open_minute'].' '.$_POST['open_period']);
    $dueTime = strtotime($_POST['due_date'].' '.$_POST['due_hour'].':'.$_POST['due_minute'].' '.$_POST['due_period']);
    $lateTime = strtotime($_POST['late_date'].' '.$_POST['late_hour'].':'.$_POST['late_minute'].' '.$_POST['late_period']);

    //modify submission box
    $subbox = array('id'        => $_POST['sid'],
            'open_date' => $openTime,
            'date'      => $dueTime,
            'late_date' => $lateTime,
            'description'=> $_POST['description'],
            'type'     => $_POST['type'],
    );
    $subBoxHandler->update($subbox);

    localRedir("/submission/listSubmission.php");
}
else if($_POST['due_date'] && $_POST['due_hour'] && $_POST['late_date'] && $_POST['late_hour']) {
    
    if( isset($_FILES['file']['name']) && $_FILES['file']['name'] ) {
        require_once $_SERVER['DOCUMENT_ROOT'] . '/../library/allowed-file.php';
        require_once $_SERVER['DOCUMENT_ROOT'] . '/../library/file.php';

        $allowedFileHandler = new AllowedFile;
        $allowedFileHandler->fileSecurityCheck($_FILES['file']);

        $fileHandler = new File;
        $data = array(  'description'	=> $_POST['description'],
                'comment'		=> $_POST['comment'],
                'category'		=> $_POST['cid'],
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

    $openTime = strtotime($_POST['open_date'].' '.$_POST['open_hour'].':'.$_POST['open_minute'].' '.$_POST['open_period']);
    $dueTime = strtotime($_POST['due_date'].' '.$_POST['due_hour'].':'.$_POST['due_minute'].' '.$_POST['due_period']);
    $lateTime = strtotime($_POST['late_date'].' '.$_POST['late_hour'].':'.$_POST['late_minute'].' '.$_POST['late_period']);

    //modify submission box
    $subbox = array('id'        => $_POST['sid'],
            'open_date' => $openTime,
            'date'      => $dueTime,
            'late_date' => $lateTime,
            'description'=> $_POST['description'],
            'type'     => $_POST['type'],
            'fid'       => $fileId,
            'created'   => 'NOW()',
    );
    $subBoxHandler->create($subbox);

    localRedir("/submission/listSubmission.php");

}
else if(isset($_GET['deleteBox']) && is_numeric($_GET['deleteBox'])) {


    $subBoxHandler->delete($_GET['deleteBox']);

}
else if(isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    //Delete student submission
    $studSubHandler = new SubmissionStudent;
    $studSubHandler->delete($_GET['delete']);
}





?>

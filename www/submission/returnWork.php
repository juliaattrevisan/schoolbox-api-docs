<?php

require_once $_SERVER['DOCUMENT_ROOT'] . '/../library/submission.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/../library/user.php';

$this->setFile(TEMPLATE_content, 'submission/returnWork.html');
$this->setTitle('Return Student Work');

$subHandler = new Submission;
$userHandler = new User;

if(is_numeric($_GET['box_id']) && is_numeric($_GET['sid']) ) {

    $subbox = $subHandler->getByID($_GET['box_id']);
    $user = $userHandler->getByID($_GET['sid']);

    //Check user owners box they are returning to
    if($subbox['owner'] != $_SESSION['id'])
            error(25);
    
    $output = array('CATEGORY'  => scrubTEXT($subbox['categoryName']),
                    'ASSNAME'   => scrubTEXT($subbox['description']),
                    'BOX_ID'    => $_GET['box_id'],
                    'NAME'      => formatName($user),
                    'SID'       => $_GET['sid'],
                );

    /* TODO: Add code to output already marked details */


    $this->setVar($output);

}
else
    error(2);


?>

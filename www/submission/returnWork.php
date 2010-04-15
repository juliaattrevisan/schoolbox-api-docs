<?php

require_once $_SERVER['DOCUMENT_ROOT'] . '/../library/submission.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/../library/user.php';

$this->setFile(TEMPLATE_content, 'submission/returnWork.html');
$this->setTitle('Return Student Work');
$this->setTab('submission');

$subHandler = new Submission;
$userHandler = new User;

if(is_numeric($this->arg[0]) && is_numeric($this->arg[1]) ) {

    $subbox = $subHandler->getByID($this->arg[0]);
    $user = $userHandler->getByID($this->arg[1]);

    //Check user owners box they are returning to
    if($subbox['owner'] != $_SESSION['id'])
            error(25);
    
    $output = array('CATEGORY'  => scrubTEXT($subbox['categoryName']),
                    'ASSNAME'   => scrubTEXT($subbox['description']),
                    'BOX_ID'    => $this->arg[0],
                    'NAME'      => formatName($user),
                    'UID'       => $this->arg[1],
                );

    /* TODO: Add code to output already marked details */


    $this->setVar($output);

}
else
    error(2);


?>

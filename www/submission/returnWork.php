<?php

require_once $_SERVER['DOCUMENT_ROOT'] . '/../library/submission.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/../library/submissionReturn.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/../library/user.php';

$this->setFile(TEMPLATE_content, 'submission/returnWork.html');
$this->setTitle('Return Student Work');
$this->setTab('submission');

$subHandler = new Submission;
$userHandler = new User;
$subReturnHandler = new SubmissionReturn;

if(is_numeric($this->arg[0]) && is_numeric($this->arg[1]) ) {

    $subbox = $subHandler->getByID($this->arg[0]);
    $user = $userHandler->getByID($this->arg[1]);
    $subReturn = $subReturnHandler->getOneByUser($this->arg[1], $this->arg[0]);

    //Check user owners box they are returning to
    if($subbox['owner'] != $_SESSION['id'])
            error(25);

    $output = array('CATEGORY'  => Scrub::text($subbox['categoryName']),
                    'ASSNAME'   => Scrub::text($subbox['name']),
                    'BOX_ID'    => $this->arg[0],
                    'NAME'      => $userHandler->formatName($user),
                    'UID'       => $this->arg[1],
                    'COMMENT'   => $subReturn['comment'],
                    'MARK'      => $subReturn['mark'],
                );

    /* TODO: Add code to output already marked details */

    $this->setVar($output);

}
else
    error(2);


?>

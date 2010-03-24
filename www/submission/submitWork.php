<?php

require_once $_SERVER['DOCUMENT_ROOT'] . '/../library/submission.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/../library/submissionStudent.php';

if(is_numeric($_GET['box_id'])) {
    $this->setFile(TEMPLATE_content, 'submission/submitFile.html');
    $this->setTitle('Submit Work');

    $subHandler = new Submission;
    $studHandler = new SubmissionStudent;

    $subbox = $subHandler->getByID($_GET['box_id']);
    $late = $studHandler->securityCheck($_GET['box_id']);

    $output = array('CATEGORY'  => scrubTEXT($subbox['categoryName']),
                    'FILE'      => scrubTEXT($subbox['description']),
                    'BOX_ID'    => $_GET['box_id'],
                    );

    $this->setVar($output);
}
else
    error(2);

?>

<?php

require_once $_SERVER['DOCUMENT_ROOT'] . '/../library/submission.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/../library/submissionStudent.php';

if(is_numeric($this->arg[0])) {
    $this->setFile(TEMPLATE_content, 'submission/submitWork.html');
    $this->setTitle('Submit Work');
	$this->setTab('submission');

    $subHandler = new Submission;
    $studHandler = new SubmissionStudent;

    $subbox = $subHandler->getByID($this->arg[0]);
    $late = $studHandler->securityCheck($this->arg[0]);

    $output = array('CATEGORY'  => scrubTEXT($subbox['categoryName']),
                    'FILE'      => scrubTEXT($subbox['name']),
                    'BOX_ID'    => $this->arg[0],
                    );

    $this->setVar($output);
}
else
    error(2);

?>

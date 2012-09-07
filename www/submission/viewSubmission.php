<?php

$submissionBoxID = $this->arg[0];

// check the ID is valid
if (!is_numeric($submissionBoxID)) {
    error(2);
}

$userHandler = new User();
$groupHandler = new Group();
$submissionHandler = new Submission();
$studentSubHandler = new SubmissionStudent();

$subBox = $submissionHandler->getByID($this->arg[0]);

// if you aren't the owner or a super, you aren't allowed to be here
if($subBox['owner'] != $_SESSION['id'] && !$groupHandler->isSuperuser()) {
    error(17);
}

// we log the viewing of this page, I know not why
$submissionHandler->logPageView(3, $this->arg[0]);

// compile the list of students over which we will iterate

// get full student list of class to show those who have not submitted. If synergy code, get synergy students
if ($subBox['code'] && Cfg::get('external_enable')) {
    $synHandler = new External_Class();
    $studentList = $synHandler->getStudents($subBox['code']);
}
// failing that, retrieve student list from group membership
else {
    $studentList = $groupHandler->getMemberList($subBox['category']);
}

if(!(is_array($studentList) && count($studentList))) {
    // failed to obtain a list of students from external sources: compile list from submissions
    $studentList = array();

    $allSubmissions = $studentSubHandler->getByBoxID($this->arg[0]);
    if(is_array($allSubmissions) && count($allSubmissions)) {
        foreach($allSubmissions as $submission) {
            $studentList[] = array(
                'title'      => $submission['title'],
                'firstname'  => $submission['firstname'],
                'lastname'   => $submission['lastname'],
                'uid'        => $submission['uid'],
                'synergy_id' => $submission['synergy_id'],
                'type'       => $submission['type'],
                'group_id'   => $submission['group_id'],
            );
        }
    }
}

$dueWorkDetail = new DueWork_Detail($submissionBoxID);
$dueWorkDetail->processStudents($studentList);
$dueWorkDetail->renderDetails($this);


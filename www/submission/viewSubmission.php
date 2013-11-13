<?php

if (!isset($this)) {
    require_once dirname(__FILE__) . '/../../include/Bootstrap.php';
    SitePage::localRedir('/submission/viewSubmission');
}

// check the ID is valid
if (!is_numeric($this->arg[0])) {
    error(2);
}

$submissionBoxID = $this->arg[0];

$userHandler = new User();
$groupHandler = new Group();
$submissionHandler = new Submission();
$studentSubHandler = new SubmissionStudent();
$studentRetHandler = new SubmissionReturn();

$subBox = $submissionHandler->getByID($this->arg[0]);

// if you aren't the owner or a super, you aren't allowed to be here
if ($subBox['owner'] != $_SESSION['id'] && !$groupHandler->isSuperuser()) {
    error(17);
}

// we log the viewing of this page, I know not why
$submissionHandler->logPageView(3, $this->arg[0]);

// compile the list of students over which we will iterate
$studentList = array();

// get full student list of class to show those who have not submitted. If synergy code, get synergy students
if ($subBox['code'] && Cfg::get('external_enable')) {
    $synHandler = new External_Class();
    $studentListClassCode = $synHandler->getStudents($subBox['code']);
    if(is_array($studentListClassCode)){
        $studentList = array_merge($studentList,$studentListClassCode);
    }
}

// list all students from group membership
$studentListFromGroup = $groupHandler->getMemberList($subBox['category']);
if(is_array($studentListFromGroup)){
    $studentList = array_merge($studentList,$studentListFromGroup);
}

// list all students who have already submitted
$allSubmissions = $studentSubHandler->getByBoxID($this->arg[0]);
if (is_array($allSubmissions) && count($allSubmissions)) {
    foreach ($allSubmissions as $submission) {
        $studentList[] = array(
            'title' => $submission['title'],
            'firstname' => $submission['firstname'],
            'lastname' => $submission['lastname'],
            'uid' => $submission['uid'],
            'synergy_id' => $submission['synergy_id'],
            'type' => $submission['type'],
            'group_id' => $submission['group_id'],
        );
    }
}

// list all students who have been marked
$allReturns = $studentRetHandler->getByBoxID($this->arg[0]);
if(is_array($allReturns)){
    foreach ($allReturns as $return) {
        $studentList[] = array(
            'title' => $return['title'],
            'firstname' => $return['firstname'],
            'lastname' => $return['lastname'],
            'uid' => $return['uid'],
            'synergy_id' => $return['synergy_id'],
            'type' => $return['type'],
            'group_id' => $return['group_id'],
        );
    }
}

usort($studentList, function($a, $b){
        $last = strcmp($a['lastname'], $b['lastname']);
        if($last == 0) {
           return strcmp($a['firstname'],$b['firstname']);
        } else {
            return $last;
        }
});

$dueWorkDetail = new DueWork_Detail($submissionBoxID);
$dueWorkDetail->processStudents($studentList);
$dueWorkDetail->renderDetails($this);


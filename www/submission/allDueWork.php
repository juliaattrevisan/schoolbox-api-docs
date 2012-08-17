<?php

if(isset($_GET['year']) || isset($_GET['semester'])) {
    $this->localRedir("/submission/allDueWork/{$this->arg[0]}/{$_GET['year']}/{$_GET['semester']}");
}

$submissionHandler = new Submission();
$groupHandler = new Group();

/* ideally session children would use the id as the array key as this is ridiculous */
$childList = array();

if (is_array($_SESSION['children'])) {
    foreach ($_SESSION['children'] as $child) {
        $childList[] = $child['id'];
    }
}

// if I am a parent, default to the first child
if (is_array($_SESSION['children'])) {
    $userID = $_SESSION['children'][0]['id'];
}
// set the userID to self
else {
    //displaying users on due work, so log this as the last time they viewed due work
    $submissionHandler->logPageView(1);
    $submissionHandler->logPageView(2);
    $userID = $_SESSION['id'];
}

// if id specified, and this is my child
if (is_numeric($this->arg[0]) && in_array($this->arg[0], $childList)) {
    $userID = $this->arg[0];
}
// if id specified, and I am staff
else if (is_numeric($this->arg[0]) && $groupHandler->isStaff()) {
    $userID = $this->arg[0];
}

$currentYear = date('Y');
$currentSemester = date('m') > 6 ? 2 : 1;

if(isset($this->arg[1])) {
    if(!isset($this->arg[2])) {
        error(2);
    }
    elseif(!(is_numeric($this->arg[1]) && is_numeric($this->arg[2]))) {
        error(2); // TODO find a more appropriate error
    }

    if($this->arg[2] < 1 || $this->arg[2] > 2) {
        error(2); // TODO find a more appropriate error
    }

    $year = (int)$this->arg[1];
    $semester = (int)$this->arg[2];
}
else {
    $year = $currentYear;
    $semester = $currentSemester;
}

$dueWork = new DueWork_All($this, $userID);
$dueWork->output($year, $semester);

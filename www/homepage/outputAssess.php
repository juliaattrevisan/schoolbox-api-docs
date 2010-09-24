<?php
require_once $_SERVER['DOCUMENT_ROOT'] . '/../library/assess/assess.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/../library/assess/assess-attempt.php';

$this->setBlock($componentHandle, 'assess', 'assess_block');
$this->setBlock($componentHandle, 'createAssessment', 'createAssessment_block');
$this->setBlock('assess', 'teacherLink', 'teacherLink_block');
$this->setBlock('assess', 'studentLink', 'studentLink_block');

$assessHandler = new Assess();
$attemptHandler = new AssessAttempt();
$allAssess = $assessHandler->getList($this->arg[0]);

if ($groupHandler->isStaff()) {
    $this->setVar(array(
        'ASSESS_CATEGORY_ID' => $this->arg[0],
    ));

    $this->setVar('studentLink_block', '');
    $this->parse('teacherLink_block', 'teacherLink');
    $this->parse('createAssessment_block', 'createAssessment');
}
else {
    $this->setVar('teacherLink_block', '');
    $this->parse('studentLink_block', 'studentLink');
    $this->setVar('createAssessment_block', '');
}

if (is_array($allAssess)) {
    $i=0;

    foreach($allAssess as $currentAssess) {
        /* Check if you have completed an assessment already
         *
         * Set up Attempt vars
         */
        $attempt = $attemptHandler->getLatestForUser($currentAssess['assess_id'], $_SESSION['id']);
        $attemptStartTime = strtotime($attempt['start']);
        $attemptTimeRemaining = $attemptStartTime + $currentAssess['duration'] - time();
        $attemptEndTime = $attemptStartTime + $currentAssess['duration'];

        /* Redirect to the end if any of the following criteria are met:
         *
         * 1. The current attempt has already ended (by visiting the end page) AND when re-sits are not allowed
         * 2. The current attempt has expired due to a positive time limit reaching zero AND when assess[duration] is not zero
         */
        $cannotContinueAttempt = $attemptHandler->isComplete($attempt['attempt_id']) && ($currentAssess['allow_resit'] == 0);
        $durationInvalid = ($attemptTimeRemaining < 0) && ($currentAssess['duration'] != Assess::UNLIMITED_DURATION);

        if ($cannotContinueAttempt || $durationInvalid) {
            $this->setVar('studentLink_block', '');
        }
        else {
            $this->setVar(array(
                'ASSESS_ASSESS_ID' => $currentAssess['assess_id'],
                'ASSESS_TITLE' => $currentAssess['title'],
                'ASSESS_FINISH_DATE' => date('jS F Y, g:i:sa', strtotime($currentAssess['finish'])),
                'ASSESS_DESCRIPTION' => $currentAssess['description'],
                'ON_OFF' => $i%2 ? 'dark' : '',
            ));

            $this->parse('studentLink_block', 'studentLink');
        }

        if ($groupHandler->isStaff()) {
            $this->parse('teacherLink_block', 'teacherLink');
        }
        else {
            $this->setVar('teacherLink_block', '');
        }

        $this->parse('assess_block', 'assess', true);

        $i++;
    }
}
else {
    $this->setVar('assess_block', '');
}
?>
<?php
require_once $_SERVER['DOCUMENT_ROOT'] . '/../library/assess/assess.php';

$this->setBlock($componentHandle, 'assess', 'assess_block');
$this->setBlock($componentHandle, 'createAssessment', 'createAssessment_block');
$this->setBlock('assess', 'teacherLink', 'teacherLink_block');
$this->setBlock('assess', 'studentLink', 'studentLink_block');

$assessHandler = new Assess();
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
        $this->setVar(array(
            'ASSESS_ASSESS_ID' => $currentAssess['assess_id'],
            'ASSESS_TITLE' => $currentAssess['title'],
            'ASSESS_FINISH_DATE' => date('jS F Y, g:i:sa', strtotime($currentAssess['finish'])),
            'ASSESS_DESCRIPTION' => $currentAssess['description'],
            'ON_OFF' => $i%2 ? 'dark' : '',
        ));

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
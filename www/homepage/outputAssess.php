<?php
require_once $_SERVER['DOCUMENT_ROOT'] . '/../library/assess/assess.php';

$this->setBlock($componentHandle, 'assess', 'assess_block');
$this->setBlock($componentHandle, 'modifyHeader', 'modifyHeader_block');
$this->setBlock($componentHandle, 'createAssessment', 'createAssessment_block');
$this->setBlock('assess', 'modifyLink', 'modifyLink_block');

$assessHandler = new Assess();
$allAssess = $assessHandler->getList($this->arg[0]);

if ($groupHandler->isStaff()) {
    $this->setVar(array(
        'ASSESS_CATEGORY_ID' => $this->arg[0],
    ));

    $this->setVar('takeTestLink_block', '');
    $this->parse('modifyHeader_block', 'modifyHeader');
    $this->parse('createAssessment_block', 'createAssessment');
}
else {
    $this->parse('takeTestLink_block', 'takeTestLink');
    $this->setVar('modifyHeader_block', '');
    $this->setVar('createAssessment_block', '');
}

foreach($allAssess as $currentAssess) {
    $this->setVar(array(
        'ASSESS_ASSESS_ID' => $currentAssess['assess_id'],
        'ASSESS_TITLE' => $currentAssess['title'],
        'ASSESS_FINISH_DATE' => date('jS F Y, g:i:sa', strtotime($currentAssess['finish'])),
    ));

    if ($groupHandler->isStaff()) {
        $this->parse('modifyLink_block', 'modifyLink');
    }
    else {
        $this->setVar('modifyLink_block', '');
    }

    $this->parse('assess_block', 'assess', true);
}

?>
<?php
require_once('../library/assess/assess.php');
require_once('../library/assess/assess-attempt.php');

$assessHandler = new Assess();
$attemptHandler = new AssessAttempt();

$assess = $assessHandler->getByID($this->arg[0]);
$attempt = $attemptHandler->getByID($_SESSION['attempt_id']);

$this->setFile(TEMPLATE_content, 'assess/end.html');
$this->setBlock(TEMPLATE_content, 'endAssess', 'endAssess_block');
$this->setBlock(TEMPLATE_content, 'alreadyEnded', 'alreadyEnded_block');
$this->setTitle('Assessment: ' . $assess['title']);

/* TODO: Check that all questions are answered
 */

if ($attempt['finish'] == '0000-00-00 00:00:00') {
    $attemptHandler->update(array(
        'attempt_id' => $_SESSION['attempt_id'],
        'finish' => date('Y-m-d H:i:s', time()),
    ));

    $this->setVar(array(
        'ASSESS_TITLE' => $assess['title'],
        'ATTEMPT_END' => date('jS F Y, g:i:sa', time())
    ));

    $this->setVar('alreadyEnded_block', '');
    $this->parse('endAssess_block', 'endAssess');
}
else {
    $this->setVar(array(
        'ASSESS_TITLE' => $assess['title'],
        'ATTEMPT_END' => date('jS F Y, g:i:sa', strtotime($attempt['finish']))
    ));

    $this->parse('alreadyEnded_block', 'alreadyEnded');
    $this->setVar('endAssess_block', '');
}

?>


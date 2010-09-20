<?php
/**
 * Assessment completion. Marks when an assessment is finished.
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   2010 Alaress Pty Ltd
 * @link        http://www.alaress.com.au
 * @version     SVN: $Id$
 */
require_once('../library/assess/assess.php');
require_once('../library/assess/assess-attempt.php');
require_once('../library/assess/assess-question.php');
require_once('../library/assess/assess-response.php');

$assessHandler = new Assess();
$attemptHandler = new AssessAttempt();
$questionHandler = new AssessQuestion();
$responseHandler = new AssessResponse();

$assess = $assessHandler->getByID($this->arg[0]);
$attempt = $attemptHandler->getByID($_SESSION['attempt_id']);

$this->setFile(TEMPLATE_content, 'assess/end.html');
$this->setBlock(TEMPLATE_content, 'endAssess', 'endAssess_block');
$this->setBlock(TEMPLATE_content, 'alreadyEnded', 'alreadyEnded_block');
$this->setTitle('Assessment: ' . $assess['title']);

/* Check that all questions are answered
if ($responseHandler->numResponses($_SESSION['attempt_id']) != $questionHandler->numQuestions($this->arg[0])) {
    error(113);
}
 */

if (!$assess) {
    error(112);
}

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
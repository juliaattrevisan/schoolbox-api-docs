<?php
/**
 * Assessment completion. Marks when an assessment is finished.
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   2010 Alaress Pty Ltd
 * @link        http://www.alaress.com.au
 * @version     SVN: $Id$
 */
require_once('../library/survey/Survey.php');
require_once('../library/survey/SurveyAttempt.php');
require_once('../library/survey/SurveyQuestion.php');
require_once('../library/survey/SurveyResponse.php');

$assessHandler = new Survey();
$attemptHandler = new SurveyAttempt();
$questionHandler = new SurveyQuestion();
$responseHandler = new SurveyResponse();

if (!is_numeric($_SESSION['attempt_id'])) {
    SitePage::localRedir('/survey/intro/' . $this->arg[0]);
}

$assess = $assessHandler->getByID($this->arg[0]);
$attempt = $attemptHandler->getByID($_SESSION['attempt_id']);

if (!$assess) {
    error(112);
}

if ($assessHandler->isDeleted($this->arg[0])) {
	error(112);
}

/* Disabled: doing so will allow teachers to give their test a "dry run" and
 * they can delete their attempts afterwards

if ($assessHandler->isOwner($this->arg[0])) {
    error(124);
}
*/

if ($assessHandler->isExpired($this->arg[0])) {
    error(120);
}

$this->setFile(TEMPLATE_content, 'survey/end.html');
$this->setBlock(TEMPLATE_content, 'endAssess', 'endAssess_block');
$this->setBlock(TEMPLATE_content, 'alreadyEnded', 'alreadyEnded_block');
$this->setTitle('Assessment: ' . $assess['title']);

if ($attempt['finish'] == '0000-00-00 00:00:00') {
	$finishDate = new Alaress_Date_Format(time(), 'U');

    $attemptHandler->update(array(
        'attempt_id' => $_SESSION['attempt_id'],
        'finish' => $finishDate->getDatabase(),
    ));

	$attemptEndDate = new Alaress_Date_Format(time(), 'U');

    $this->setVar(array(
        'ASSESS_TITLE' => $assess['title'],
		'ASSESS_CATEGORY_ID' => $assess['category_id'],
        'ATTEMPT_END' => $attemptEndDate->getShort()
    ));

    $this->setVar('alreadyEnded_block', '');
    $this->parse('endAssess_block', 'endAssess');
}
else {
	$attemptEndDate = new Alaress_Date_Format(strtotime($attempt['finish']), 'U');

    $this->setVar(array(
        'ASSESS_TITLE' => $assess['title'],
		'ASSESS_CATEGORY_ID' => $assess['category_id'],
        'ATTEMPT_END' => $attemptEndDate->getShort()
    ));

    $this->parse('alreadyEnded_block', 'alreadyEnded');
    $this->setVar('endAssess_block', '');
}

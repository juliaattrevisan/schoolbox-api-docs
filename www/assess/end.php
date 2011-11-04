<?php
/**
 * Assessment completion. Marks when an assessment is finished.
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   2010 Alaress Pty Ltd
 * @link        http://www.alaress.com.au
 * @version     SVN: $Id$
 */
require_once('../library/assess/Assess.php');
require_once('../library/assess/AssessAttempt.php');
require_once('../library/assess/AssessQuestion.php');
require_once('../library/assess/AssessFactory.php');
require_once('../library/assess/AssessResponse.php');

$assessHandler = new Assess();
$attemptHandler = new AssessAttempt();
$questionHandler = new AssessQuestion();
$responseHandler = new AssessResponse();

if (!is_numeric($_SESSION['attempt_id'])) {
    SitePage::localRedir('/assess/intro/' . $this->arg[0]);
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

$this->setFile(TEMPLATE_content, 'assess/end.html');
$this->setBlock(TEMPLATE_content, 'endAssess', 'endAssess_block');
$this->setBlock(TEMPLATE_content, 'alreadyEnded', 'alreadyEnded_block');
$this->setTitle('Assessment: ' . $assess['title']);

if ($attempt['finish'] == '0000-00-00 00:00:00') {
	$attemptFinishDate = new Alaress_Date_Format(time(), 'U');

    $attemptHandler->update(array(
        'attempt_id' => $_SESSION['attempt_id'],
        'finish' => $attemptFinishDate->getDatabase(),
    ));

    $this->setVar(array(
        'ASSESS_TITLE' => $assess['title'],
		'ASSESS_CATEGORY_ID' => $assess['category_id'],
        'ATTEMPT_END' => $attemptFinishDate->getShort(),
    ));

    $this->setVar('alreadyEnded_block', '');
    $this->parse('endAssess_block', 'endAssess');
}
else {
	$attemptFinishDate = new Alaress_Date_Format(strtotime($attempt['finish']), 'U');

    $this->setVar(array(
        'ASSESS_TITLE' => $assess['title'],
		'ASSESS_CATEGORY_ID' => $assess['category_id'],
        'ATTEMPT_END' => $attemptFinishDate->getShort(),
    ));

    $this->parse('alreadyEnded_block', 'alreadyEnded');
    $this->setVar('endAssess_block', '');
}

/* Perform automated marking on responses which support this feature. Only
 * mark responses which haven't been automarked previously, which allows
 * teachers to customise response marks without being overwritten again.
 *
 * Once automated marking has been performed, remember which responses were
 * affected so we don't refetch them the next time the page loads.
 */
$allAttempts = $attemptHandler->getListByAssessAndAttempt($this->arg[0], $_SESSION['attempt_id']);

$factory = new AssessFactory();

if ($allAttempts) {
    foreach($allAttempts as $currentAttempt) {
        $allResponses = $responseHandler->getAutomarkableResponses($_SESSION['attempt_id']);

        if (is_array($allResponses)) {
            foreach ($allResponses as $currentResponse) {
                $answerHandler = $factory->createAnswerModel($currentResponse['type_id']);

                $answerHandler->mark($currentResponse);

                $markedResponseIDs[] = $currentResponse['response_id'];
            }
        }
    }
}

$responseHandler->updatedAutomatedMarks($markedResponseIDs);

/* Show mark for the attempt you just did
 */
$marks = $attemptHandler->getMarksForAttempt($_SESSION['attempt_id']);
$total = $attemptHandler->getTotalMarks($this->arg[0], $_SESSION['attempt_id']);
$percentage = 100*number_format($marks/$total, 2);
$numMarkedQuestions = count($allResponses);
$numUnmarkedQuestions = $responseHandler->numNotAutomarked($_SESSION['attempt_id']);
$numQuestions = $numMarkedQuestions + $numUnmarkedQuestions;
$valueUnmarkedQuestions = $responseHandler->valueNotAutomarked($_SESSION['attempt_id']);

$this->setVar(array(
	'ATTEMPT_MARKS' => $marks,
	'ATTEMPT_PERCENTAGE' => $percentage,
	'ATTEMPT_TOTAL' => $total,
	'ATTEMPT_NUM_MARKED_QUESTIONS' => $numMarkedQuestions,
	'ATTEMPT_NUM_UNMARKED_QUESTIONS' => $numUnmarkedQuestions,
	'ATTEMPT_NUM_QUESTIONS' => $numQuestions,
	'ATTEMPT_VALUE_UNMARKED_QUESTIONS' => $valueUnmarkedQuestions,
));
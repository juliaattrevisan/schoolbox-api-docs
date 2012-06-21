<?php
/**
 * Attempt creation. When someone makes an Attempt at an Assessment, record the
 * various details. This is loaded for each Section in the Assessment.
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   2010 Alaress Pty Ltd
 * @link        http://www.alaress.com.au
 * @version     SVN: $Id$
 */

$assessHandler = new Assess_Main();
$sectionHandler = new Assess_Section();
$questionHandler = new Assess_Question();
$factory = new Assess_Factory();
$attemptHandler = new Assess_Attempt();
$responseHandler = new Assess_Response();
$groupHandler = new Group();

if (!is_numeric($this->arg[0])) {
    error(112);
}

if (!is_numeric($this->arg[1])) {
    error(115);
}

$assess = $assessHandler->getByID($this->arg[0]);
$section = $sectionHandler->getByID($this->arg[1]);

if (!$assess) {
    error(112);
}

if ($assessHandler->isDeleted($this->arg[0])) {
	error(112);
}

if (!$section) {
    error(115);
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

if (!$assessHandler->canDoAssessment($this->arg[0])) {
    error(19);
}

if (!$questionHandler->numQuestions($this->arg[0])) {
	error(137);
}

/* Participants should be blocked from attempting if it hasn't started,
 * but authors should be able to test out their content
 */
if (!$assessHandler->isOpen($assess) && ($groupHandler->getPermission($assess['category_id']) < WRITE_ACCESS)) {
	error(143);
}

/* Check if this user has attempted this assess in the past and restore whatever
 * he was doing.
 *
 * As of 7/3/12, all attempts MUST be completed ie. you cannot start a new attempt
 * if you already have an incomplete one
 */
$latestResumable = $attemptHandler->getLatestResumableForUser($this->arg[0], $_SESSION['id']);

if ($latestResumable) {
	$_SESSION['attempt_id'] = $latestResumable['attempt_id'];

	$attempt = $latestResumable;
}
/* Otherwise make a new attempt and set the start time
 */
else if ($_SESSION['attempt_id'] == '') {
    $_SESSION['attempt_id'] = $attemptHandler->start($_SESSION['id'], $this->arg[0]);

	$attempt = $attemptHandler->getByID($_SESSION['attempt_id']);
}

/* Set up Attempt vars
 */
$attemptStartTime = strtotime($attempt['start']);
$attemptTimeRemaining = $attemptStartTime + $assess['duration'] - time();
$attemptEndTime = $attemptStartTime + $assess['duration'];

/* Tests can be undertaken if:
 *
 * 1. The assessment allows resit and the assessment hasn't expired
 * 2. The assessment is one-time-only, and you haven't completed an attempt, and have time to do it, and have not yet attempted
 */
if ($assess['allow_resit'] == 1) {
    if (!(($assess['duration'] == Assess_Main::UNLIMITED_DURATION) || (strtotime($assess['finish']) > time()))) {
        error(120);
    }
}
else {
    if ($attemptHandler->isComplete($assess['assess_id'])) {
        error(121);
    }
    else if (($attemptTimeRemaining < 0) && ($attemptStartTime > 0) && ($assess['duration'] != Assess_Main::UNLIMITED_DURATION)) {
        error(122);
    }
}

/* Set up template
 */
$this->setFile(TEMPLATE_content, 'assess/attempt.html');
$this->setBlock(TEMPLATE_content, 'answerInputs', 'answerInputs_block');
$this->setBlock(TEMPLATE_content, 'question', 'question_block');
$this->setBlock(TEMPLATE_content, 'nextSection', 'nextSection_block');
$this->setBlock(TEMPLATE_content, 'prevSection', 'prevSection_block');
$this->setBlock(TEMPLATE_content, 'endAssess', 'endAssess_block');
$this->setTitle('Assessment: ' . $assess['title']);

/* Show current Section's data, and other mandatory tokens needed to complete
 * the Assessment.
 */
$attemptStartDate = new Alaress_Date_Format($attemptStartTime, 'U');
$attemptEndDate = new Alaress_Date_Format($attemptEndTime, 'U');

$this->setVar(array(
    'ASSESS_ASSESS_ID' => $assess['assess_id'],
    'ASSESS_TITLE' => $assess['title'],
    'ASSESS_TIME_REMAINING' => $attemptTimeRemaining,
    'ATTEMPT_START' => $attemptStartDate->getShort(),
    'ATTEMPT_END' => $assess['duration'] == Assess_Main::UNLIMITED_DURATION ? 'No particular time' : $attemptEndDate->getShort(),
    'SECTION_TITLE' => $section['title'],
    'SECTION_DESCRIPTION' => $section['description'],
	'CURRENT_SECTION_SEQUENCE' => $section['sequence'],
));

/* Load all the questions for this section. This saves stubs for each response
 * and also the questions that were attempted, allowing an overview of which
 * questions were available during the attempt, even the randomly selected ones
 */
if (!$responseHandler->numStubResponsesInSection($_SESSION['attempt_id'], $section['section_id'])) {
    if ($section['random'] == 1) {
        $pool = $section['pool'];
        $randomise = Assess_Response::RANDOMISE_QUESTIONS;
    }
    else {
        $pool = 0;
        $randomise = Assess_Response::ORDERED_QUESTIONS;
    }

    $responseHandler->assignQuestions($_SESSION['attempt_id'], $section['section_id'], $pool, $randomise);
}

/* Load questions
 */
$allQuestions = $sectionHandler->getQuestions($_SESSION['attempt_id'], $section['section_id']);

/* Process questions now that we've got a list of 'em
 */
if ($allQuestions) {
    foreach($allQuestions as $currentQuestion) {
        if (!is_numeric($currentQuestion['type_id'])) {
            error(133);
        }

        $answerHandler = $factory->createAnswerModel($currentQuestion['type_id']);

        /* Load answer template into answerOption block
         */
        $this->setFile('answerFormFragment', 'assess/answer/' . $factory->getQuestionType($currentQuestion['type_id']) . '/answer.html');
        $this->setBlock('answerFormFragment', 'answerOption', 'answerOption_block');

        /* Reset answer options from previous iteration
         */
        $this->setVar('answerOption_block', '');

        $this->setVar(array(
            'QUESTION_QUESTION_ID' => $currentQuestion['question_id'],
            'QUESTION_SEQUENCE' => $currentQuestion['sequence'],
            'QUESTION_QUESTION' => $currentQuestion['question'],
            'QUESTION_MARKS' => $currentQuestion['marks'],
        ));

        /* Load individual answers
         */
        $answerHandler->prepareResponse($this, $currentQuestion['question_id']);

        $this->parse('answerInputs_block', 'answerFormFragment', true);
        $this->parse('question_block', 'question', true);

		/* Clear child blocks after parsing out the parent
		 */
		$answerHandler->cleanupParse($this);

        $this->setVar('answerFormFragment', '');
        $this->setVar('answerInputs_block', '');
    }
}
else {
    $this->setVar('question_block', '');
}

/* Find out how many questions were completed in this section already
 */
$this->setBlock(TEMPLATE_content, 'sectionPercentageComplete', 'sectionPercentageComplete_block');

$allPercentageComplete = $sectionHandler->sectionPercentageComplete($this->arg[1]);

foreach ($allPercentageComplete as $currentPercentageComplete) {
    $this->setVar(array(
        'SECTION_PERCENTAGE_COMPLETE_TITLE' => $currentPercentageComplete['title'],
        'SECTION_PERCENTAGE_COMPLETE_SECTION_ID' => $currentPercentageComplete['section_id'],
        'SECTION_PERCENTAGE_COMPLETE_PERCENTAGE_COMPLETE' => $currentPercentageComplete['percentageComplete'],
        'SECTION_SEQUENCE' => $currentPercentageComplete['sequence'],
    ));

    $this->parse('sectionPercentageComplete_block', 'sectionPercentageComplete', true);
}

/* Render Previous, Next and Finish buttons where appropriate
 */
$nextSection = $sectionHandler->getAfter($this->arg[0], $section['sequence']);
$prevSection = $sectionHandler->getBefore($this->arg[0], $section['sequence']);

/* If there is no next section and no previous section, this was a one-section
 * assessment and should only have the Finish button
 */
if (!is_numeric($nextSection['section_id']) && !is_numeric($prevSection['section_id'])) {
    $this->setVar('nextSection_block', '');
    $this->setVar('prevSection_block', '');
    $this->parse('endAssess_block', 'endAssess');
}
/* If there is no next section, then provide the finish link and parse out the
 * previous button if applicable
 */
else {
    if (is_numeric($nextSection['section_id'])) {
        $this->setVar(array(
            'NEXT_SECTION_ID' => $nextSection['section_id'],
        ));

        $this->parse('nextSection_block', 'nextSection');
        $this->setVar('endAssess_block', '');
    }
    else {
        $this->setVar('nextSection_block', '');
        $this->parse('endAssess_block', 'endAssess');
    }

    /* Conditions where the previous button is available
     */
    if (is_numeric($prevSection['section_id'])) {
        $this->setVar(array(
            'PREV_SECTION_ID' => $prevSection['section_id'],
        ));

        $this->parse('prevSection_block', 'prevSection');
    }
    else {
        $this->setVar('prevSection_block', '');
    }
}

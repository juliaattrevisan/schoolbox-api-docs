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

$assessHandler = new Survey_Main();
$sectionHandler = new Survey_Section();
$questionHandler = new Survey_Question();
$factory = new Survey_Factory();
$attemptHandler = new Survey_Attempt();
$responseHandler = new Survey_Response();
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

if ($assessHandler->isDeleted($assess['assess_id'])) {
    error(112);
}

if (!$section) {
    error(115);
}

/* Check if you are in this category, so you can't submit other category's
 * online assessments unless you are a part of that category.
 */
if (!$assessHandler->canDoAssessment($assess['assess_id'])) {
    error(19);
}

/* Check if this assessment can be started
 */
if ($assessHandler->isExpired($assess['assess_id'])) {
    error(120);
}

/* Participants should be blocked from attempting if it hasn't started,
 * but authors should be able to test out their content
 */
if (!$assessHandler->isOpen($assess) && ($groupHandler->getPermission($assess['category_id']) < WRITE_ACCESS)) {
    error(144);
}

if (!$questionHandler->numQuestions($assess['assess_id'])) {
    error(137);
}

/* Disabled: doing so will allow teachers to give their test a "dry run" and
 * they can delete their attempts afterwards

if ($assessHandler->isOwner($this->arg[0])) {
    error(124);
}
*/

/* Check if you have completed an assessment already
 *
 * Set up Attempt vars
 */
$numAttempts = $attemptHandler->userHasAttempted($assess['assess_id'], $_SESSION['id']);

$attempt = $attemptHandler->getLatestResumableForUser($assess['assess_id'], $_SESSION['id']);
$attemptStartTime     = ($attempt['start'] ? strtotime($attempt['start']) : $_SERVER['REQUEST_TIME']);
$attemptTimeRemaining = $attemptStartTime + $assess['duration'] - $_SERVER['REQUEST_TIME'];
$attemptEndTime = $attemptStartTime + $assess['duration'];

/* If there is a resumable attempt, resume it.
 */
if ($attempt) {
    $_SESSION['attempt_id'] = $attempt['attempt_id'];
}

/* Redirect back to the homepage if you've reached the end, or had
 * your attempt_id cleared from the session
 *
 * This has to come after the $attempt check, otherwise SB-4199 happens.
 * You can log out, lose your attempt_id in the session, intend to resume
 * after logging back in, only to be redirected back to the homepage because
 * we haven't yet checked for a resumable attempt
 *
 * This should only redirect you back to the homepage if you have no resumable
 * attempts waiting for you to complete and you don't have an attempt_id
 */
if (!is_numeric($_SESSION['attempt_id'])) {
	SitePage::localRedir('/homepage/' . $assess['category_id']);
}

/* Check if Attempt ran out of time
 */
if (($attemptTimeRemaining < 0) && ($attemptStartTime > 0) && ($assess['duration'] != Assess_Main::UNLIMITED_DURATION)) {
    error(122);
}

/* Set up template
 */
$this->setFile(TEMPLATE_content, 'survey/attempt.html');
$this->setBlock(TEMPLATE_content, 'answerInputs', 'answerInputs_block');
$this->setBlock(TEMPLATE_content, 'question', 'question_block');
$this->setBlock(TEMPLATE_content, 'nextSection', 'nextSection_block');
$this->setBlock(TEMPLATE_content, 'prevSection', 'prevSection_block');
$this->setBlock(TEMPLATE_content, 'endAssess', 'endAssess_block');
$this->setTitle('Assessment: ' . $assess['title']);

/* Show current Section's data, and other mandatory tokens needed to complete
 * the Assessment.
 */
$attemptStart = new Alaress_Date_Format($attemptStartTime, 'U');
$attemptEnd = new Alaress_Date_Format($attemptEndTime, 'U');

$this->setVar(array(
	'ASSESS_ASSESS_ID' => $assess['assess_id'],
	'ASSESS_TITLE' => Scrub::text($assess['title']),
	'ASSESS_TIME_REMAINING' => $attemptTimeRemaining,
	'ATTEMPT_START' => $attemptStart->getShort(),
	'ATTEMPT_END' => $assess['duration'] == Survey_Main::UNLIMITED_DURATION ? 'No particular time' : $attemptEnd->getShort(),
	'SECTION_TITLE' => Scrub::text($section['title']),
	'SECTION_DESCRIPTION' => Scrub::html($section['description']),
	'CURRENT_SECTION_SEQUENCE' => $section['sequence'],
));

/* Load questions
 */
$allQuestions = $sectionHandler->getQuestions($_SESSION['attempt_id'], $section['section_id']);

/* Process questions now that we've got a list of 'em
 */
if ($allQuestions) {
    foreach ($allQuestions as $currentQuestion) {

        /* Ignore questions that don't exist, or cannot be determined
         * This formerly used to error() critically whenever type_id
         * didn't save properly for whatever reason
         *
         * Unfortunately that also occurred whenever a section has no
         * questions in it - so instead of fatally dying, just ignore
         * and continue
         */
        if (!is_numeric($currentQuestion['type_id'])) {
            $this->setVar(array(
                    'question_block' => ''
                ));

            continue;
        }

        $answerHandler = $factory->createAnswerModel($currentQuestion['type_id']);

        /* Load answer template into answerOption block
         */
        $path = 'survey/answer/' . $factory->getQuestionType($currentQuestion['type_id']) . '/answer.html';

        $this->setFile('answerFormFragment', $path);
        $this->setBlock('answerFormFragment', 'answerOption', 'answerOption_block');

        /* Reset answer options from previous iteration
         */
        $this->setVar('answerOption_block', '');

		/* Load question configuration to detect Mandatoryness
		 */
		$configuration = json_decode($currentQuestion['config']);

        $this->setVar(array(
			'QUESTION_QUESTION_ID' => $currentQuestion['question_id'],
			'QUESTION_SEQUENCE' => $currentQuestion['sequence'],
			'QUESTION_QUESTION' => Scrub::html($currentQuestion['question']),
			'QUESTION_MARKS' => $currentQuestion['marks'],
			'QUESTION_MANDATORY' => $configuration->mandatory ? '* This question is required' : ''
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
} else {
    $this->setVar('question_block', '');
}

/* Find out how many questions were completed in this section already
 */
$this->setBlock(TEMPLATE_content, 'sectionPercentageComplete', 'sectionPercentageComplete_block');

$allPercentageComplete = $sectionHandler->sectionPercentageComplete($this->arg[1]);

foreach ($allPercentageComplete as $currentPercentageComplete) {
    $this->setVar(
        array(
            'SECTION_PERCENTAGE_COMPLETE_TITLE' => $currentPercentageComplete['title'],
            'SECTION_PERCENTAGE_COMPLETE_SECTION_ID' => $currentPercentageComplete['section_id'],
            'SECTION_PERCENTAGE_COMPLETE_PERCENTAGE_COMPLETE' => $currentPercentageComplete['percentageComplete'],
            'SECTION_SEQUENCE' => $currentPercentageComplete['sequence'],
        )
    );

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
} /* If there is no next section, then provide the finish link and parse out the
 * previous button if applicable
 */
else {
    if (is_numeric($nextSection['section_id'])) {
        $this->setVar(
            array(
                'NEXT_SECTION_ID' => $nextSection['section_id'],
            )
        );

        $this->parse('nextSection_block', 'nextSection');
        $this->setVar('endAssess_block', '');
    } else {
        $this->setVar('nextSection_block', '');
        $this->parse('endAssess_block', 'endAssess');
    }

    /* Conditions where the previous button is available
     */
    if (is_numeric($prevSection['section_id'])) {
        $this->setVar(
            array(
                'PREV_SECTION_ID' => $prevSection['section_id'],
            )
        );

        $this->parse('prevSection_block', 'prevSection');
    } else {
        $this->setVar('prevSection_block', '');
    }
}

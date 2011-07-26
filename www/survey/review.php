<?php
/**
 * Review assessment. The marks for each submitted question can be modified here
 * but the actual answers cannot be changed, only seen. Structurally, this is
 * similar to the normal undertaking of the test, only that answers for the
 * specified attempt are shown and you can only change the value of marks.
 *
 * You can add extra marks beyond the specified maximum for bonus answers, and
 * half marks are acceptable.
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   2010 Alaress Pty Ltd
 * @link        http://www.alaress.com.au
 * @version     SVN: $Id$
 */
require_once('../library/assess/assess.php');
require_once('../library/assess/assess-factory.php');
require_once('../library/assess/assess-section.php');
require_once('../library/assess/assess-attempt.php');

$factory = new AssessFactory();
$assessHandler = new Assess();
$attemptHandler = new AssessAttempt();
$responseHandler = new AssessResponse();
$sectionHandler = new AssessSection();

if (!is_numeric($this->arg[0])) {
    error(112);
}

if (!is_numeric($this->arg[1])) {
    error(115);
}

if (!is_numeric($this->arg[2])) {
    error(128);
}

$assess = $assessHandler->getByID($this->arg[0]);
$section = $sectionHandler->getByID($this->arg[1]);
$attempt = $attemptHandler->getByID($this->arg[2]);

if (!$assess) {
    error(112);
}

if (!$section) {
    error(115);
}

if (!$attempt) {
    error(128);
}

if ($assessHandler->isDeleted($this->arg[0])) {
	error(112);
}

if (!$assessHandler->isOwner($this->arg[0])) {
    error(127);
}

/* Find out which attempt was made
 */
if (is_numeric($this->arg[2])) {
    $_SESSION['attempt_id'] = $this->arg[2];
}
else {
    error(114);
}

/* Set up template
 */
$this->setFile(TEMPLATE_content, 'assess/review.html');
$this->setBlock(TEMPLATE_content, 'answerInputs', 'answerInputs_block');
$this->setBlock(TEMPLATE_content, 'question', 'question_block');
$this->setBlock(TEMPLATE_content, 'nextSection', 'nextSection_block');
$this->setBlock(TEMPLATE_content, 'prevSection', 'prevSection_block');
$this->setBlock(TEMPLATE_content, 'endAssess', 'endAssess_block');
$this->setTitle('Assessment: ' . $assess['title']);

/* Show current Section's data, and other mandatory tokens needed to complete
 * the Assessment.
 */
$this->setVar(array(
    'ASSESS_ASSESS_ID' => $assess['assess_id'],
    'ASSESS_TITLE' => $assess['title'],
    'ATTEMPT_ATTEMPT_ID' => $_SESSION['attempt_id'],
    'SECTION_TITLE' => $section['title'],
    'SECTION_DESCRIPTION' => $section['description'],
));

/* Load all the questions for this section.
 */
$allQuestions = $sectionHandler->getContents($this->arg[0], $this->arg[1], AssessSection::SECTION_EXCLUDE);

if ($allQuestions) {
    foreach($allQuestions as $currentQuestion) {
        $answerHandler = $factory->createAnswerModel($currentQuestion['type_id']);

        /* Load answer template into answerOption block
         */
        $this->setFile('answerFormFragment', 'assess/answer/' . $factory->getQuestionType($currentQuestion['type_id']) . '/review.html');
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
        $answerHandler->reviewResponse($this, $currentQuestion['question_id']);

        $this->parse('answerInputs_block', 'answerFormFragment', true);
        $this->parse('question_block', 'question', true);

        $this->setVar('answerFormFragment', '');
        $this->setVar('answerInputs_block', '');
    }
}
else {
    $this->setVar('question_block', '');
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
?>
<?php
require_once('../library/assess/assess.php');
require_once('../library/assess/assess-factory.php');
require_once('../library/assess/assess-section.php');
require_once('../library/assess/assess-attempt.php');

$factory = new AssessFactory();
$assessHandler = new Assess();
$attemptHandler = new AssessAttempt();
$responseHandler = new AssessResponse();
$sectionHandler = new AssessSection();

/* TODO: Error checking for handlers
 */

$assess = $assessHandler->getByID($this->arg[0]);
$section = $sectionHandler->getByID($this->arg[1]);

/* Set the start time if not already done
 */
if ($_SESSION['attempt_id'] == '') {
    $_SESSION['attempt_id'] = $attemptHandler->start($_SESSION['id'], $this->arg[0]);
}

/* Set up Attempt vars
 */
$attempt = $attemptHandler->getByID($_SESSION['attempt_id']);
$attemptStartTime = strtotime($attempt['start']);
$attemptTimeRemaining = $attemptStartTime + $assess['duration'] - time();
$attemptEndTime = $attemptStartTime + $assess['duration'];

/* Check if you have previously completed this test, and also check if this test
 * has already expired; if either is the case, redirect to end screen and force
 * test completion.
 */
if ($attemptHandler->isComplete($this->arg[0]) || ($attemptTimeRemaining <= 0)) {
    localRedir('/assess/end/' . $this->arg[0]);
    exit();
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
$this->setVar(array(
    'ASSESS_ASSESS_ID' => $assess['assess_id'],
    'ASSESS_TITLE' => $assess['title'],
    'ASSESS_TIME_REMAINING' => $attemptTimeRemaining,
    'ATTEMPT_START' => date('jS F Y, g:i:sa', $attemptStartTime),
    'ATTEMPT_END' => date('jS F Y, g:i:sa', $attemptEndTime),
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
        $this->setFile('answerFormFragment', 'assess/answer/' . $factory->getQuestionType($currentQuestion['type_id']) . '/answer.html');
        $this->setBlock('answerFormFragment', 'answerOption', 'answerOption_block');

        /* Reset answer options from previous iteration
         */
        $this->setVar('answerOption_block', '');

        $this->setVar(array(
            'QUESTION_QUESTION_ID' => $currentQuestion['question_id'],
            'QUESTION_SEQUENCE' => $currentQuestion['sequence'],
            'QUESTION_QUESTION' => $currentQuestion['question'],
        ));

        /* Load individual answers
         */
        $answerHandler->prepareResponse($this, $currentQuestion['question_id']);

        $this->parse('answerInputs_block', 'answerFormFragment', true);
        $this->parse('question_block', 'question', true);

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
?>
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

$factory = new Survey_Factory();
$surveyHandler = new Survey_Main();
$attemptHandler = new Survey_Attempt();
$responseHandler = new Survey_Response();
$sectionHandler = new Survey_Section();

if (!is_numeric($this->arg[0])) {
    error(112);
}

if (!is_numeric($this->arg[1])) {
    error(115);
}

if (!is_numeric($this->arg[2])) {
    error(128);
}

$survey = $surveyHandler->getByID($this->arg[0]);
$section = $sectionHandler->getByID($this->arg[1]);
$attempt = $attemptHandler->getByID($this->arg[2]);

if (!$survey) {
    error(112);
}

if ($surveyHandler->isDeleted($this->arg[0])) {
    error(112);
}

if (!$section) {
    error(115);
}

if (!$attempt) {
    error(128);
}

if (!$attemptHandler->isOwner($this->arg[2])) {
    error(129);
}

/* Find out which attempt was made
 */
if (is_numeric($this->arg[2])) {
    $_SESSION['attempt_id'] = $this->arg[2];
} else {
    error(114);
}

/* Set up template
 */
$this->setFile(TEMPLATE_content, 'survey/studentReview.html');
$this->setBlock(TEMPLATE_content, 'answerInputs', 'answerInputs_block');
$this->setBlock(TEMPLATE_content, 'question', 'question_block');
$this->setBlock(TEMPLATE_content, 'nextSection', 'nextSection_block');
$this->setBlock(TEMPLATE_content, 'prevSection', 'prevSection_block');
$this->setTitle('Survey: ' . $survey['title']);

/* Show current Section's data, and other mandatory tokens needed to complete
 * the survey.
 */
$openDate      = new Alaress_Date_Format($assess['start'], Alaress_Date_Format::DATABASE_FORMAT);
$dueDate       = new Alaress_Date_Format($assess['finish'], Alaress_Date_Format::DATABASE_FORMAT);
$attemptDate   = new Alaress_Date_Format($attempt['start'], Alaress_Date_Format::DATABASE_FORMAT);
$attemptFinish = new Alaress_Date_Format($attempt['finish'], Alaress_Date_Format::DATABASE_FORMAT);
$this->setVar(array(
    'ASSESS_ASSESS_ID' => $survey['assess_id'],
    'ASSESS_TITLE' => Scrub::text($survey['title']),
    'ASSESS_CATEGORY_ID' => $survey['category_id'],
    'ASSESS_START'                  => $openDate->getShort(),
    'ASSESS_FINISH'                 => $dueDate->getShort(),
    'ATTEMPT_ATTEMPT_ID'            => $_SESSION['attempt_id'],
    'ATTEMPT_START'                 => $attemptDate->getShort(),
    'ATTEMPT_FINISH'                => $attemptFinish->getShort(),
    'ATTEMPT_TIME_TAKEN'            => $surveyHandler->sec2hms($attemptFinish->diffInSeconds($attemptDate, true)),
    'SECTION_TITLE' => Scrub::text($section['title']),
    'SECTION_DESCRIPTION' => Scrub::html($section['description']),
));

/* Load all the questions for this section.
 * Note: only show the randomly-selected ones
 */
$allQuestions = $sectionHandler->getQuestions($_SESSION['attempt_id'], $section['section_id']);

if ($allQuestions) {
    $randomQuestionNumber = 1;

    foreach ($allQuestions as $currentQuestion) {
        $answerHandler = $factory->createAnswerModel($currentQuestion['type_id']);

        /* Load answer template into answerOption block
         */
        $this->setFile('answerFormFragment', 'survey/answer/' . $factory->getQuestionType($currentQuestion['type_id']) . '/studentReview.html');
        $this->setBlock('answerFormFragment', 'answerOption', 'answerOption_block');

        /* Reset answer options from previous iteration
         */
        $this->setVar('answerOption_block', '');

        $this->setVar(array(
            'QUESTION_QUESTION_ID' => $currentQuestion['question_id'],
            'QUESTION_SEQUENCE' => $currentQuestion['sequence'],
            'QUESTION_QUESTION' => Scrub::html($currentQuestion['question']),
        ));

        /* If this is a randomised section, number each question incrementally
         */
        if ($section['random'] == 1) {
            $this->setVar(array(
                'QUESTION_SEQUENCE' => $randomQuestionNumber
            ));
        }

        /* Load individual answers
         */
        $answerHandler->reviewResponse($this, $currentQuestion['question_id']);

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
    }
	else {
        $this->setVar('nextSection_block', '');
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

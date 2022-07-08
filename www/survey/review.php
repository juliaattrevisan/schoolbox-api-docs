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
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

use Schoolbox\Permission\Access\UserAccess;

$factory         = new Survey_Factory();
$surveyHandler   = new Survey_Main();
$attemptHandler  = new Survey_Attempt();
$responseHandler = new Survey_Response();
$sectionHandler  = new Survey_Section();
$groupHandler    = new Group();
$userHandler     = new User();

/** @var SitePage $this */
if (!is_numeric($this->arg[0])) {
    error(112);
}

if (!is_numeric($this->arg[1])) {
    error(115);
}

if (!is_numeric($this->arg[2])) {
    error(128);
}

if (!$surveyHandler->isValidUrlAccess($this->arg[0])) {
    $this->pageNotFound();
}

$survey = $surveyHandler->getByID($this->arg[0]);
$section = $sectionHandler->getByID($this->arg[1]);
$attempt = $attemptHandler->getByID($this->arg[2]);

if (!$section) {
    error(115);
}

if (!$attempt) {
    error(128);
}

if ($surveyHandler->isDeleted($this->arg[0])) {
    error(112);
}

if (
    !$surveyHandler->isOwner($survey['assess_id']) && ($groupHandler->getPermission(
        $survey['category_id']
    ) < UserAccess::ACCESS_WRITE)
) {
    error(127);
}

/* Find out which attempt was made
 */
if (is_numeric($this->arg[2])) {
    $attemptId = $this->arg[2];
} else {
    error(114);
}

/* Find out whether the attempt has been previously finalised
 */
if ($attempt['reviewed_at'] != '') {
    $reviewedAt = 'true';
} else {
    $reviewedAt = 'false';
}


/* Set up template
 */
$this->setFile(TEMPLATE_content, 'survey/review.html');
$this->setBlock(TEMPLATE_content, 'attemptFinished', 'attemptFinished_block');
$this->setBlock(TEMPLATE_content, 'attemptNotYetFinished', 'attemptNotYetFinished_block');
$this->setBlock(TEMPLATE_content, 'attemptUnfinished', 'attemptUnfinished_block');
$this->setBlock(TEMPLATE_content, 'answerInputs', 'answerInputs_block');
$this->setBlock(TEMPLATE_content, 'question', 'question_block');
$this->setBlock(TEMPLATE_content, 'nextSection', 'nextSection_block');
$this->setBlock(TEMPLATE_content, 'prevSection', 'prevSection_block');
$this->setBlock(TEMPLATE_content, 'endAssess', 'endAssess_block');
$this->setTitle('Assessment: ' . Scrub::text($survey['title']));

try {
    $folder = Schoolbox\Resource\Folder\Mapper::getInstance()->findById($survey['category_id']);
} catch (Schoolbox\Mapper\Exception $e) {
    error(112);
    exit;
}
$openDate = new Alaress_Date_Format($survey['start'], Alaress_Date_Format::DATABASE_FORMAT);
$dueDate  = new Alaress_Date_Format($survey['finish'], Alaress_Date_Format::DATABASE_FORMAT);
$start = new Alaress_Date_Format($attempt['start'], Alaress_Date_Format::DATABASE_FORMAT);
$finish = new Alaress_Date_Format($attempt['finish'], Alaress_Date_Format::DATABASE_FORMAT);
$user = $attemptHandler->getUser($attempt['attempt_id']);

/* Show current Section's data, and other mandatory tokens needed to complete
 * the Assessment.
 */
$this->setVar(
    array(
        'ASSESS_ASSESS_ID'            => $survey['assess_id'],
        'ASSESS_TITLE'                => Scrub::text($survey['title']),
        'ASSESS_START'                => $openDate->getShort(),
        'ASSESS_FINISH'               => $dueDate->getShort(),
        'ATTEMPT_ATTEMPT_ID'          => $attemptId,
        'ATTEMPT_START'               => $start->getShort(),
        'ATTEMPT_FINISH'              => $finish->getShort(),
        'ATTEMPT_TIME_TAKEN'          => $surveyHandler->sec2hms($finish->diffInSeconds($start, true)),
        'SECTION_TITLE'               => Scrub::text($section['title']),
        'SECTION_DESCRIPTION'         => Scrub::html($section['description']),
        'ASSESS_PREVIOUSLY_REVIEWED'  => $reviewedAt,
        'RESPONDENT_NAME'             => $survey['anonymous_result'] ? 'Anonymous' : $userHandler->formatName($user),
        'ASSESS_CATEGORY_NAME'        => Scrub::text($folder->getName()),
        'ASSESS_CATEGORY_ID'          => $folder->getId(),
        'attemptFinished_block'       => '',
        'attemptNotYetFinished_block' => '',
        'attemptUnfinished_block'     => '',
    )
);

// Parse finished/unfinished/not finished yet blockage
// Finished: $attempt.finish is set to something other that 0000-00-etc
// Otherwise:
// not yet finished if survey is open, unfinished if closed
if (!isset($attempt['finish'])) {
    $close = new DateTime($survey['finish']);
    if ($close->getTimestamp() < $_SERVER['REQUEST_TIME']) {
        $this->parse('attemptUnfinished_block', 'attemptUnfinished');
    } else {
        $this->parse('attemptNotYetFinished_block', 'attemptNotYetFinished');
    }
} else {
    $this->parse('attemptFinished_block', 'attemptFinished');
}


/* Load all the questions for this section.
 * Note: only show the randomly-selected ones
 */
$allQuestions = $sectionHandler->getQuestions($attemptId, $section['section_id']);

if ($allQuestions) {
    foreach ($allQuestions as $currentQuestion) {
        $answerHandler = $factory->createAnswerModel($currentQuestion['type_id']);

        /* Load answer template into answerOption block
         */
        $this->setFile(
            'answerFormFragment',
            'survey/answer/' . $factory->getQuestionType($currentQuestion['type_id']) . '/review.html'
        );
        $this->setBlock('answerFormFragment', 'answerOption', 'answerOption_block');

        /* Reset answer options from previous iteration
         */
        $this->setVar('answerOption_block', '');

        $this->setVar(
            array(
                'QUESTION_QUESTION_ID' => $currentQuestion['question_id'],
                'QUESTION_SEQUENCE' => $currentQuestion['sequence'],
                'QUESTION_QUESTION' => Scrub::html($currentQuestion['question']),
                'QUESTION_MARKS' => $currentQuestion['marks'],
            )
        );

        /* Load individual answers
         */
        $answerHandler->reviewResponse($this, $attemptId, $currentQuestion['question_id']);

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

/* Render Previous, Next and Finish buttons where appropriate
 */
$nextSection = $sectionHandler->getAfter($this->arg[0], $section['sequence']);
$prevSection = $sectionHandler->getBefore($this->arg[0], $section['sequence']);

/* If there is no next section and no previous section, this was a one-section
 * assessment and should only have the Finish button
 */
if (!$nextSection && !$prevSection) {
    $this->setVar('nextSection_block', '');
    $this->setVar('prevSection_block', '');
    $this->parse('endAssess_block', 'endAssess');
} else {
    /* If there is no next section, then provide the finish link and parse out the
     * previous button if applicable
     */
    if ($nextSection) {
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
    if ($prevSection) {
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

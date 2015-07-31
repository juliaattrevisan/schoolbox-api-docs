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

$factory = new Assess_Factory();
$assessHandler = new Assess_Main();
$attemptHandler = new Assess_Attempt();
$responseHandler = new Assess_Response();
$sectionHandler = new Assess_Section();
$groupHandler = new Group();
$userHandler = new User();

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
// getListByAssessAndAttempt gets a rowset for some reason!
$attemptList = $attemptHandler->getListByAssessAndAttempt($this->arg[0], $this->arg[2]);
$attempt     = array_pop($attemptList);

if (!$assess) {
    error(112);
}

if (!$section) {
    error(115);
}

if (!$attempt) {
    error(128);
} else {
    $attempt['totalAvailableMarks'] = $attemptHandler->getTotalMarks($this->arg[0], $this->arg[2]);
}

if ($assessHandler->isDeleted($this->arg[0])) {
    error(112);
}

if (!$assessHandler->isOwner($this->arg[0]) && ($groupHandler->getPermission($assess['category_id']) < WRITE_ACCESS)) {
    error(127);
}

/* Find out which attempt was made
 */
if (is_numeric($this->arg[2])) {
    $_SESSION['attempt_id'] = $this->arg[2];
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

$user = $userHandler->getByID($attempt['user_id']);

/* Set up template
 */
$this->setFile(TEMPLATE_content, 'assess/review.html');
$this->setBlock(TEMPLATE_content, 'attemptFinished', 'attemptFinished_block');
$this->setBlock(TEMPLATE_content, 'attemptNotYetFinished', 'attemptNotYetFinished_block');
$this->setBlock(TEMPLATE_content, 'attemptUnfinished', 'attemptUnfinished_block');
$this->setBlock(TEMPLATE_content, 'resubmit_label', 'resubmit_label_block');
$this->setBlock(TEMPLATE_content, 'visible_staff', 'visible_staff_block');
$this->setBlock(TEMPLATE_content, 'visible_staff_students', 'visible_staff_students_block');
$this->setBlock(TEMPLATE_content, 'visible_staff_students_parents', 'visible_staff_students_parents_block');
$this->setBlock(TEMPLATE_content, 'answerInputs', 'answerInputs_block');
$this->setBlock(TEMPLATE_content, 'question', 'question_block');
$this->setBlock(TEMPLATE_content, 'nextSection', 'nextSection_block');
$this->setBlock(TEMPLATE_content, 'prevSection', 'prevSection_block');
$this->setBlock(TEMPLATE_content, 'endAssess', 'endAssess_block');
$this->setBlock('endAssess', 'writable_finalise', 'writable_finalise_block');
$this->setBlock('endAssess', 'writable_resubmit', 'writable_resubmit_block');
$this->setTitle('Assessment: ' . $assess['title']);

/* Show current Section's data, and other mandatory tokens needed to complete
 * the Assessment.
 */
try {
    $folder = Schoolbox\Resource\Folder\Mapper::getInstance()->findById($assess['category_id']);
} catch (Schoolbox\Mapper\Exception $e) {
    error(112);
    exit;
}
$openDate      = new Alaress_Date_Format($assess['start'], Alaress_Date_Format::DATABASE_FORMAT);
$dueDate       = new Alaress_Date_Format($assess['finish'], Alaress_Date_Format::DATABASE_FORMAT);
$attemptDate   = new Alaress_Date_Format($attempt['start'], Alaress_Date_Format::DATABASE_FORMAT);
$attemptFinish = new Alaress_Date_Format($attempt['finish'], Alaress_Date_Format::DATABASE_FORMAT);
$this->setVar(
    array(
        'ASSESS_ASSESS_ID'                     => $assess['assess_id'],
        'ASSESS_TITLE'                         => Scrub::text($assess['title']),
        'ASSESS_START'                         => $openDate->getShort(),
        'ASSESS_FINISH'                        => $dueDate->getShort(),
        'ATTEMPT_ATTEMPT_ID'                   => $_SESSION['attempt_id'],
        'ATTEMPT_START'                        => $attemptDate->getShort(),
        'ATTEMPT_FINISH'                       => $attemptFinish->getShort(),
        'ATTEMPT_TIME_TAKEN'                   => $assessHandler->sec2hms($attemptFinish->diffInSeconds($attemptDate, true)),
        'ATTEMPT_TOTAL_MARKS'                  => number_format($attempt['totalMarks'], 2),
        'ATTEMPT_TOTAL_AVAILABLE_MARKS'        => number_format($attempt['totalAvailableMarks'], 2),
        'SECTION_TITLE'                        => Scrub::text($section['title']),
        'SECTION_DESCRIPTION'                  => Scrub::html($section['description']),
        'ASSESS_PREVIOUSLY_REVIEWED'           => $reviewedAt,
        'RESPONDENT_NAME'                      => $userHandler->formatName($user),
        'ASSESS_CATEGORY_NAME'                 => Scrub::text($folder->getName()),
        'ASSESS_CATEGORY_ID'                   => $folder->getId(),
        'ASSESS_DUEWORK_YEAR'                  => $dueDate->getYear(),
        'ASSESS_DUEWORK_SEMESTER'              => $dueDate->getYearHalf(),
        'resubmit_label_block'                 => '',
        'visible_staff_block'                  => '',
        'visible_staff_students_block'         => '',
        'visible_staff_students_parents_block' => '',
        'attemptFinished_block'                => '',
        'attemptNotYetFinished_block'          => '',
        'attemptUnfinished_block'              => '',
    )
);

// Parse finished/unfinished/not finished yet blockage
// Finished: $attempt.finish is set to something other that 0000-00-etc
// Otherwise:
// not yet finished if survey is open, unfinished if closed
if (!isset($attempt['finish'])) {
    $close = new DateTime($assess['finish']);
    if ($close->getTimestamp() < $_SERVER['REQUEST_TIME']) {
        $this->parse('attemptUnfinished_block', 'attemptUnfinished');
    }
    else {
        $this->parse('attemptNotYetFinished_block', 'attemptNotYetFinished');
    }
}
else {
    $this->parse('attemptFinished_block', 'attemptFinished');
}

if ($attempt['reviewed_at'] != '') {
    $this->parse('resubmit_label_block', 'resubmit_label');
}

switch ($assess['mark_visibility']) {
    case Schoolbox\Assessment\Component\SubmissionBox::MARK_VISIBILITY_PRIVATE:
        $this->parse('visible_staff_block', 'visible_staff');
        break;
    case Schoolbox\Assessment\Component\SubmissionBox::MARK_VISIBILITY_STUDENT:
        $this->parse('visible_staff_students_block', 'visible_staff_students');
        break;
    case Schoolbox\Assessment\Component\SubmissionBox::MARK_VISIBILITY_PARENT_STUDENT:
        $this->parse('visible_staff_students_parents_block', 'visible_staff_students_parents');
        break;
}

$readonly = (!($groupHandler->isSuperuser() || (!(date('Y', strtotime($assess['finish'])) < date('Y')))));

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
        $this->setFile('answerFormFragment', 'assess/answer/' . $factory->getQuestionType($currentQuestion['type_id']) . '/review.html');
        $this->setBlock('answerFormFragment', 'answerOption', 'answerOption_block');

        $this->setBlock('answerFormFragment', 'writable_comment', 'writable_comment_block');
        $this->setBlock('answerFormFragment', 'readonly_comment', 'readonly_comment_block');

        $this->setBlock('answerFormFragment', 'writable_mark', 'writable_mark_block');
        $this->setBlock('answerFormFragment', 'readonly_mark', 'readonly_mark_block');

        /* Reset answer options and marks from previous iteration
         */
        $this->setVar('answerOption_block', '');
        $this->setVar('writable_comment_block', '');
        $this->setVar('writable_mark_block', '');
        $this->setVar('readonly_comment_block', '');
        $this->setVar('readonly_mark_block', '');

        $this->setVar(array(
            'QUESTION_QUESTION_ID' => $currentQuestion['question_id'],
            'QUESTION_SEQUENCE' => $currentQuestion['sequence'],
            'QUESTION_QUESTION' => Scrub::html($currentQuestion['question']),
            'QUESTION_MARKS' => $currentQuestion['marks'],
        ));

        /* If this is a randomised section, number each question incrementally
           */
        if ($section['random'] == 1) {
            $this->setVar(array(
                'QUESTION_SEQUENCE' => $randomQuestionNumber
            ));
        }

        $randomQuestionNumber++;

        /* Load individual answers
         */
        $answerHandler->reviewResponse($this, $currentQuestion['question_id']);

        //
        // Mark and comment blocks must be parsed before the answerInputs block
        // gets parsed, so that the correct template vars are exposed to these
        // blocks.
        if ($readonly) {
            $this->parse('readonly_comment_block', 'readonly_comment');
            $this->parse('readonly_mark_block',    'readonly_mark');
        }
        else {
            $this->parse('writable_comment_block', 'writable_comment');
            $this->parse('writable_mark_block',    'writable_mark');
        }
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
    $this->parse('endAssess_block', 'endAssess');
} /* If there is no next section, then provide the finish link and parse out the
 * previous button if applicable
 */
else {
    if (is_numeric($nextSection['section_id'])) {
        $this->setVar(array(
            'NEXT_SECTION_ID' => $nextSection['section_id'],
        ));

        $this->parse('nextSection_block', 'nextSection');
        $this->setVar('endAssess_block', '');
    } else {
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
    } else {
        $this->setVar('prevSection_block', '');
    }
}

$this->setVar('writable_finalise_block', '');
$this->setVar('writable_resubmit_block', '');
if (!$readonly) {
    if ($attempt['reviewed_at'] != '') {
        $this->parse('writable_resubmit_block', 'writable_resubmit');
    } else {
        $this->parse('writable_finalise_block', 'writable_finalise');
    }
}
<?php
/**
 * Review assessment. The marks for each submitted question can be modified here
 * but the actual answers cannot be changed, only seen. Structurally, this is
 * similar to the normal undertaking of the test, only that answers for the
 * specified attempt are shown and you can only change the value of marks.
 * You can add extra marks beyond the specified maximum for bonus answers, and
 * half marks are acceptable.
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   2010 Alaress Pty Ltd
 * @link        http://www.alaress.com.au
 * @version     SVN: $Id$
 */

$factory         = new Assess_Factory();
$assessHandler   = new Assess_Main();
$attemptHandler  = new Assess_Attempt();
$responseHandler = new Assess_Response();
$sectionHandler  = new Assess_Section();
$groupHandler    = new Group;

if (!is_numeric($this->arg[0])) {
    error(112);
}

if (!is_numeric($this->arg[1])) {
    error(115);
}

if (!is_numeric($this->arg[2])) {
    error(128);
}

$assess  = $assessHandler->getByID($this->arg[0]);
$section = $sectionHandler->getByID($this->arg[1]);
// getListByAssessAndAttempt gets a rowset for some reason!
$attemptList = $attemptHandler->getListByAssessAndAttempt($this->arg[0], $this->arg[2]);
$attempt     = array_pop($attemptList);

if (!$assess) {
    error(112);
}

if ($assessHandler->isDeleted($this->arg[0])) {
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

/* Check access rights:
 * Allow owner of attempt to review
 * Allow staff access
 * Allow parent of child access
 */
if (!$attemptHandler->isOwner($this->arg[2])
    && !$groupHandler->isStaff()
    && !($groupHandler->isParentOfChild($attempt['user_id']) && !Config::get('restrict_parent_duework_access'))
) {
    error(129);
}

/* Check visibility
 */
$quizVisibility = new \Assess\Visibility();
$quizVisibility->setId($assess['mark_visibility']);

/* Mostly do nothing
 */
if ($quizVisibility->isStaff() && $groupHandler->isStaff()) {
    // noop
} elseif ($quizVisibility->isStaffStudent() && ($groupHandler->isStaff() || $groupHandler->isStudent())) {
    // noop
} elseif ($quizVisibility->isStaffStudentParent() && ($groupHandler->isStaff() || $groupHandler->isStudent())) {
    // noop
} else {
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
$this->setFile(TEMPLATE_content, 'assess/studentReview.html');
$this->setBlock(TEMPLATE_content, 'attemptFinished', 'attemptFinished_block');
$this->setBlock(TEMPLATE_content, 'attemptNotYetFinished', 'attemptNotYetFinished_block');
$this->setBlock(TEMPLATE_content, 'attemptUnfinished', 'attemptUnfinished_block');
$this->setBlock(TEMPLATE_content, 'answerInputs', 'answerInputs_block');
$this->setBlock(TEMPLATE_content, 'question', 'question_block');
$this->setBlock(TEMPLATE_content, 'nextSection', 'nextSection_block');
$this->setBlock(TEMPLATE_content, 'prevSection', 'prevSection_block');
$this->setTitle('Assessment: ' . $assess['title']);

try {
    $folder = Schoolbox\Resource\Folder\Mapper::getInstance()->findById($assess['category_id']);
} catch (Schoolbox\Mapper\Exception $e) {
    error(112);
    exit;
}

try {
    $userMapper = \Schoolbox\UserMapper::getInstance();
    $user       = $userMapper->findById($attempt['user_id']);
} catch (Schoolbox\Mapper\Exception $e) {
    error(19);
    exit;
}

/* Show current Section's data, and other mandatory tokens needed to complete
 * the Assessment.
 */
$openDate      = new Alaress_Date_Format($assess['start'], Alaress_Date_Format::DATABASE_FORMAT);
$dueDate       = new Alaress_Date_Format($assess['finish'], Alaress_Date_Format::DATABASE_FORMAT);
$attemptDate   = new Alaress_Date_Format($attempt['start'], Alaress_Date_Format::DATABASE_FORMAT);
$attemptFinish = new Alaress_Date_Format($attempt['finish'], Alaress_Date_Format::DATABASE_FORMAT);
$this->setVar(
    array(
        'ASSESS_ASSESS_ID'              => $assess['assess_id'],
        'ASSESS_TITLE'                  => Scrub::text($assess['title']),
        'ASSESS_START'                  => $openDate->getShort(),
        'ASSESS_FINISH'                 => $dueDate->getShort(),
        'ATTEMPT_ATTEMPT_ID'            => $_SESSION['attempt_id'],
        'ATTEMPT_START'                 => $attemptDate->getShort(),
        'ATTEMPT_FINISH'                => $attemptFinish->getShort(),
        'ATTEMPT_TIME_TAKEN'            => $assessHandler->sec2hms($attemptFinish->diffInSeconds($attemptDate, true)),
        'ATTEMPT_TOTAL_MARKS'           => number_format($attempt['totalMarks'], 2),
        'ATTEMPT_TOTAL_AVAILABLE_MARKS' => number_format($attempt['totalAvailableMarks'], 2),
        'SECTION_TITLE'                 => Scrub::text($section['title']),
        'SECTION_DESCRIPTION'           => Scrub::html($section['description']),
        'ASSESS_CATEGORY_NAME'          => Scrub::text($folder->getName()),
        'ASSESS_CATEGORY_ID'            => $folder->getId(),
        'attemptFinished_block'         => '',
        'attemptNotYetFinished_block'   => '',
        'attemptUnfinished_block'       => '',
    )
);

// Parse finished/unfinished/not finished yet blockage
if (!isset($attempt['finish'])) {
    if ($dueDate->isPast()) {
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
$allQuestions = $sectionHandler->getQuestions($_SESSION['attempt_id'], $section['section_id']);

if ($allQuestions) {
    $randomQuestionNumber = 1;

    foreach ($allQuestions as $currentQuestion) {
        $answerHandler = $factory->createAnswerModel($currentQuestion['type_id']);

        /* Load answer template into answerOption block
         */
        $this->setFile('answerFormFragment', 'assess/answer/' . $factory->getQuestionType($currentQuestion['type_id']) . '/studentReview.html');
        $this->setBlock('answerFormFragment', 'answerOption', 'answerOption_block');

        /* Reset answer options from previous iteration
         */
        $this->setVar('answerOption_block', '');

        $this->setVar(
            array(
                'QUESTION_QUESTION_ID' => $currentQuestion['question_id'],
                'QUESTION_SEQUENCE'    => $currentQuestion['sequence'],
                'QUESTION_QUESTION'    => Scrub::html($currentQuestion['question']),
                'QUESTION_MARKS'       => $currentQuestion['marks'],
            )
        );

        /* If this is a randomised section, number each question incrementally
           */
        if ($section['random'] == 1) {
            $this->setVar(array(
                'QUESTION_SEQUENCE' => $randomQuestionNumber
            ));

            $randomQuestionNumber++;
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
if (!is_numeric($nextSection['section_id']) && !is_numeric($prevSection['section_id'])) {
    $this->setVar('nextSection_block', '');
    $this->setVar('prevSection_block', '');
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
    } else {
        $this->setVar('nextSection_block', '');
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

// Breadcrumbs
$trail = new \Schoolbox\Breadcrumbs();
$trail->renderIntoSitePage(
    $this,
    'BREADCRUMB',
    [
        [
            'text'  => $folder->getName(),
            'href'  => '/homepage/' . $folder->getId(),
            'class' => ''
        ],
        [
            'text'  => $assess['title'],
            'href'  => '/assess/summary/' . $assess['assess_id'],
            'class' => ''
        ],
        [
            'text'  => $user->formatName(),
            'href'  => '/assess/attempts/' . $assess['assess_id'] . '/' . $user->getId(),
            'class' => ''
        ],
        [
            'text'  => 'Review',
            'href'  => '#',
            'class' => 'active'
        ],
    ]
);

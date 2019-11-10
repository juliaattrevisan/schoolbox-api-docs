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
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

use Schoolbox\Error;
use Schoolbox\IConfigLoader;
use Schoolbox\Learning\Assessment\DataModel\MapperProxy as AssessmentDataSource;
use Schoolbox\Learning\Quiz\ActionValidator;
use Schoolbox\Mapper;
use Schoolbox\Permission\Access\Access;
use Schoolbox\Permission\Access\UserAccess;
use Schoolbox\Resource\Folder;

$factory         = new Assess_Factory();
$assessHandler   = new Assess_Main();
$attemptHandler  = new Assess_Attempt();
$responseHandler = new Assess_Response();
$sectionHandler  = new Assess_Section();
$groupHandler    = new Group;

/**
 * @var SitePage $this
 * @var AssessmentDataSource $assessmentDataSource
 * @var IConfigLoader        $config
 */
$assessmentDataSource = $this->getDi()->get('assessmentDataSource');
$config               = $this->getDi()->get('config');
[$quizId, $sectionId, $attemptId] = $this->arg;
if (!is_numeric($quizId)) {
    error(Error\Mapper::ERROR_QUIZ_NOT_FOUND);
}

if (!is_numeric($sectionId)) {
    error(Error\Mapper::ERROR_SECTION_NOT_FOUND_2);
}

if (!is_numeric($attemptId)) {
    error(Error\Mapper::ERROR_ATTEMPT_NOT_FOUND);
}

$assess  = $assessHandler->getByID($quizId);
$section = $sectionHandler->getByID($sectionId);

if (!$assessHandler->isValidUrlAccess($quizId)) {
    $this->pageNotFound();
}

if ($assessHandler->isDeleted($quizId)) {
    error(Error\Mapper::ERROR_QUIZ_NOT_FOUND);
}

if (!$section) {
    error(Error\Mapper::ERROR_SECTION_NOT_FOUND_2);
}

// getListByAssessAndAttempt gets a rowset for some reason!
$attemptList = $attemptHandler->getListByAssessAndAttempt($quizId, $attemptId);

if (!$attemptList) {
    error(Error\Mapper::ERROR_ATTEMPT_NOT_FOUND_2);
}
$attempt                        = array_pop($attemptList);
$attempt['totalAvailableMarks'] = $attemptHandler->getTotalMarks($quizId, $attemptId);

/* Check the folder and user exists meaningfully
 */
try {
    $folder = Folder\Mapper::getInstance()->findById($assess['category_id']);
}
catch (Mapper\Exception $e) {
    error(Error\Mapper::ERROR_SELECT_QUIZ_GROUP);
}

try {
    $userMapper = \Schoolbox\UserMapper::getInstance();
    $user       = $userMapper->findById($attempt['user_id']);
} catch (Schoolbox\Mapper\Exception $e) {
    error(19);
    exit;
}

/** @var \Schoolbox\User $sessionUser */
$sessionUser = $_SESSION['user'];
$assessment  = $assessmentDataSource->findByQuizId($assess['assess_id'], $sessionUser);

// Check access rights
// Rules are the same as seeing an attempt
$actionValidator = new ActionValidator(Alaress_Date_Format::now(), Access::getInstance());
if (!$actionValidator->canReviewAttemptsReadonly(
    $sessionUser,
    $user,
    $assessment,
    $config->get('restrict_student_submissions')
)) {
    $this->pageNotFound();
}


/* Set up template
 */
$this->setFile(TEMPLATE_content, 'assess/studentReview.html');
$this->setBlock(TEMPLATE_content, 'attemptFinished', 'attemptFinished_block');
$this->setBlock(TEMPLATE_content, 'attemptNotYetFinished', 'attemptNotYetFinished_block');
$this->setBlock(TEMPLATE_content, 'attemptUnfinished', 'attemptUnfinished_block');
$this->setBlock(TEMPLATE_content, 'homepageLinkOpen', 'homepageLinkOpen_block');
$this->setBlock(TEMPLATE_content, 'homepageLinkClose', 'homepageLinkClose_block');
$this->setBlock(TEMPLATE_content, 'answerInputs', 'answerInputs_block');
$this->setBlock(TEMPLATE_content, 'question', 'question_block');
$this->setBlock(TEMPLATE_content, 'nextSection', 'nextSection_block');
$this->setBlock(TEMPLATE_content, 'prevSection', 'prevSection_block');
$this->setTitle('Assessment: ' . Scrub::text($assess['title']));

/* Show current Section's data, and other mandatory tokens needed to complete
 * the Assessment.
 */

// Does session user have a participation? If so, use CAP details.
$participant = $assessment->getParticipantIfExtant($sessionUser);
$plan['start'] = $participant ? $participant->getOpenDate() : $assessment->getOpenAt();
$plan['finish'] = $participant ? $participant->getDueDate() : $assessment->getDueAt();

$attemptDate   = new Alaress_Date_Format($attempt['start'], Alaress_Date_Format::DATABASE_FORMAT);
$attemptFinish = new Alaress_Date_Format($attempt['finish'], Alaress_Date_Format::DATABASE_FORMAT);

$returnUrlSuffix = isset($_GET["returnUrl"]) ? "?returnUrl=" . $_GET["returnUrl"] : "";

$this->setVar(
    array(
        'RETURN_URL_SUFFIX'             => $returnUrlSuffix,
        'ASSESS_ASSESS_ID'              => $assess['assess_id'],
        'ASSESS_TITLE'                  => Scrub::text($assess['title']),
        'ASSESS_START'                  => $plan['start']->getShort(),
        'ASSESS_FINISH'                 => $plan['finish']->getShort(),
        'ATTEMPT_ATTEMPT_ID'            => $attemptId,
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

// Display homepage link for user who have permission to view it
if (Access::getInstance()->getFolderAccess($_SESSION['user'], $folder->getId()) >= UserAccess::ACCESS_READ) {
    $this->parse('homepageLinkOpen_block', 'homepageLinkOpen');
    $this->parse('homepageLinkClose_block', 'homepageLinkClose');
}
else {
    $this->setVar('homepageLinkOpen_block', '');
    $this->setVar('homepageLinkClose_block', '');
}

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
$allQuestions = $sectionHandler->getQuestions($attemptId, $section['section_id']);

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
$nextSection = $sectionHandler->getAfter($quizId, $section['sequence']);
$prevSection = $sectionHandler->getBefore($quizId, $section['sequence']);

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

if (isset($_GET["returnUrl"])) {
    $returnUrl = \Schoolbox\Learning\Assessment\Redirect::decode($_GET["returnUrl"]);
} else {
    $returnUrl = '/assess/summary/' . $assess['assess_id'];
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
            'href'  => $returnUrl,
            'class' => ''
        ],
        [
            'text'  => $user->formatName(),
            'href'  => '/assess/attempts/' . $assess['assess_id'] . '/' . $user->getId().$returnUrlSuffix,
            'class' => ''
        ],
        [
            'text'  => 'Review',
            'href'  => '#',
            'class' => 'active'
        ],
    ]
);

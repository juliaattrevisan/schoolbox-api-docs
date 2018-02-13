<?php
/**
 * Assessment commencement. If the Assessment allows people to re-sit the test,
 * then clear the current attempt_id. Otherwise, remember it so people can't
 * reset the assessment timer by repeatedly refreshing the intro page.
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   2010 Alaress Pty Ltd
 * @link        http://www.alaress.com.au
 * @version     SVN: $Id$
 */

use Schoolbox\Assessment\Participant;
use Schoolbox\Breadcrumbs;
use Schoolbox\Learning\Assessment\DataModel as Assessment;
use Schoolbox\Learning\Assessment\DataModel\MapperProxy as AssessmentDataSource;
use Schoolbox\Learning\Quiz\ValidParticipant;
use Schoolbox\Mapper;
use Schoolbox\Permission\Access\UserAccess;
use Schoolbox\Permission\Access\Access;
use Schoolbox\Resource\Folder;
use Schoolbox\UserMapper;

$assessHandler = new Assess_Main();
$sectionHandler = new Assess_Section();
$attemptHandler = new Assess_Attempt();
$userHandler = new User();

/**
 * @var SitePage $this
 * @var AssessmentDataSource $assessmentDataSource
 */
$assessmentDataSource = $this->getDi()->get('assessmentDataSource');

if (!is_numeric($this->arg[0])) {
    error(112);
}
if (!$assessHandler->isValidUrlAccess($this->arg[0])) {
    $this->pageNotFound();
}

/* Get intro summary for the assessment
 */
$assess = $assessHandler->getIntro($this->arg[0]);

if (!$assess) {
    error(112);
}

if ($assessHandler->isDeleted($assess['assess_id'])) {
    error(112);
}

/* Check the folder exists meaningfully
 */
try {
    $folder = Folder\Mapper::getInstance()->findById($assess['category_id']);
}
catch (Mapper\Exception $e) {
    error(117);
}

/* Non-participants are sent to a 404 screen
 */
$isValidParticipant = new ValidParticipant(Participant\Plan\Mapper::getInstance(), Participant\Mapper::getInstance());

if (!$isValidParticipant->isSatisfiedBy($_SESSION['user'], $folder, $assess['assessment_id'])) {
    $this->pageNotFound();
}

/* Check if you are in this category, so you can't submit other category's
 * online assessments unless you are a part of that category.
 */
$assessment       = $assessmentDataSource->findByQuizId($assess['assess_id'], $_SESSION['user']);
$assessmentFolder = $assessment->getBaseContext()->getFolder();
$accessLevel      = Access::getInstance()->getFolderAccess($_SESSION['user'], $assessmentFolder->getId());
$readOnly         = false;

/* Looks like everyone has a Plan in some way, regardless if it's custom or default?
 */
$participant = $assessment->getParticipantIfExtant($_SESSION['user']);

/* Special case: participation isn't guaranteed at this point
 * so we can't get a Plan in all cases e.g. visiting from Course Import as an instructor
 */
$plan = [];

if (!$participant) {
    $plan['description'] = trim($assessment->getDescription());
    $plan['start'] = $assessment->getOpenAt()->getShort();
    $plan['finish'] = $assessment->getDueAt()->getShort();
}
else {
    $plan['description'] = trim($participant->getDescription());
    $plan['start'] = $participant->getOpenDate()->getShort();
    $plan['finish'] = $participant->getDueDate()->getShort();
}

if ($accessLevel < UserAccess::ACCESS_WRITE) {
    // I can't attempt a spec assessment
    if ($assessment->isSpecification()) {
        error(19);
    }

    // I can't attempt this if it has expired
    if (\Alaress_Date_Format::now()->gt($participant->getCloseDate())) {
        error(120);
    }

    // I can't attempt this if it isn't open
    if (\Alaress_Date_Format::now()->lt($participant->getOpenDate())) {
        error(143);
    }

    // I can't start this if I have too many attempts
    $numAttempts = $attemptHandler->userHasAttempted($assess['assess_id'], $_SESSION['id']);
    $attempt     = $attemptHandler->getLatestResumableForUser($assess['assess_id'], $_SESSION['id']);
    if ($attempt) {
        // Check if Attempt ran out of time
        // If it did, give the student another attempt, if they have one.
        $attemptStartTime     = strtotime($attempt['start']);
        $attemptTimeRemaining = $attemptStartTime + $assess['duration'] - time();
        $attemptEndTime       = $attemptStartTime + $assess['duration'];
        if (($attemptTimeRemaining < 0)
            && ($attemptStartTime > 0)
            && ($assess['duration'] != Assess_Main::UNLIMITED_DURATION)
        ) {
            // Close off the last attempt first (at the time it would have ended naturally)
            $closeTime = new Alaress_Date_Format($attemptEndTime, 'U');
            $attemptHandler->update(array(
                'attempt_id' => $attempt['attempt_id'],
                'finish'     => $closeTime->getDatabase(),
            ));

            // Now am I out of attempts?
            if (($assess['max_attempts'] > 0) && ($numAttempts >= $assess['max_attempts'])) {
                error(122);
            }
        }
    } else {
        // Am I already out of attempts?
        if (($assess['max_attempts'] > 0) && ($numAttempts >= $assess['max_attempts'])) {
            error(146);
        }
    }
} else {
    // I can do whatever I want
}

/* Continue once you get past checks
 */
$author = UserMapper::getInstance()->findByIdWithFallback($assess['user_id']);

$allSummary = $assessHandler->getIntroSummary($assess['assess_id']);
$allSections = $sectionHandler->getList($assess['assess_id']);

$this->setFile(TEMPLATE_content, 'assess/intro.html');
$this->setBlock(TEMPLATE_content, 'section', 'section_block');
$this->setBlock(TEMPLATE_content, 'startTest', 'startTest_block');
$this->setBlock(TEMPLATE_content, 'has_assess_description', 'has_assess_description_block');
$this->setBlock(TEMPLATE_content, 'has_assess_due', 'has_assess_due_block');
$this->setBlock(TEMPLATE_content, 'author_link_open', 'author_link_open_block');
$this->setBlock(TEMPLATE_content, 'author_link_close', 'author_link_close_block');

$this->setTitle('Assessment: ' . Scrub::text($assess['title']));

if ($author->isDeleted()) {
    $this->setVar('author_link_open_block', '');
    $this->setVar('author_link_close_block', '');
} else {
    $this->parse('author_link_open_block', 'author_link_open');
    $this->parse('author_link_close_block', 'author_link_close');
}

$this->setVar(array(
    'RETURN_URL_SUFFIX' => isset($_GET['returnUrl']) ? '?returnUrl=' . $_GET['returnUrl'] : '',
    'ASSESS_ASSESS_ID' => $assess['assess_id'],
    'ASSESS_TITLE' => Scrub::text($assess['title']),
    'ASSESS_DESCRIPTION' => Scrub::html($plan['description']),
    'ASSESS_START' => $plan['start'],
    'ASSESS_FINISH' => $plan['finish'],
    'FIRST_SECTION' => $allSections[0]['section_id'],
    'ASSESS_AUTHOR' => Scrub::text($author->formatName()),
    'ASSESS_AUTHOR_ID' => $author->getId(),
    'ASSESS_AUTHOR_EXT_ID' => $author->getExternalId(),
    'ASSESS_AUTHOR_ROLE' => $author->getRole()->getName(),
    'ASSESS_FOLDER' => Scrub::text($folder->getName() . ($folder->getCodeString() ? ' ('.$folder->getCodeString().')': '')),
    'ASSESS_FOLDER_ID' => $folder->getId(),
    'ASSESS_DURATION' => $assess['duration'] === Assess_Main::UNLIMITED_DURATION ? 'Unlimited' : $assessHandler->sec2hms($assess['duration']),
    'READ_ONLY' => $readOnly,
));

if ($assessment->isImplementation()) {
    $this->parse('has_assess_due_block', 'has_assess_due');
} else {
    $this->setVar('has_assess_due_block', '');
}

if ('' === $plan['description']) {
    $this->setVar('has_assess_description_block', '');
} else {
    $this->parse('has_assess_description_block', 'has_assess_description', true);
}

/* Section list and question count
 */
if (is_array($allSummary) && count($allSummary)) {
    $numQuestions = 0;

    foreach ($allSummary as $currentSummary) {
        if ($currentSummary['pool'] > 0 && $currentSummary['pool'] < $currentSummary['numQuestions']) {
            $numQuestions = $currentSummary['pool'];
        } else {
            $numQuestions = $currentSummary['numQuestions'];
        }

        $this->setVar(array(
            'SECTION_SEQUENCE' => $currentSummary['sequence'],
            'SECTION_TITLE' => Scrub::text($currentSummary['title']),
            'SECTION_NUMQUESTIONS' => $numQuestions,
        ));

        $this->parse('section_block', 'section', true);
    }

    $this->parse('startTest_block', 'startTest');

    if ($readOnly) {
        $this->setVar('startTest_block', '');
    }
} else {
    $this->setVar('section_block', '');
    $this->setVar('startTest_block', '');
}

$trail = new Breadcrumbs();
$term  = Schoolbox\Assessment\Term\Mapper::getInstance()->findForDate(
    new Alaress_Date_Format($assess['finish'], Alaress_Date_Format::DATABASE_FORMAT)
);

$trail->renderIntoSitePage($this, 'BREADCRUMB', [
    [
        'text'  => 'Learning',
        'href'  => '/learning/classes',
        'class' => ''
    ],
    [
        'text'  => $folder->getName(),
        'href'  => '/homepage/'.$folder->getId(),
        'class' => ''
    ],
    [
        'text'  => $assess['title'],
        'href'  => '/assess/intro/' . $assess['assess_id'],
        'class' => 'active'
    ],
]);

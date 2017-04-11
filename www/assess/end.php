<?php
/**
 * Assessment completion. Marks when an assessment is finished.
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   2010 Alaress Pty Ltd
 * @link        http://www.alaress.com.au
 * @version     SVN: $Id$
 */

use Schoolbox\Learning\Assessment\DataModel\MapperProxy as AssessmentDataSource;
use Schoolbox\Learning\Assessment\Visibility;
use Schoolbox\Learning\Course\DataModel\Specification\Unit\Model as UnitSpecification;
use Schoolbox\Learning\Quiz\Attempt\Model as QuizAttempt;
use Schoolbox\Learning\Quiz\Attempt\Mapper as QuizAttemptMapper;
use Schoolbox\Permission\Access\Access;
use Schoolbox\Permission\Access\UserAccess;
use Schoolbox\Breadcrumbs;

$assessHandler   = new Assess_Main();
$attemptHandler  = new Assess_Attempt();
$questionHandler = new Assess_Question();
$responseHandler = new Assess_Response();
$sectionHandler  = new Assess_Section();

$assess    = $assessHandler->getByID($this->arg[0]);
$attempt   = $attemptHandler->getByID($this->arg[1]);
$attemptId = $attempt['attempt_id'];
if (!is_numeric($attemptId)) {
    SitePage::localRedir('/assess/intro/' . $this->arg[0]);
}

if (!$assessHandler->isValidUrlAccess($assess['assess_id'])) {
    $this->pageNotFound();
}

if ($assessHandler->isDeleted($assess['assess_id'])) {
    error(112);
}

// Sanity check on attempt - does it belong to the current user and quiz?
if (!($attempt['user_id'] == $_SESSION['user']->getId() && $attempt['assess_id'] == $assess['assess_id'])) {
    error(123);
}

/* Disabled: doing so will allow teachers to give their test a "dry run" and
 * they can delete their attempts afterwards

if ($assessHandler->isOwner($this->arg[0])) {
    error(124);
}
*/

/**
 * @var SitePage $this
 * @var AssessmentDataSource $assessmentDataSource
 */
$assessmentDataSource = $this->getDi()->get('assessmentDataSource');

$assessment       = $assessmentDataSource->findByQuizId($assess['assess_id'], $_SESSION['user']);
$assessmentFolder = $assessment->getBaseContext()->getFolder();
$accessLevel      = Access::getInstance()->getFolderAccess($_SESSION['user'], [$assessmentFolder->getId()]);

if ($assessHandler->isExpired($assess['assess_id'])) {
    // If I'm an instructor, I should be allowed to attempt anything that is in a course spec.
    if (!($accessLevel >= UserAccess::ACCESS_WRITE && $assessment->getBaseContext() instanceof UnitSpecification)) {
        error(120);
    }
}

/* Participants should be blocked from attempting if it hasn't started,
 * but authors should be able to test out their content
 */
if ($accessLevel < UserAccess::ACCESS_WRITE && !$assessHandler->isOpen($assess)) {
    error(143);
}

$this->setFile(TEMPLATE_content, 'assess/end.html');
$this->setBlock(TEMPLATE_content, 'endAssess', 'endAssess_block');
$this->setBlock(TEMPLATE_content, 'alreadyEnded', 'alreadyEnded_block');
$this->setBlock(TEMPLATE_content, 'unmarkedRemaining', 'unmarkedRemaining_block');
$this->setBlock(TEMPLATE_content, 'has_marks', 'has_marks_block');
$this->setBlock(TEMPLATE_content, 'view_attempt', 'view_attempt_block');

$this->setTitle('Assessment: ' . Scrub::text($assess['title']));

$this->setVar(array(
    'ASSESS_TITLE' => Scrub::text($assess['title']),
    'ASSESS_CATEGORY_ID' => $assess['category_id'],
    'ASSESS_COMPLETION_MESSAGE' => Scrub::html($assess['completion_message']),
));

if (!$attempt['finish']) {
    $attemptFinishDate = Alaress_Date_Format::now();

    /** @var QuizAttempt $attempt */
    $attempt = QuizAttemptMapper::getInstance()->findById($attemptId);

    $attempt->setFinish($attemptFinishDate);

    QuizAttemptMapper::getInstance()->save($attempt);

    $this->setVar('ATTEMPT_END', $attemptFinishDate->getShort());
    $this->setVar('alreadyEnded_block', '');
    $this->parse('endAssess_block', 'endAssess');
} else {
    $attemptFinishDate = new Alaress_Date_Format($attempt['finish'], Alaress_Date_Format::DATABASE_FORMAT);
    $this->setVar('ATTEMPT_END', $attemptFinishDate->getShort());
    $this->parse('alreadyEnded_block', 'alreadyEnded');
    $this->setVar('endAssess_block', '');
}

/* Perform automated marking on responses which support this feature. Only
 * mark responses which haven't been automarked previously, which allows
 * teachers to customise response marks without being overwritten again.
 *
 * Once automated marking has been performed, remember which responses were
 * affected so we don't refetch them the next time the page loads.
 */
$allAttempts = $attemptHandler->getListByAssessAndAttempt($this->arg[0], $attemptId);

$factory = new Assess_Factory();

if ($allAttempts) {
    foreach ($allAttempts as $currentAttempt) {
        $allResponses = $responseHandler->getAutomarkableResponses($attemptId);

        if (is_array($allResponses)) {
            foreach ($allResponses as $currentResponse) {
                $answerHandler = $factory->createAnswerModel($currentResponse['type_id']);

                $answerHandler->mark($currentResponse);

                $markedResponseIDs[] = $currentResponse['response_id'];
            }
        }
    }
}

$responseHandler->updatedAutomatedMarks($markedResponseIDs);

/* Show mark for the attempt you just did
 */
$marks = $attemptHandler->getMarksForAttempt($attemptId);
$total = $attemptHandler->getTotalMarks($this->arg[0], $attemptId);

if ($total > 0) {
    $percentage = 100 * number_format($marks / $total, 2);
} else {
    $percentage = 0;
}

$numMarkedQuestions     = $responseHandler->numAutomarked($attemptId);
$numUnmarkedQuestions   = $responseHandler->numNotAutomarked($attemptId);
$valueUnmarkedQuestions = $responseHandler->valueNotAutomarked($attemptId);

$returnUrlSuffix = isset($_GET["returnUrl"]) ? "?returnUrl=" . $_GET["returnUrl"] : "";

$this->setVar(
    array(
        'RETURN_URL_SUFFIX' => $returnUrlSuffix,
        'ATTEMPT_MARKS'                    => $marks,
        'ATTEMPT_PERCENTAGE'               => $percentage,
        'ATTEMPT_TOTAL'                    => $total,
        'ATTEMPT_NUM_MARKED_QUESTIONS'     => $numMarkedQuestions,
        'ATTEMPT_NUM_UNMARKED_QUESTIONS'   => $numUnmarkedQuestions,
        'ATTEMPT_NUM_QUESTIONS'            => $numMarkedQuestions + $numUnmarkedQuestions,
        'ATTEMPT_VALUE_UNMARKED_QUESTIONS' => $valueUnmarkedQuestions,
    )
);

/* If there are unmarked questions remaining, show the block
 */
if ($numUnmarkedQuestions) {
    $this->parse('unmarkedRemaining_block', 'unmarkedRemaining');
} else {
    $this->setVar('unmarkedRemaining_block', '');
}

/* View Attempt link
 */
$firstSection = $sectionHandler->getFirst($assess['assess_id']);

$this->setVar(
    array(
        'ASSESS_ID'  => $assess['assess_id'],
        'SECTION_ID' => $firstSection['section_id'],
        'ATTEMPT_ID' => $attemptId,
    )
);

/* Visibility
 */
$visibility = new Visibility(Access::getInstance());

/** @var \Schoolbox\User $currentUser */
$currentUser = $_SESSION['user'];
if ($visibility->isVisibleToUser($assessment, $currentUser, true)) {
    $this->parse('has_marks_block', 'has_marks');
    $this->parse('view_attempt_block', 'view_attempt');

} else {
    $this->setVar(
        array(
            'view_attempt_block' => '',
            'has_marks_block'    => '',
        )
    );
}

$folderMap = Schoolbox\Resource\Folder\Mapper::getInstance();
$folder    = $folderMap->findById($assess['category_id']);

$trail = new Breadcrumbs();
$term  = Schoolbox\Assessment\Term\Mapper::getInstance()->findForDate(
    new Alaress_Date_Format($assess['finish'], Alaress_Date_Format::DATABASE_FORMAT)
);

if (isset($_GET["returnUrl"])) {
    $url = \Schoolbox\Learning\Assessment\Redirect::decode($_GET["returnUrl"]);
} else {
    $url = sprintf('/learning/grades/%d/%s/%d', $_SESSION['id'], $term->getCode(), $folder->getId());
}

$trail->renderIntoSitePage($this, 'BREADCRUMB', [
    [
        'text'  => 'Learning',
        'href'  => '/learning/classes',
        'class' => ''
    ],
    [
        'text'  => $folder->getName(),
        'href'  => $url,
        'class' => ''
    ],
    [
        'text'  => $assess['title'],
        'href'  => '#',
        'class' => 'active'
    ],
]);

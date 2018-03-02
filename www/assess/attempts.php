<?php
/**
 * Attempts
 *
 * Show a list of attempts for a particular Quiz.
 *
 * This allows students to review various attempts at a particular Quiz.
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2014, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */
use Schoolbox\Breadcrumbs;
use Schoolbox\Error;
use Schoolbox\IConfigLoader;
use Schoolbox\Learning\Assessment\Redirect;
use Schoolbox\Learning\Quiz\ActionValidator;
use Schoolbox\Mapper;
use Schoolbox\Permission\Access\Access;
use Schoolbox\Permission\Access\UserAccess;
use Schoolbox\Resource\Folder;
use Schoolbox\UserMapper;
use Schoolbox\Learning\Assessment\DataModel as Assessment;

$groupHandler = new Group();
$assessHandler = new Assess_Main();
$sectionHandler = new Assess_Section();
$attemptHandler = new Assess_Attempt();
$responseHandler = new Assess_Response();
[$quizId, $userId] = $this->arg;
$assess = $assessHandler->getByID($quizId);
/**
 * @var SitePage $this
 * @var Assessment\MapperProxy $assessmentMapperProxy
 * @var IConfigLoader          $config
 */
$assessmentMapperProxy = $this->getDi()->get('assessmentDataSource');
$config                = $this->getDi()->get('config');

if (!$assess) {
    error(112);
}

if (!$assessHandler->isValidUrlAccess($quizId)) {
    $this->pageNotFound();
}

if ($assessHandler->isDeleted($quizId)) {
    error(112);
}

if (!is_numeric($userId)) {
	error(19);
}

if ($groupHandler->isStudent() && ($_SESSION['user']->getId() != $userId)) {
	error(19);
}

try {
    $userMapper = UserMapper::getInstance();
    $user = $userMapper->findById($userId);
} catch (Schoolbox\Mapper\Exception $e) {
    error(19);
    exit;
}

/* Check the folder exists meaningfully
 */
try {
    $folder = Folder\Mapper::getInstance()->findById($assess['category_id']);
}
catch (Mapper\Exception $e) {
    error(Error\Mapper::ERROR_SELECT_QUIZ_GROUP);
}

/* Non-participants are sent to a 404 screen
 *
 * TODO: This will cause problems now that the spec patterns include the plan dates
 * This spec pattern only checks whether you can interact with a quiz -now-. It does not check whether you
 * could have interacted with it historically.
 */
$quiz            = $assessmentMapperProxy->findByQuizId($assess['assess_id'], $user);
$actionValidator = new ActionValidator(Alaress_Date_Format::now(), Access::getInstance());
if (!$actionValidator->canSeeAttempts($_SESSION['user'], $user, $quiz, $config->get('restrict_student_submissions'))) {
    $this->pageNotFound();
}

$this->setFile(TEMPLATE_content, 'assess/attempts.html');
$this->setBlock(TEMPLATE_content, 'has_preview_action', 'has_preview_action_block');
$this->setBlock(TEMPLATE_content, 'has_preview_link', 'has_preview_link_block');
$this->setBlock(TEMPLATE_content, 'has_attempts', 'has_attempts_block');
$this->setBlock(TEMPLATE_content, 'can_attempt', 'can_attempt_block');
$this->setBlock('has_attempts', 'attempt', 'attempt_block');
$this->setBlock('attempt', 'attempt_review', 'attempt_review_block');
$this->setBlock('attempt', 'attempt_resume', 'attempt_resume_block');
$this->setBlock('attempt', 'attempt_delete', 'attempt_delete_block');

/* Generic details
 */
$this->setVar(array(
    'ASSESS_ID' => $assess['assess_id'],
    'ASSESS_NAME' => Scrub::text($assess['title']),
    'CID' => $assess['category_id'],
    'ATTEMPT_USER_ID' => $user->getId(),
    'ATTEMPT_USER_NAME' => $user->formatName(),
    'CURRENT_USER' => $user->getFullName(),
));

/* Default
 */
$this->setVar([
    'attempt_block' => '',
    'has_attempts_block' => '',
    'has_preview_action_block' => '',
    'has_preview_link_block' => '',
    'can_attempt_block' => '',
]);

$canDeleteAttempt = $actionValidator->canDeleteAttempts($_SESSION['user'], $user, $quiz);

$perm = Access::getInstance()->getFolderAccess($_SESSION['user'], $assess['category_id']);

if ($perm >= UserAccess::ACCESS_WRITE) {
    $this->parse('has_preview_action_block', 'has_preview_action');
    $this->parse('has_preview_link_block', 'has_preview_link');
}

/* Iterate
 */
$allAttempts = $attemptHandler->getListForUser($quizId, $userId);

if (is_array($allAttempts) && count($allAttempts)) {
    $i = 0;

    foreach ($allAttempts as $currentAttempt) {
        $finishDateValue = null;
        $timeTaken = null;

        /* Teacher Review link goes to a different url than the Student Review
         * Since it's phplib, kinda do it the awkward way
         */
        $canReview = false;
        $canStudentReview = false;
        $reviewUrl = 'studentReview';

        $firstSection = $sectionHandler->getFirst($quizId);

        $this->setVar(array(
            'ATTEMPT_ASSESS_ID' => $quizId,
            'ATTEMPT_SECTION_ID' => $firstSection['section_id'],
            'ATTEMPT_ATTEMPT_ID' => $currentAttempt['attempt_id'],
        ));

        $startDate = new Alaress_Date_Format($currentAttempt['start'], Alaress_Date_Format::DATABASE_FORMAT);

        /* For attempts that are still in progress
         */

        if ($currentAttempt['finish']) {

			/* Calculate time taken in hrs
			 */
			$timeTaken = $assessHandler->sec2hms($currentAttempt['timeTaken']);

			$finishDate = new Alaress_Date_Format($currentAttempt['finish'], Alaress_Date_Format::DATABASE_FORMAT);
            $finishDateValue = $finishDate->getShort();

			/* Parse review link
			 */
			/* Check visibility
			  */
			$quizVisibility = new \Assess\Visibility();
			$quizVisibility->setId($assess['mark_visibility']);

			/* Mostly do nothing
			 */
			if ($quizVisibility->isStaff() && $groupHandler->isStaff()) {
				$canReview = true;
                $reviewUrl = 'review';
			}
			elseif ($quizVisibility->isStaffStudent()) {
                if ($groupHandler->isStaff()) {
                    $canReview = true;
                    $reviewUrl = 'review';
                }
                elseif ($groupHandler->isStudent()) {
                    $canStudentReview = true;
                }
			}
			elseif ($quizVisibility->isStaffStudentParent()) {
                if ($groupHandler->isStaff()) {
                    $canReview = true;
                    $reviewUrl = 'review';
                }
                elseif ($groupHandler->isStudent() || $groupHandler->isParentOfChild($userId)) {
                    $canStudentReview = true;
                }
			}
        }

        /* Misc details
         */
        $available = $attemptHandler->getTotalMarks($assess['assess_id'], $currentAttempt['attempt_id']);
        $reviewed = '';

        if ($currentAttempt['reviewed_at'] != null) {
            $reviewedAt = new \Alaress_Date_Format($currentAttempt['reviewed_at'], 'Y-m-d H:i:s');
            $reviewed = $reviewedAt->getShort();

            if (($available > 0) && ($canReview || $canStudentReview)) {
                $marks = round($currentAttempt['totalMarks'],2).' / '.round($available);
            }
            else {
                $marks = 'N/A';
            }
        }
        else {
            $numMarkedQuestions = $responseHandler->numAutomarked($currentAttempt['attempt_id']);
            $numUnmarkedQuestions = $responseHandler->numNotAutomarked($currentAttempt['attempt_id']);
            $valueUnmarkedQuestions = $responseHandler->valueNotAutomarked($currentAttempt['attempt_id']);

            if ($available > 0) {
                $reviewed = 'Not reviewed (' . (100 - round(($valueUnmarkedQuestions / $available) * 100)) . '% graded)';

                if($canReview || $canStudentReview) {
                    $marks = round($currentAttempt['totalMarks']).'/'.round($available);
                }
            }
            else {
                $marks = 'N/A';
            }
        }

        $returnUrlSuffix = !empty($_GET[Redirect::RETURN_URL]) ? Redirect::queryString($_GET[Redirect::RETURN_URL]) : '';

        $this->setVar(array(
            'RETURN_URL_SUFFIX' => $returnUrlSuffix,
            'ATTEMPT_START' => $startDate->getShort(),
            'ATTEMPT_FINISH' => $finishDateValue,
            'ATTEMPT_REVIEWED_AT' => $reviewed,
            'ATTEMPT_MARKS' => $marks,
            'ATTEMPT_TIME_TAKEN' => $timeTaken,
            'ATTEMPT_REVIEW_URL' => $reviewUrl
        ));

        /* Parse these blocks first
         */
        if ($currentAttempt['finish']) {
            if ($canReview || $canStudentReview) {
                $this->setVar(array(
                    'attempt_resume_block' => ''
                ));

                $this->parse('attempt_review_block', 'attempt_review');
            }
            else {
                $this->setVar(array(
                    'attempt_review_block' => ''
                ));
            }
        }
        else {
            $finishDateValue = 'Unfinished';

            /* Parse resume link
             */
            $this->setVar(array(
                'attempt_review_block' => ''
            ));

            $this->parse('attempt_resume_block', 'attempt_resume');
        }

        /* Only instructors can delete attempts
         */
        if ($canDeleteAttempt) {
            $this->parse('attempt_delete_block', 'attempt_delete');
        }
        else {
            $this->setVar([
                'attempt_delete_block' => ''
            ]);
        }

        $this->parse('attempt_block', 'attempt', true);

        /* Clear children after parsing out the parent
		 */
		$this->setVar(array(
			'respondent_name_block' => '',
            'attempt_review_block' => '',
			'attempt_resume_block' => '',
            'attempt_delete_block' => '',
		));

        $i++;
    }

    $this->parse('has_attempts_block', 'has_attempts');

    $this->setVar([
        'EMPTY_STATE_CSS' => 'hide'
    ]);
}
else {
    $this->setVar([
        'EMPTY_STATE_CSS' => ''
    ]);
}

/* If you can still attempt the quiz and number of attempts is less than maximum allowed, allow attempts to occur
 *
 * Note that the user check ensures that the ui illustrates that you attempt the quiz as yourself, rather than
 * someone else (this occurs when instructors view learners' attempts screens)
 */
if ($i < $assess['max_attempts']) {
    if ((int)$_SESSION['user']->getId() === (int)$userId) {
        if ($actionValidator->canAttemptQuiz($_SESSION['user'], $quiz)) {
            $this->parse('can_attempt_block', 'can_attempt');
        }
    }
}

/** @var Schoolbox\User $user */
$user = $_SESSION['user'];
$this->setVar('RETURN_URL', Redirect::queryString('/assess/attempts/'.$assess['assess_id'].'/'.$user->getId()));

$trail = new Breadcrumbs();

$trail->renderIntoSitePage($this, 'BREADCRUMB', [
    [
        'text'  => $folder->getName(),
        'href'  => '/homepage/' . $folder->getId(),
        'class' => ''
    ],
    [
        'text'  => $assess['title'],
        'href'  => Redirect::decode($_GET["returnUrl"]),
        'class' => ''
    ],
    [
        'text'  => $user->formatName(),
        'href'  => '#',
        'class' => 'active'
    ],
]);

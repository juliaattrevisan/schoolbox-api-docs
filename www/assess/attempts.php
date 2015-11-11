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
use Schoolbox\Resource\Folder\Mapper as FolderMapper;
use Schoolbox\UserMapper;

$groupHandler = new Group();
$assessHandler = new Assess_Main();
$sectionHandler = new Assess_Section();
$attemptHandler = new Assess_Attempt();
$responseHandler = new Assess_Response();

$assess = $assessHandler->getByID($this->arg[0]);

if (!$assess) {
    error(112);
}

if ($assessHandler->isDeleted($this->arg[0])) {
    error(112);
}

if (!is_numeric($this->arg[1])) {
	error(19);
}

if ($groupHandler->isStudent() && ($_SESSION['user']->getId() != $this->arg[1])) {
	error(19);
}

try {
    $userMapper = UserMapper::getInstance();
    $user = $userMapper->findById($this->arg[1]);
} catch (Schoolbox\Mapper\Exception $e) {
    error(19);
    exit;
}

try {
    $folderMapper = new FolderMapper();
    $folder = $folderMapper->findById($assess['category_id']);
}
catch (\Exception $e) {
    error(117);
}

$this->setFile(TEMPLATE_content, 'assess/attempts.html');
$this->setBlock(TEMPLATE_content, 'attempt', 'attempt_block');
$this->setBlock('attempt', 'attempt_review', 'attempt_review_block');
$this->setBlock('attempt', 'attempt_resume', 'attempt_resume_block');

/* Generic details
 */
$this->setVar(array(
    'ASSESS_NAME' => Scrub::text($assess['title']),
    'CID' => $assess['category_id'],
    'ATTEMPT_USER_ID' => $user->getId(),
    'ATTEMPT_USER_NAME' => $user->formatName()
));

/* Default
 */
$this->setVar('attempt_block', '');

/* Iterate
 */
$allAttempts = $attemptHandler->getListForUser($this->arg[0], $this->arg[1]);

if (is_array($allAttempts) && count($allAttempts)) {
    $i = 0;

    foreach ($allAttempts as $currentAttempt) {
        $i++;
        $canReview = false;

        $firstSection = $sectionHandler->getFirst($this->arg[0]);

        $this->setVar(array(
            'ATTEMPT_ASSESS_ID' => $this->arg[0],
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
			$canReview = false;

			if ($quizVisibility->isStaff() && $groupHandler->isStaff()) {
				$canReview = true;
			}
			else if ($quizVisibility->isStaffStudent() && ($groupHandler->isStaff() || $groupHandler->isStudent())) {
				$canReview = true;
			}
			else if ($quizVisibility->isStaffStudentParent() && ($groupHandler->isStaff() || $groupHandler->isStudent() || $groupHandler->isParentOfChild($this->arg[1]))) {
				$canReview = true;
			}
        }

        /* Misc details
         */
        $available = $attemptHandler->getTotalMarks($assess['assess_id'], $currentAttempt['attempt_id']);

        if ($currentAttempt['reviewed_at'] != null) {
            $reviewedAt = new \Alaress_Date_Format($currentAttempt['reviewed_at'], 'Y-m-d H:i:s');
            $reviewed = $reviewedAt->getShort();

            if ($available && $canReview) {
                $marks = round($currentAttempt['totalMarks']).'/'.round($available);
            }
            else {
                $marks = 'N/A';
            }
        }
        else {
            $numMarkedQuestions = $responseHandler->numAutomarked($currentAttempt['attempt_id']);
            $numUnmarkedQuestions = $responseHandler->numNotAutomarked($currentAttempt['attempt_id']);
            $valueUnmarkedQuestions = $responseHandler->valueNotAutomarked($currentAttempt['attempt_id']);

            $reviewed = 'Not reviewed ('.(100 - round(($valueUnmarkedQuestions / $available ) * 100)).'% graded)';

            if($canReview) {
                $marks = round($currentAttempt['totalMarks']).'/'.round($available);
            }
        }

        $this->setVar(array(
            'ATTEMPT_START' => $startDate->getShort(),
            'ATTEMPT_FINISH' => $finishDateValue,
            'ATTEMPT_REVIEWED_AT' => $reviewed,
            'ATTEMPT_MARKS' => $marks,
            'ATTEMPT_TIME_TAKEN' => $timeTaken,
            'ON_OFF' => $i % 2 ? 'dark' : ''
        ));

        /* Parse these blocks first
         */
        if ($currentAttempt['finish']) {
            if ($canReview) {
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

        $this->parse('attempt_block', 'attempt', true);

        /* Clear children after parsing out the parent
		 */
		$this->setVar(array(
			'respondent_name_block' => '',
            'attempt_review_block' => '',
			'attempt_resume_block' => '',
		));
    }
}

$trail = new Breadcrumbs();

$trail->renderIntoSitePage($this, 'BREADCRUMB', [
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
        'href'  => '#',
        'class' => 'active'
    ],
]);
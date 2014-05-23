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
$groupHandler = new Group();
$assessHandler = new Assess_Main();
$sectionHandler = new Assess_Section();
$attemptHandler = new Assess_Attempt();

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

$this->setFile(TEMPLATE_content, 'assess/attempts.html');
$this->setBlock(TEMPLATE_content, 'attempt', 'attempt_block');
$this->setBlock('attempt', 'attempt_review', 'attempt_review_block');
$this->setBlock('attempt', 'attempt_resume', 'attempt_resume_block');

/* Generic details
 */
$this->setVar(array(
    'SURVEY_NAME' => Scrub::text($assess['title']),
    'CID' => $assess['category_id'],
    'SURVEY_ID' => $assess['assess_id']
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

        $startDate = new Alaress_Date_Format($currentAttempt['start'], 'y-m-d h:i:s');

        /* For attempts that are still in progress
         */
        if ($currentAttempt['finish'] != '0000-00-00 00:00:00') {
            $finishDate = new Alaress_Date_Format($currentAttempt['finish'], 'y-m-d h:i:s');
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
			else if ($quizVisibility->isStaffStudentParent() && ($groupHandler->isStaff() || $groupHandler->isStudent())) {
				$canReview = true;
			}

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

        $firstSection = $sectionHandler->getFirst($this->arg[0]);

		$userMapper = new \Schoolbox\UserMapper();
		$user = $userMapper->findById($currentAttempt['user_id']);

		if ($currentAttempt['reviewed_at'] != null) {
			$reviewedAt = new \Alaress_Date_Format($currentAttempt['reviewed_at'], 'y-m-d h:i:s');
			$reviewed = $reviewedAt->getShort();

			$marks = $currentAttempt['totalMarks'] . ' / ' . $attemptHandler->getTotalMarks($assess['assess_id'], $currentAttempt['attempt_id']);
		}
		else {
			$reviewed = 'Not reviewed';
			$marks = '';
		}

        $this->setVar(array(
            'ATTEMPT_USER_ID' => $currentAttempt['user_id'],
            'ATTEMPT_START' => $startDate->getShort(),
            'ATTEMPT_FINISH' => $finishDateValue,
            'ATTEMPT_ASSESS_ID' => $this->arg[0],
            'ATTEMPT_SECTION_ID' => $firstSection['section_id'],
            'ATTEMPT_ATTEMPT_ID' => $currentAttempt['attempt_id'],
			'ATTEMPT_REVIEWED_AT' => $reviewed,
			'ATTEMPT_MARKS' => $marks,
            'ON_OFF' => $i % 2 ? 'dark' : ''
        ));

        $this->parse('attempt_block', 'attempt', true);

        /* Clear children after parsing out the parent
		 */
		$this->setVar(array(
			'respondent_name_block' => '',
			'attempt_resume_block' => ''
		));
    }
}
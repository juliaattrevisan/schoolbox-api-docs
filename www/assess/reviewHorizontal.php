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

use Schoolbox\Assessment\Term\Mapper as TermMapper;
use Schoolbox\Breadcrumbs;
use Schoolbox\Error;
use Schoolbox\Learning\Assessment\IAssessable;
use Schoolbox\Learning\Quiz\Attempt\Mapper as QuizAttemptMapper;
use Schoolbox\Mapper;
use Schoolbox\Resource\Folder;
use Schoolbox\UserMapper;

$factory = new Assess_Factory();
$assessHandler = new Assess_Main();
$attemptHandler = new Assess_Attempt();
$responseHandler = new Assess_Response();
$sectionHandler = new Assess_Section();
$groupHandler = new Group();
$userHandler = new User();
$questionHandler = new Assess_Question();
$answerHandler = new Assess_Answer();

if (!is_numeric($this->arg[0])) {
    error(112);
}

$assess = $assessHandler->getByID($this->arg[0]);

if (!$assessHandler->isValidUrlAccess($this->arg[0])) {
	$this->pageNotFound();
}

if ($assessHandler->isDeleted($this->arg[0])) {
	error(112);
}

if (!$assessHandler->isOwner($this->arg[0]) && ($groupHandler->getPermission($assess['category_id']) < WRITE_ACCESS)) {
	error(127);
}

/* Check the folder exists meaningfully
 */
try {
    $folder = Folder\Mapper::getInstance()->findById($assess['category_id']);
}
catch (Mapper\Exception $e) {
    error(Error\Mapper::ERROR_SELECT_QUIZ_GROUP);
}

$dueDate = new Alaress_Date_Format($assess['finish'], Alaress_Date_Format::DATABASE_FORMAT);
$term    = TermMapper::getInstance()->findForDate($dueDate);

/* Index of questions
 */
if (!isset($this->arg[1])) {

	/* Set up template
	 */
	$this->setFile(TEMPLATE_content, 'assess/reviewHorizontalIndex.html');
	$this->setBlock(TEMPLATE_content, 'allQuestions', 'allQuestions_block');

	/* Show this Assess' contents
 	 */
	$this->setFile('assessContentsFragment', 'assess/reviewHorizontalContents.html');

	$assessHandler = new Assess_Main();
	$assessHandler->renderAssessContents($assess['assess_id'], $this);

	$this->setVar(array(
        'ASSESS_TITLE'         => Scrub::text($assess['title']),
        'ASSESS_ASSESS_ID'     => $assess['assess_id'],
        'ASSESS_CATEGORY_ID'   => $folder->getId(),
        'ASSESS_CATEGORY_NAME' => Scrub::text($folder->getName()),
	));

	$this->parse('allQuestions_block', 'assessContentsFragment', true);

    /* Render out breadcrumbs
     */
    $trail   = new Breadcrumbs();
    $trail->renderIntoSitePage($this, 'BREADCRUMB', [
        [
            'text'  => 'Learning',
            'href'  => '/learning/classes',
            'class' => ''
        ],
        [
            'text'  => $folder->getName(),
            'href'  => "/learning/markbook/{$folder->getId()}/term/{$term->getCode()}/",
            'class' => ''
        ],
        [
            'text'  => $assess['title'],
            'href'  => "/learning/assessments/{$assess['assessment_id']}/status",
            'class' => ''
        ],
        [
            'text'  => 'Horizontal Marking',
            'href'  => "#",
            'class' => 'active'
        ],
    ]);
}
/* Update the reviewed_at for all the latest attempts for a particular assess :D
 * How do you like them apples
 */
else if ($this->arg[1] == 'finalise') {
	$attemptHandler->finaliseAllLatestAttempts($assess['assess_id']);

	//Save all the marks
    $attemptMapper = QuizAttemptMapper::getInstance();
    $allAttempts = $attemptMapper->findByColumn("assess_id", $assess['assess_id']);
    $attemptMapper->save($allAttempts);

	$this->localRedir("/learning/assessments/{$assess['assessment_id']}/status");
}
/* Reviewing attempts for a question
 */
else {
	if (!is_numeric($this->arg[1])) {
		error(119);
	}

	$question = $questionHandler->getByID($this->arg[1]);
	$section  = $sectionHandler->getByID($question['section_id']);

	if (!$question) {
		error(119);
	}

	/* Set up template
	 */
	$this->setFile(TEMPLATE_content, 'assess/reviewHorizontal.html');
	$this->setBlock(TEMPLATE_content, 'answerInputs', 'answerInputs_block');
	$this->setBlock(TEMPLATE_content, 'attempt', 'attempt_block');
	$this->setBlock(TEMPLATE_content, 'next_question', 'next_question_block');
	$this->setBlock(TEMPLATE_content, 'previous_question', 'previous_question_block');
	$this->setBlock(TEMPLATE_content, 'writable_finalise', 'writable_finalise_block');
	$this->setBlock(TEMPLATE_content, 'writable_finalise_button', 'writable_finalise_button_block');
	$this->setBlock(TEMPLATE_content, 'visible_staff', 'visible_staff_block');
	$this->setBlock(TEMPLATE_content, 'visible_staff_students', 'visible_staff_students_block');
	$this->setBlock(TEMPLATE_content, 'visible_staff_students_parents', 'visible_staff_students_parents_block');
	$this->setVar('writable_finalise_block', '');
	$this->setVar('writable_finalise_button_block', '');
	$this->setTitle('Assessment: ' . Scrub::text($assess['title']));

	/* Correct answer displayed once, at the top of the page, under the question
	 * Each answer is handled uniquely by each answerHandler
	 */
	$this->setBlock(TEMPLATE_content, 'answerInputsTop', 'answerInputsTop_block');
	$this->setFile('answerFormFragmentTop', 'assess/answer/' . $factory->getQuestionType($question['type_id']) . '/reviewTop.html');
	$this->setBlock('answerFormFragmentTop', 'answerTop', 'answerTop_block');

	$answerHandler = $factory->createAnswerModel($question['type_id']);
	$answerHandler->reviewResponseHorizontalAnswerForQuestion($this, $question);

	$this->parse('answerInputsTop_block', 'answerFormFragmentTop', true);

	/* Show current Section's data, and other mandatory tokens needed to complete
	 * the Assessment.
	 */
	$openDate = new Alaress_Date_Format($assess['start'], Alaress_Date_Format::DATABASE_FORMAT);
	$dueDate  = new Alaress_Date_Format($assess['finish'], Alaress_Date_Format::DATABASE_FORMAT);
	$this->setVar(array(
		'ASSESS_ASSESS_ID' => $assess['assess_id'],
		'ASSESS_TITLE' => Scrub::text($assess['title']),
		'ASSESS_START' => $openDate->getShort(),
		'ASSESS_FINISH' => $dueDate->getShort(),
		'SECTION_TITLE' => Scrub::text($section['title']),
		'QUESTION_TITLE' => Scrub::html($question['question']),
		'ASSESS_CATEGORY_ID' => $folder->getId(),
		'ASSESS_CATEGORY_NAME' => Scrub::text($folder->getName()),
		'visible_staff_block'                  => '',
		'visible_staff_students_block'         => '',
		'visible_staff_students_parents_block' => '',
	));

	// Visibility copypaste
	switch ($assess['mark_visibility']) {
		case IAssessable::MARK_VISIBILITY_PRIVATE:
			$this->parse('visible_staff_block', 'visible_staff');
			break;
		case IAssessable::MARK_VISIBILITY_STUDENT:
			$this->parse('visible_staff_students_block', 'visible_staff_students');
			break;
		case IAssessable::MARK_VISIBILITY_PARENT_STUDENT:
			$this->parse('visible_staff_students_parents_block', 'visible_staff_students_parents');
			break;
	}

	$readonly = !$term->isMarkableBy($_SESSION['user']);

	/* Load all the questions for this section.
	 * Note: only show the randomly-selected ones
	 */
	$allAttempts = $attemptHandler->getAllAttemptsForQuestion($question['question_id']);

    $this->setVar('attempt_block', '');

	if ($allAttempts) {
		$j = 1;
		foreach ($allAttempts as $currentAttempt) {
            try {
                $user = UserMapper::getInstance()->findById($currentAttempt['user_id']);
            }
            catch (Mapper\Exception $e) {
                continue;
            }

            /* Load answer template into answerOption block
             */
			$this->setFile('answerFormFragment', 'assess/answer/' . $factory->getQuestionType($currentAttempt['type_id']) . '/reviewBottom.html');
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
				'QUESTION_QUESTION_ID' => $currentAttempt['question_id'],
				'QUESTION_SEQUENCE' => $currentAttempt['sequence'],
				'QUESTION_QUESTION' => Scrub::html($currentAttempt['question']),
				'QUESTION_MARKS' => $currentAttempt['marks'],
				'QUESTION_PLUGIN' => $answerHandler->getPluginReview(),
			));

			/* Load individual answers
			 */
			$answerHandler->reviewResponseHorizontal($this, $currentAttempt['attempt_id'], $currentAttempt);

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
			$this->parse('attempt_block', 'attempt', true);

			/* Clear child blocks after parsing out the parent
			 */
			$answerHandler->cleanupParse($this);

			$this->setVar('answerFormFragment', '');
			$this->setVar('answerInputs_block', '');
		}
	}

	/* Navigation tools
	 */

	/* Previous question
	 */
	$previous = $questionHandler->getPreviousQuestion($assess['assess_id'], $question['question_id']);

	if ($previous) {
		$this->setVar(array(
			'PREVIOUS_ID' => $previous['question_id']
		));

		$this->parse('previous_question_block', 'previous_question');
	}
	else {
		$this->setVar('previous_question_block', '');
	}

	/* Next question
	 */
	$next = $questionHandler->getNextQuestion($assess['assess_id'], $question['question_id']);

	if ($next) {
		$this->setVar(array(
			'NEXT_ID' => $next['question_id']
		));

		$this->parse('next_question_block', 'next_question');
	}
	else {

		/* Finalise all the attempts in a macro-ey way
		 *
		 * This option only exists on the last question so you don't
		 * accidentally finalise everything prematurely
		 */
		if (!$readonly) {
			$this->parse('writable_finalise_block', 'writable_finalise');
			$this->parse('writable_finalise_button_block', 'writable_finalise_button');
		}

		$this->setVar('next_question_block', '');
	}

    /* Render out breadcrumbs
     */
    $dueDate = new Alaress_Date_Format($assess['finish'], Alaress_Date_Format::DATABASE_FORMAT);
    $term    = TermMapper::getInstance()->findForDate($dueDate);
    $trail   = new Breadcrumbs();
    $trail->renderIntoSitePage($this, 'BREADCRUMB', [
        [
            'text'  => 'Learning',
            'href'  => '/learning/classes',
            'class' => ''
        ],
        [
            'text'  => $folder->getName(),
            'href'  => "/learning/markbook/{$folder->getId()}/term/{$term->getCode()}/",
            'class' => ''
        ],
        [
            'text'  => $assess['title'],
            'href'  => "/learning/assessments/{$assess['assessment_id']}/status",
            'class' => ''
        ],
        [
            'text'  => 'Horizontal Marking',
            'href'  => "/assess/reviewHorizontal/{$assess['assess_id']}",
            'class' => ''
        ],
        [
            'text'  => Scrub::text($section['title']),
            'href'  => "#",
            'class' => 'active'
        ]
    ]);
}

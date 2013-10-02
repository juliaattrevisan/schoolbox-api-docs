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
$questionHandler = new Assess_Question();

if (!is_numeric($this->arg[0])) {
    error(112);
}

$assess = $assessHandler->getByID($this->arg[0]);

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
	$this->setBlock('assessContentsFragment', 'section', 'section_block');
	$this->setBlock('section', 'question', 'question_block');

	$assessHandler = new Assess_Main();
	$assessHandler->renderAssessContents($assess['assess_id'], $this);

	$this->setVar(array(
		'ASSESS_TITLE' => $assess['title'],
		'ASSESS_ASSESS_ID' => $assess['assess_id'],
		'ASSESS_CATEGORY_ID' => $assess['category_id'],
	));

	$this->parse('allQuestions_block', 'assessContentsFragment', true);
}
/* Reviewing attempts for a question
 */
else {
	if (!is_numeric($this->arg[1])) {
		error(119);
	}

	$question = $questionHandler->getByID($this->arg[1]);

	if (!$assess) {
		error(112);
	}

	if (!$question) {
		error(119);
	}

	if ($assessHandler->isDeleted($this->arg[0])) {
		error(112);
	}

	if (!$assessHandler->isOwner($this->arg[0]) && ($groupHandler->getPermission($assess['category_id']) < WRITE_ACCESS)) {
		error(127);
	}

	$user = $userHandler->getByID($attempt['user_id']);

	/* Set up template
	 */
	$this->setFile(TEMPLATE_content, 'assess/reviewHorizontal.html');
	$this->setBlock(TEMPLATE_content, 'answerInputs', 'answerInputs_block');
	$this->setBlock(TEMPLATE_content, 'attempt', 'attempt_block');
	$this->setBlock(TEMPLATE_content, 'next_question', 'next_question_block');
	$this->setBlock(TEMPLATE_content, 'previous_question', 'previous_question_block');
	$this->setBlock('attempt', 'writable_update', 'writable_update_block');
	$this->setTitle('Assessment: ' . $assess['title']);

	/* Show current Section's data, and other mandatory tokens needed to complete
	 * the Assessment.
	 */
	$this->setVar(array(
		'ASSESS_ASSESS_ID' => $assess['assess_id'],
		'ASSESS_TITLE' => Scrub::text($assess['title']),
		'ATTEMPT_ATTEMPT_ID' => $_SESSION['attempt_id'],
		'QUESTION_TITLE' => Scrub::text($question['title']),
		'ASSESS_PREVIOUSLY_REVIEWED' => $reviewedAt,
		'RESPONDENT_NAME' => $userHandler->formatName($user),
	));

	$readonly = (!($groupHandler->isSuperuser() || (!(date('Y', strtotime($assess['finish'])) < date('Y')))));

	/* Load all the questions for this section.
	 * Note: only show the randomly-selected ones
	 */
	$allAttempts = $attemptHandler->getAllAttemptsForQuestion($question['question_id']);

	if ($allAttempts) {
		foreach ($allAttempts as $currentAttempt) {
			$answerHandler = $factory->createAnswerModel($currentAttempt['type_id']);

			/* Load answer template into answerOption block
			 */
			$this->setFile('answerFormFragment', 'assess/answer/' . $factory->getQuestionType($currentAttempt['type_id']) . '/review.html');
			$this->setBlock('answerFormFragment', 'answerOption', 'answerOption_block');

			$this->setBlock('answerFormFragment', 'writable_comment', 'writable_comment_block');
			$this->setBlock('answerFormFragment', 'readonly_comment', 'readonly_comment_block');

			$this->setBlock('answerFormFragment', 'writable_mark', 'writable_mark_block');
			$this->setBlock('answerFormFragment', 'readonly_mark', 'readonly_mark_block');

			/* Reset answer options and marks from previous iteration
			 */
			$this->setVar('answerOption_block', '');
			$this->setVar('writable_update_block', '');
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
				$this->parse('writable_update_block',  'writable_update');
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
	else {
		$this->setVar('attempt_block', '');
	}

	//if ($readonly) {
	//    $this->setVar('writable_finalise_block', '');
	//} else {
	//    $this->parse('writable_finalise_block', 'writable_finalise');
	//}

	/* Navigation tools
	 */
	$previous = $questionHandler->getPreviousQuestion($assess['assess_id'], $question['sequence']);

	if ($previous) {
		$this->setVar(array(
			'PREVIOUS_ID' => $previous['question_id']
		));

		$this->parse('previous_question_block', 'previous_question');
	}
	else {
		$this->setVar('previous_question_block', '');
	}

	$next = $questionHandler->getNextQuestion($assess['assess_id'], $question['sequence']);

	if ($next) {
		$this->setVar(array(
			'NEXT_ID' => $next['question_id']
		));

		$this->parse('next_question_block', 'next_question');
	}
	else {
		$this->setVar('next_question_block', '');
	}

}
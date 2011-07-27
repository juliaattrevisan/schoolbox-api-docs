<?php
require_once('../library/assess/AssessQuestionBank.php');

$this->setFile(TEMPLATE_content, 'assess/questionbank/contents.html');
$this->setBlock(TEMPLATE_content, 'question_category', 'question_category_block');
$this->setBlock(TEMPLATE_content, 'show_submit', 'show_submit_block');
$this->setBlock(TEMPLATE_content, 'show_delete', 'show_delete_block');
$this->setBlock(TEMPLATE_content, 'no_questions', 'no_questions_block');
$this->setBlock('question_category', 'question', 'question_block');
$this->setBlock('question', 'show_input', 'show_input_block');

$this->setVar(array(
	'SECTION_ID' => $this->arg[0],
));

$questionBankHandler = new AssessQuestionBank();

$allQuestions = $questionBankHandler->getContents();

/* Iterate and show
 */
if (is_array($allQuestions) && count($allQuestions)) {
	$previousCategory = false;

	foreach($allQuestions as $currentQuestion) {
		if (($currentQuestion['question_category_id'] != $previous) && $previous) {
			$this->parse('question_category_block', 'question_category', true);
			$this->setVar('question_block', '');
		}

		$this->setVar(array(
			'QUESTION_BANK_ID' => $currentQuestion['question_bank_id'],
			'QUESTION_CATEGORY_NAME' => $currentQuestion['name'],
			'QUESTION_TYPE' => $currentQuestion['description'],
			'QUESTION' => $currentQuestion['question'],
			'MARKS' => $currentQuestion['marks'],
		));

		/* Hide checkboxes if we aren't adding contents to a section
		 */
		if (is_numeric($this->arg[0])) {
			$this->parse('show_input_block', 'show_input');
		}
		else {
			$this->setVar('show_input_block', '');
		}

		$this->parse('question_block', 'question', true);

		$previous = $currentQuestion['question_category_id'];
	}

	/* Parse last question_category out
	 */
	$this->parse('question_category_block', 'question_category', true);

	/* Hide submit button if we aren't adding contents to a section
	 */
	if (is_numeric($this->arg[0])) {
		$this->parse('show_submit_block', 'show_submit');
	}
	else {
		$this->setVar('show_submit_block', '');
	}

	/* Show delete functionality
	 */
	$this->parse('show_delete_block', 'show_delete');

	/* Hide 'no questions' message
	 */
	$this->setVar('no_questions_block', '');
}
else {

	/* There are no questions: hide the add/remove functionality and display a
	 * little message
	 */
	$this->setVar('show_submit_block', '');
	$this->setVar('show_delete_block', '');
	$this->setVar('question_category_block', '');
	$this->parse('no_questions_block', 'no_questions');
}



?>

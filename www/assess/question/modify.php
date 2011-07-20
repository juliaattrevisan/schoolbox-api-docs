<?php
/**
 * Question modification and Answer modification.
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   2010 Alaress Pty Ltd
 * @link        http://www.alaress.com.au
 * @version     SVN: $Id$
 */
require_once '../library/assess/assess.php';
require_once '../library/assess/assess-section.php';
require_once '../library/assess/assess-factory.php';
require_once('../library/assess/assess-questioncategory.php');
require_once('../library/group.php');

$factory = new AssessFactory();
$assessHandler = new Assess();
$sectionHandler = new AssessSection();
$groupHandler = new Group();

if (!$groupHandler->isStaff()) {
    error(130);
}

/* If a question was submitted
 */
if (!empty($_POST)) {
    $factory = new AssessFactory();

    if (!is_numeric($_POST['type_id'])) {
        error(116);
    }

    $section = $sectionHandler->getByID(scrubTEXT($_POST['section_id']));

	if ($assessHandler->isDeleted($section['assess_id'])) {
		error(112);
	}

    if (!$assessHandler->isOwner($section['assess_id'])) {
        error(130);
    }

	if ($sectionHandler->isDeleted($section['section_id'])) {
		error(115);
	}

    $questionHandler = $factory->createQuestionModel(scrubTEXT($_POST['type_id']));
    $answerHandler = $factory->createAnswerModel(scrubTEXT($_POST['type_id']));

    $questionHandler->update($_POST);
    $answerHandler->update($_POST);

    /* Find the assess summary page
     */
    localRedir('/assess/summary/' . $section['assess_id']);
}
else {
    if (!is_numeric($this->arg[0])) {
        error(115);
    }

    /* Render modify page
     */
    $this->setFile(TEMPLATE_content, 'assess/question/modify.html');
    $this->setTitle('Modify Question');
    $this->setBlock(TEMPLATE_content, 'answerInputs', 'answerInputs_block');
    $this->setBlock(TEMPLATE_content, 'questionType', 'questionType_block');
    $this->setBlock(TEMPLATE_content, 'allQuestions', 'allQuestions_block');

    $questionHandler = new AssessQuestion();
    $question = $questionHandler->getByID($this->arg[0]);
    $section = $sectionHandler->getByID($question['section_id']);

	if ($assessHandler->isDeleted($section['assess_id'])) {
		error(112);
	}

    if (!$assessHandler->isOwner($section['assess_id'])) {
        error(130);
    }

	if ($sectionHandler->isDeleted($section['section_id'])) {
		error(115);
	}

    $answerHandler = $factory->createAnswerModel($question['type_id']);

    /* Show question type options. For the Modify, preselect whichever it was
     */
    $currentQuestionType = $questionHandler->getQuestionType($question['type_id']);

    /* Load answer template into answerOption block
     */
    $this->setFile('answerFormFragment', 'assess/answer/' . $factory->getQuestionType($question['type_id']) . '/modify.html');
    $this->setBlock('answerFormFragment', 'answerOption', 'answerOption_block');

    $answerHandler->modify($this, $question['question_id']);

    $this->parse('answerInputs_block', 'answerFormFragment', true);

    $this->setVar(array(
        'QUESTIONTYPE_TYPE_ID' => $currentQuestionType['type_id'],
        'QUESTIONTYPE_DESCRIPTION' => $currentQuestionType['description'],
        'QUESTIONTYPE_SELECTED' => $selected,
    ));

    /* Render contents of this Assess
     */
    if (empty($section)) {
        error(51);
    }

    $this->setVar(array(
        'ASSESS_ASSESS_ID' => $section['assess_id'],
        'SECTION_ID' => $question['section_id'],
        'SEQUENCE' => $question['sequence'],
        'QUESTION_ID' => $question['question_id'],
        'QUESTION' => $question['question'],
        'MARKS' => $question['marks'],
        'DISABLED_SELECTED' => '',
    ));

    /* Show this Assess' contents
     */
    $this->setFile('assessContentsFragment', 'assess/contents.html');
    $this->setBlock('assessContentsFragment', 'section', 'section_block');
    $this->setBlock('section', 'question', 'question_block');

    $assess = new Assess();
    $assess->renderAssessContents($section['assess_id'], $this);

    $this->parse('allQuestions_block', 'assessContentsFragment', true);

	/* Options for importing into the Question Bank
	 */
	$this->setBlock(TEMPLATE_content, 'question_category', 'question_category_block');

	$questionCategoryHandler = new AssessQuestionCategory();
	$allQuestionCategories = $questionCategoryHandler->getList();

	if (is_array($allQuestionCategories) && count($allQuestionCategories)) {
		foreach($allQuestionCategories as $currentQuestionCategory) {
			$this->setVar(array(
				'QUESTION_CATEGORY_ID' => $currentQuestionCategory['question_category_id'],
				'QUESTION_CATEGORY_NAME' => $currentQuestionCategory['name'],
			));

			$this->parse('question_category_block', 'question_category', true);
		}
	}
	else {
		$this->setVar('question_category_block', '');
	}
}
?>

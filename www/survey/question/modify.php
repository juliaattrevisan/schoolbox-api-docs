<?php
/**
 * Question modification and Answer modification.
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   2010 Alaress Pty Ltd
 * @link        http://www.alaress.com.au
 * @version     SVN: $Id$
 */

$factory = new Survey_Factory();
$assessHandler = new Survey_Main();
$sectionHandler = new Survey_Section();
$groupHandler = new Group();

/* If a question was submitted
 */
if (!empty($_POST)) {
    if (!is_numeric($_POST['type_id'])) {
        error(116);
    }

    $section = $sectionHandler->getByID(Scrub::text($_POST['section_id']));
    $assess = $assessHandler->getByID($section['assess_id']);

    if ($assessHandler->isDeleted($assess['assess_id'])) {
        error(112);
    }

    if (!$assessHandler->isOwner($assess['assess_id']) && ($groupHandler->getPermission($assess['category_id']) < WRITE_ACCESS)) {
        error(130);
    }

    if ($sectionHandler->isDeleted($section['section_id'])) {
        error(115);
    }

    $questionHandler = $factory->createQuestionModel(Scrub::text($_POST['type_id']));
    $answerHandler = $factory->createAnswerModel(Scrub::text($_POST['type_id']));

    $questionHandler->update($_POST);
    $answerHandler->update($_POST);

    /* Create new question
     */
    SitePage::localRedir('/survey/question/create/' . $section['section_id']);
} else {
    if (!is_numeric($this->arg[0])) {
        error(115);
    }

    /* Render modify page
     */
    $this->setFile(TEMPLATE_content, 'survey/question/modify.html');
    $this->setTitle('Modify Question');
    $this->setBlock(TEMPLATE_content, 'answerInputs', 'answerInputs_block');
    $this->setBlock(TEMPLATE_content, 'questionType', 'questionType_block');
    $this->setBlock(TEMPLATE_content, 'allQuestions', 'allQuestions_block');

    $questionHandler = new Survey_Question();
    $question = $questionHandler->getByID($this->arg[0]);

    if ($question) {
        $section = $sectionHandler->getByID($question['section_id']);
    }

    if ($section) {
        $assess = $assessHandler->getByID($section['assess_id']);
    }

    if ($assessHandler->isDeleted($section['assess_id'])) {
        error(112);
    }

    if (!$assessHandler->isOwner($assess['assess_id']) && ($groupHandler->getPermission($assess['category_id']) < WRITE_ACCESS)) {
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
    $this->setFile('answerFormFragment', 'survey/answer/' . $factory->getQuestionType($question['type_id']) . '/modify.html');
    $this->setBlock('answerFormFragment', 'answerOption', 'answerOption_block');

    $answerHandler->modify($this, $question['question_id']);

    $this->parse('answerInputs_block', 'answerFormFragment', true);

    $this->setVar(array(
        'QUESTIONTYPE_TYPE_ID' => $currentQuestionType['type_id'],
        'QUESTIONTYPE_DESCRIPTION' => Scrub::html($currentQuestionType['description']),
        'QUESTIONTYPE_SELECTED' => $selected,
    ));

    /* Render contents of this Survey
     */
    if (empty($section)) {
        error(51);
    }

    /* Show this Survey' contents
      */
    $this->setFile('assessContentsFragment', 'survey/contents.html');
    $this->setBlock('assessContentsFragment', 'section', 'section_block');
    $this->setBlock('section', 'question', 'question_block');

    $this->parse('allQuestions_block', 'assessContentsFragment', true);

    /* Options for importing into the Question Bank
      */
    $this->setBlock(TEMPLATE_content, 'question_category', 'question_category_block');

    $assessHandler = new Survey_Main();
    $assessHandler->renderAssessContents($section['assess_id'], $this);

    $assess = $assessHandler->getByID($section['assess_id']);

    $this->setVar(array(
        'ASSESS_ASSESS_ID' => $section['assess_id'],
        'ASSESS_CATEGORY_ID' => $assess['category_id'],
        'SECTION_ID' => $question['section_id'],
        'SEQUENCE' => $question['sequence'],
        'QUESTION_ID' => $question['question_id'],
        'QUESTION' => Scrub::html($question['question']),
        'MARKS' => $question['marks'],
        'DISABLED_SELECTED' => '',
    ));

    /* Derive and render a category list
      */
    $questionBankCategoryHandler = new Survey_Question_Category();

    $questionCategoryList = new Survey_Question_Category_List($this, $assess['assess_id'], 0, $questionBankCategoryHandler->getPath());
    $questionCategoryList->render();
}


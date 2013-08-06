<?php
/**
 * Question modification and Answer modification.
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   2010 Alaress Pty Ltd
 * @link        http://www.alaress.com.au
 * @version     SVN: $Id$
 */

$factory = new Assess_Factory();
$assessHandler = new Assess_Main();
$sectionHandler = new Assess_Section();
$groupHandler = new Group();

/* If a question was submitted
 */
if (!empty($_POST)) {
    if (!is_numeric($_POST['type_id'])) {
        error(116);
    }

    $section = $sectionHandler->getByID($_POST['section_id']);
    $assess = $assessHandler->getByID($section['assess_id']);

    if ($assessHandler->isDeleted($section['assess_id'])) {
        error(112);
    }

    if (!$assessHandler->isOwner($assess['assess_id']) && ($groupHandler->getPermission($assess['category_id']) < WRITE_ACCESS)) {
        error(130);
    }

    if ($sectionHandler->isDeleted($section['section_id'])) {
        error(115);
    }

    $questionHandler = $factory->createQuestionModel($_POST['type_id']);
    $answerHandler = $factory->createAnswerModel($_POST['type_id']);

    $questionHandler->update($_POST);
    $answerHandler->update($_POST);

    /* Find the assess summary page
     */
    SitePage::localRedir('/assess/summary/' . $section['assess_id']);
} else {
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

    $questionHandler = new Assess_Question();
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
    $this->setFile('answerFormFragment', 'assess/answer/' . $factory->getQuestionType($question['type_id']) . '/modify.html');
    $this->setBlock('answerFormFragment', 'answerOption', 'answerOption_block');

    $answerHandler->modify($this, $question['question_id']);

    $this->parse('answerInputs_block', 'answerFormFragment', true);

    $this->setVar(array(
        'QUESTIONTYPE_TYPE_ID' => $currentQuestionType['type_id'],
        'QUESTIONTYPE_DESCRIPTION' => Scrub::text($currentQuestionType['description']),
        'QUESTIONTYPE_SELECTED' => $selected,
    ));

    /* Render contents of this Assess
     */
    if (empty($section)) {
        error(51);
    }

    /* Show this Assess' contents
    */
    $this->setFile('assessContentsFragment', 'assess/contents.html');
    $this->setBlock('assessContentsFragment', 'section', 'section_block');
    $this->setBlock('section', 'question', 'question_block');

    $this->setVar(array(
        'ASSESS_CATEGORY_ID' => $assess['category_id'],
    ));

    $this->parse('allQuestions_block', 'assessContentsFragment', true);

    $assessHandler = new Assess_Main();
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
    $questionBankCategoryHandler = new Assess_Question_Category();

    $questionCategoryList = new Assess_Question_Category_List($this, $assess['assess_id'], 0, $questionBankCategoryHandler->getPath());
    $questionCategoryList->render();
}


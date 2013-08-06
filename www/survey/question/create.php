<?php
/**
 * Question creation and Answer creation. When a QuestionType is selected such
 * as multiple choice, fill gaps etc. the related AnswerType handler is loaded;
 * actual Answer creation is delegated to the AnswerType handler.
 *
 * The forms used to input data to create an Answer are also loaded and inserted
 * dynamically by the templating.
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   2010 Alaress Pty Ltd
 * @link        http://www.alaress.com.au
 * @version     SVN: $Id$
 */

$assessHandler = new Survey_Main();
$sectionHandler = new Survey_Section();
$questionHandler = new Survey_Question();
$factory = new Survey_Factory();
$groupHandler = new Group();

/* If a question was submitted
 */
if (!empty($_POST)) {
    $section = $sectionHandler->getByID($_POST['section_id']);
    $assess = $assessHandler->getByID($section['assess_id']);

    if (!$assessHandler->isOwner($assess['assess_id']) && ($groupHandler->getPermission($assess['category_id']) < WRITE_ACCESS)) {
        error(130);
    }

    if ($assessHandler->isDeleted($section['assess_id'])) {
        error(112);
    }

    if ($sectionHandler->isDeleted($section['section_id'])) {
        error(115);
    }

    $questionHandler = $factory->createQuestionModel(Scrub::text($_POST['type_id']));
    $answerHandler = $factory->createAnswerModel(Scrub::text($_POST['type_id']));

    $question_id = $questionHandler->create($_POST);
    $answer_id = $answerHandler->create($_POST, $question_id);

    if (!$question_id) {
        error(131);
    }

    if (!$answer_id) {
        error(132);
    }

    /* Need to keep this so non-ajaxed questions still redirect
     */
    SitePage::localRedir('/survey/question/create/' . $_POST['section_id']);
} else {
    if (!is_numeric($this->arg[0])) {
        error(51);
    }

    $section = $sectionHandler->getByID($this->arg[0]);
    $assess = $assessHandler->getByID($section['assess_id']);

    if (!$assessHandler->isOwner($assess['assess_id']) && ($groupHandler->getPermission($assess['category_id']) < WRITE_ACCESS)) {
        error(130);
    }

    if ($assessHandler->isDeleted($section['assess_id'])) {
        error(112);
    }

    if ($sectionHandler->isDeleted($section['section_id'])) {
        error(115);
    }

    /* Render create page
     */
    $this->setFile(TEMPLATE_content, 'survey/question/create.html');
    $this->setBlock(TEMPLATE_content, 'answerInputs', 'answerInputs_block');
    $this->setBlock(TEMPLATE_content, 'allQuestions', 'allQuestions_block');
    $this->setBlock(TEMPLATE_content, 'questionType', 'questionType_block');
    $this->setTitle('Create Question');

    $this->setVar(array(
        'ASSESS_ASSESS_ID' => $section['assess_id'],
        'SECTION_ID' => $this->arg[0],
        'CURRENT_SECTION_TITLE' => Scrub::text($section['title']),
        'SEQUENCE' => $questionHandler->getNextSequence($section['section_id']),
        'QUESTION' => '',
        'MARKS' => '',
    ));

    /* If a particular question type has been selected; otherwise ignore it
     */
    if (is_numeric($this->arg[1])) {
        $answerHandler = $factory->createAnswerModel($this->arg[1]);

        $this->setVar(array(
            'IMPORT_SELECTED' => '',
            'DISABLED_SELECTED' => '',
        ));
    } else if ($this->arg[1] == 'import') {
        $this->setVar(array(
            'IMPORT_SELECTED' => 'selected=\'selected\'',
            'ANSWER_INPUTS' => ''
        ));
    } else {
        $this->setVar(array(
            'IMPORT_SELECTED' => '',
            'DISABLED_SELECTED' => 'selected=\'selected\'',
            'ANSWER_INPUTS' => $answerTemplate
        ));
    }

    /* Show question type options
     */
    $setTemplate = false;

    $questionTypes = $questionHandler->getAllQuestionTypes();

    foreach ($questionTypes as $currentQuestionType) {
        if (is_numeric($this->arg[1]) && ($this->arg[1] == $currentQuestionType['type_id'])) {
            $selected = 'selected=\'selected\'';

            /* Load answer template into answerOption block
             */
            $this->setFile('answerFormFragment', 'survey/answer/' . $factory->getQuestionType($this->arg[1]) . '/create.html');
            $this->setBlock('answerFormFragment', 'answerOption', 'answerOption_block');

            /* Render existing answers, or clear if none
             */
            $answerHandler->modify($this);

            $this->parse('answerInputs_block', 'answerFormFragment', true);

            /* We've set the template, so remember this
             */
            $setTemplate = true;
        } else {
            $selected = '';
        }

        $this->setVar(array(
            'QUESTIONTYPE_TYPE_ID' => $currentQuestionType['type_id'],
            'QUESTIONTYPE_DESCRIPTION' => Scrub::html($currentQuestionType['description']),
            'QUESTIONTYPE_SELECTED' => $selected,
        ));

        $this->parse('questionType_block', 'questionType', true);
    }

    /* If the answer template wasn't set, then we haven't chosen an answer type
     * so hide the block that would normally contain the answer type's form.
     */
    if (!$setTemplate) {
        $this->setVar('answerInputs_block', '');
    }

    /* Show this Survey' contents
     */
    $this->setFile('surveyContentsFragment', 'survey/contents.html');
    $this->setBlock('surveyContentsFragment', 'section', 'section_block');
    $this->setBlock('section', 'question', 'question_block');

    $survey = new Survey_Main();
    $survey->renderAssessContents($section['assess_id'], $this);

    $this->parse('allQuestions_block', 'surveyContentsFragment', true);
}

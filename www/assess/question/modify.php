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

$factory = new AssessFactory();

/* If a question was submitted
 */
if (!empty($_POST)) {
    $factory = new AssessFactory();

    if (!is_numeric($_POST['type_id'])) {
        error(116);
    }

    $questionHandler = $factory->createQuestionModel(scrubTEXT($_POST['type_id']));
    $answerHandler = $factory->createAnswerModel(scrubTEXT($_POST['type_id']));

    $questionHandler->update($_POST);
    $answerHandler->update($_POST);

    localRedir('/assess/question/modify/' . scrubTEXT($_POST['question_id']));
}
else {
    /* Render modify page
     */
    $this->setFile(TEMPLATE_content, 'assess/question/modify.html');
    $this->setTitle('Modify Question');
    $this->setBlock(TEMPLATE_content, 'answerInputs', 'answerInputs_block');
    $this->setBlock(TEMPLATE_content, 'questionType', 'questionType_block');
    $this->setBlock(TEMPLATE_content, 'allQuestions', 'allQuestions_block');
    
    /* Set question ID
     */
    if (empty($this->arg[0])) {
        error(115);
    }

    $questionHandler = new AssessQuestion();
    $question = $questionHandler->getByID($this->arg[0]);

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
    $sectionHandler = new AssessSection();
    $section = $sectionHandler->getByID($question['section_id']);

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
}
?>
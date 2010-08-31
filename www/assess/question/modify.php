<?php
require_once '../library/assess/assess.php';
require_once '../library/assess/assess-section.php';
require_once '../library/assess/assess-factory.php';

$factory = new AssessFactory();

/* If a question was submitted
 */
if (!empty($_POST)) {
    $factory = new AssessFactory();

    $questionHandler = $factory->createQuestionModel($_POST['type_id']);
    $answerHandler = $factory->createAnswerModel($_POST['type_id']);

    $questionHandler->update($_POST);
    $answerHandler->update($_POST);

    localRedir('/assess/question/modify/' . $_POST['question_id']);
}
else {
    /* Render modify page
     */
    $this->setFile(TEMPLATE_content, 'assess/question/modify.html');
    $this->setBlock(TEMPLATE_content, 'questionType', 'questionType_block');
    $this->setBlock(TEMPLATE_content, 'section', 'section_block');
    $this->setBlock('section', 'question', 'question_block');
    $this->setTitle('Modify Question');

    /* Set question ID
     */
    if (empty($this->arg[0])) {
        error(51);
    }

    $questionHandler = new AssessQuestion();
    $question = $questionHandler->getByID($this->arg[0]);
    
    $answerHandler = $factory->createAnswerModel($question['type_id']);

    /* Show question type options. For the Modify, preselect whichever it was
     */
    $questionTypes = $questionHandler->getQuestionTypes();

    foreach($questionTypes as $currentQuestionType) {
        if ($currentQuestionType['type_id'] == $question['type_id']) {

            /* Load answer template
             */
            $type = $factory->getQuestionType($question['type_id']);
            $answerTemplate = file_get_contents("../templates/core/assess/answer/$type/create.html");

            $this->setVar(array(
                'ANSWER_INPUTS' => $answerTemplate
            ));

            /* Render answers that exist for this question
             */
            $answerHandler->renderAnswers($this, $question['question_id']);

            $this->setVar(array(
                'QUESTIONTYPE_TYPE_ID' => $currentQuestionType['type_id'],
                'QUESTIONTYPE_DESCRIPTION' => $currentQuestionType['description'],
                'QUESTIONTYPE_SELECTED' => $selected,
            ));
        }
    }

    $this->setVar(array(
        'SECTION_ID' => $question['section_id'],
        'SEQUENCE' => $question['sequence'],
        'QUESTION_ID' => $question['question_id'],
        'QUESTION' => $question['question'],
        'MARKS' => $question['marks'],
        'DISABLED_SELECTED' => '',
    ));

    /* Render contents of this Assess
     */
    $sectionHandler = new AssessSection();
    $section = $sectionHandler->getByID($question['section_id']);

    if (empty($section)) {
        error(51);
    }

    $assess = new Assess();
    $assess->renderAssessContents($section['assess_id'], $this);
}
?>
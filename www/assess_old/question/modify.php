<?php
require_once '../library/assess/assess.php';
require_once '../library/assess/assess-section.php';
require_once '../library/assess/assess-factory.php';

/* If a question was submitted
 */
if (!empty($_POST)) {
//    $factory = new AssessFactory();
//
//    $questionHandler = $factory->createQuestionModel($_POST['type_id']);
//    $answerHandler = $factory->createAnswerModel($_POST['type_id']);
//
//    $question_id = $questionHandler->create($_POST);
//    $answer_id = $answerHandler->create($_POST);
//
//    if (!$question_id || !$answer_id) {
//        error();
//    }
//
//    localRedir('/assess/create/question/' . $_POST['section_id']);
}
else {
    /* Render modify page
     */
    $this->setFile(TEMPLATE_content, 'assess/create/question.html');
    $this->setBlock(TEMPLATE_content, 'questionType', 'questionType_block');
    $this->setTitle('Modify Question');

    /* Set question ID
     */
    if (empty($this->arg[0])) {
        error(51);
    }

    $questionHandler = new AssessQuestion();
    $question = $questionHandler->getByID($this->arg[0]);

    /* Show question type options. For the Modify, preselect whichever it was
     */
    $questionTypes = $questionHandler->getQuestionTypes();

    foreach($questionTypes as $currentQuestionType) {
        if ($currentQuestionType['type_id'] == $question['type_id']) {
            $selected = 'selected=\'selected\'';
        }
        else {
            $selected = '';
        }

        $this->setVar(array(
            'QUESTIONTYPE_TYPE_ID' => $currentQuestionType['type_id'],
            'QUESTIONTYPE_DESCRIPTION' => $currentQuestionType['description'],
            'QUESTIONTYPE_SELECTED' => $selected,
        ));

        $this->parse('questionType_block', 'questionType', true);
    }

    $this->setVar(array(
        'SECTION_ID' => $question['section_id'],
        'SEQUENCE' => $question['sequence'],
        'QUESTION' => $question['question'],
        'MARKS' => $question['marks'],
        'DISABLED_SELECTED' => '',
    ));
}
?>
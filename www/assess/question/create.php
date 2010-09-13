<?php
require_once('../library/assess/assess.php');
require_once('../library/assess/assess-section.php');
require_once('../library/assess/assess-factory.php');

$factory = new AssessFactory();

/* If a question was submitted
 */
if (!empty($_POST)) {
    $questionHandler = $factory->createQuestionModel($_POST['type_id']);
    $answerHandler = $factory->createAnswerModel($_POST['type_id']);

    $question_id = $questionHandler->create($_POST);
    $answer_id = $answerHandler->create($_POST, $question_id);

    if (!$question_id) {
        echo "Question could not be saved";
        error();
    }

    if (!$answer_id) {
        echo "Answer could not be saved";
        error();
    }

    localRedir('/assess/question/create/' . $_POST['section_id']);
}
else {
    /* Render create page
     */
    $this->setFile(TEMPLATE_content, 'assess/question/create.html');
    $this->setBlock(TEMPLATE_content, 'answerInputs', 'answerInputs_block');
    $this->setBlock(TEMPLATE_content, 'allQuestions', 'allQuestions_block');
    $this->setBlock(TEMPLATE_content, 'questionType', 'questionType_block');
    $this->setTitle('Create Question');

    /* Set section ID
     */
    if (empty($this->arg[0])) {
        error(51);
    }

    $sectionHandler = new AssessSection();
    $section = $sectionHandler->getByID($this->arg[0]);

    if (empty($section)) {
        error(51);
    }

    $questionHandler = new AssessQuestion();

    $this->setVar(array(
        'ASSESS_ASSESS_ID' => $section['assess_id'],
        'SECTION_ID' => $this->arg[0],
        'CURRENT_SECTION_TITLE' => $section['title'],
        'SEQUENCE' => $questionHandler->getNextSequence($section['section_id']),
        'QUESTION' => '',
        'MARKS' => '',
    ));

    /* If a particular question type has been selected; otherwise ignore it
     */
    if (is_numeric($this->arg[1])) {
        $answerHandler = $factory->createAnswerModel($this->arg[1]);
    }
    else {
        $this->setVar(array(
            'DISABLED_SELECTED' => 'selected=\'selected\'',
            'ANSWER_INPUTS' => $answerTemplate
        ));
    }

    /* Show question type options
     */
    $setTemplate = false;

    $questionTypes = $questionHandler->getAllQuestionTypes();

    foreach($questionTypes as $currentQuestionType) {
        if (is_numeric($this->arg[1]) && ($this->arg[1] == $currentQuestionType['type_id'])) {
            $selected = 'selected=\'selected\'';

            /* Load answer template into answerOption block
             */
            $this->setFile('answerFormFragment', 'assess/answer/' . $factory->getQuestionType($this->arg[1]) . '/create.html');
            $this->setBlock('answerFormFragment', 'answerOption', 'answerOption_block');

            /* Render existing answers, or clear if none
             */
            $answerHandler->modify($this);

            $this->parse('answerInputs_block', 'answerFormFragment', true);

            /* We've set the template, so remember this
             */
            $setTemplate = true;
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

    /* If the answer template wasn't set, then we haven't chosen an answer type
     * so hide the block that would normally contain the answer type's form.
     */
    if (!$setTemplate) {
        $this->setVar('answerInputs_block', '');
    }

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
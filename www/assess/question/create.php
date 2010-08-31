<?php
require_once '../library/assess/assess.php';
require_once '../library/assess/assess-section.php';
require_once '../library/assess/assess-factory.php';

$factory = new AssessFactory();

/* If a question was submitted
 */
if (!empty($_POST)) {
    $questionHandler = $factory->createQuestionModel($_POST['type_id']);
    $answerHandler = $factory->createAnswerModel($_POST['type_id']);

    $question_id = $questionHandler->create($_POST);
    $answer_id = $answerHandler->create($_POST, $question_id);

    if (!$question_id || !$answer_id) {
        error();
    }

    localRedir('/assess/question/create/' . $_POST['section_id']);
}
else {
    /* Render create page 
     */
    $this->setFile(TEMPLATE_content, 'assess/question/create.html');
    $this->setBlock(TEMPLATE_content, 'questionType', 'questionType_block');
    $this->setBlock(TEMPLATE_content, 'section', 'section_block');
    $this->setBlock('section', 'question', 'question_block');
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
        'SECTION_ID' => $this->arg[0],
        'SECTION_TITLE' => $section['title'],
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
    $questionTypes = $questionHandler->getQuestionTypes();

    foreach($questionTypes as $currentQuestionType) {
        if (is_numeric($this->arg[1]) && ($this->arg[1] == $currentQuestionType['type_id'])) {
            $selected = 'selected=\'selected\'';

            /* Load answer template
             */
            $type = $factory->getQuestionType($this->arg[1]);
            $answerTemplate = file_get_contents("../templates/core/assess/answer/$type/create.html");

            $this->setVar(array(
                'ANSWER_INPUTS' => $answerTemplate
            ));

            /* Render existing answers, or clear if none
             */
            $answerHandler->renderAnswers($this);
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

    /* Show this Assess' contents
     */
    $assess = new Assess();
    $assess->renderAssessContents($section['assess_id'], $this);
}

?>

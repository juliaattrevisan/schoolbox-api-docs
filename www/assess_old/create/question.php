<?php
require_once '../library/assess/assess.php';
require_once '../library/assess/assess-section.php';
require_once '../library/assess/assess-factory.php';

/* If a question was submitted
 */
if (!empty($_POST)) {
    $factory = new AssessFactory();

    $questionHandler = $factory->createQuestionModel($_POST['type_id']);
    $answerHandler = $factory->createAnswerModel($_POST['type_id']);

    $question_id = $questionHandler->create($_POST);
    $answer_id = $answerHandler->create($_POST);

    if (!$question_id || !$answer_id) {
        error();
    }

    localRedir('/assess/create/question/' . $_POST['section_id']);
}
else {
    /* Render create page 
     */
    $this->setFile(TEMPLATE_content, 'assess/create/question.html');
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
        'SECTION_ID' => $this->arg[0],
        'SEQUENCE' => $questionHandler->getNextSequence($section['section_id']),
        'QUESTION' => '',
        'MARKS' => '',
    ));

    /* Show question type options
     */
    $questionTypes = $questionHandler->getQuestionTypes();

    foreach($questionTypes as $currentQuestionType) {
        $this->setVar(array(
            'QUESTIONTYPE_TYPE_ID' => $currentQuestionType['type_id'],
            'QUESTIONTYPE_DESCRIPTION' => $currentQuestionType['description'],
            'QUESTIONTYPE_SELECTED' => '',
        ));
        
        $this->parse('questionType_block', 'questionType', true);
    }

    $this->setVar(array(
        'DISABLED_SELECTED' => 'selected=\'selected\'',
    ));

    /* Show this Assess' contents
     */
    $this->setBlock(TEMPLATE_content, 'section', 'section_block');
    $this->setBlock('section', 'question', 'question_block');
    
    $allQuestions = $questionHandler->getList($section['assess_id']);

    $sectionTitle = $allQuestions[0]['title'];

    for ($i=0; $i < count($allQuestions); $i++) {
        $currentQuestion = $allQuestions[$i];

        /* Parse out the previous Section's info if we're entering a new Section
         */
        if ($currentQuestion['title'] != $sectionTitle) {
            $this->setVar(array(
                'SECTION_TITLE' => $allQuestions[$i-1]['title'],
                'SECTION_SEQUENCE' => $allQuestions[$i-1]['sectionSequence'],
                'SECTION_SECTION_ID' => $allQuestions[$i-1]['section_id'],
            ));
            
            $this->parse('section_block', 'section', true);
            $this->setVar('question_block', '');
        }

        /* Put the current Question into a new Section and parse it out
         */
        $this->setVar(array(
            'QUESTION_SEQUENCE' => $currentQuestion['questionSequence'],
            'QUESTION_QUESTION' => $currentQuestion['question'],
            'QUESTION_QUESTION_ID' => $currentQuestion['question_id'],
        ));

        $this->parse('question_block', 'question', true);
    }

    /* Finally, parse out this Section's info
     */
    $this->setVar(array(
        'SECTION_TITLE' => $currentQuestion['title'],
        'SECTION_SEQUENCE' => $currentQuestion['sectionSequence'],
        'SECTION_SECTION_ID' => $currentQuestion['section_id'],
    ));

    $this->parse('section_block', 'section', true);
}

?>

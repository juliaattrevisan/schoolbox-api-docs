<?php
require_once '../library/assess/assess.php';
require_once '../library/assess/assess-question.php';
require_once '../library/assess/assess-section.php';

if (!empty($_POST)) {

    /* Create new Section
     */
    $section = new AssessSection();
    $section_id = $section->create($_POST);

    /* Redirect to question creation for this section
     */
    localRedir('/assess/question/create/' . $section_id);
}
else {
    /* Render create page
     */
    $this->setFile(TEMPLATE_content, 'assess/section/create.html');
    $this->setBlock(TEMPLATE_content, 'section', 'section_block');
    $this->setBlock('section', 'question', 'question_block');
    $this->setTitle('Create Section');

    if (empty($this->arg[0])) {
        error(51);
    }

    /* Find out whether the test exists to which you are attempting to add a
     * new Section
     */
    $assessHandler = new Assess();
    $assess = $assessHandler->getByID($this->arg[0]);

    if (empty($assess)) {
        error();
    }

    $section = new AssessSection();

    $this->setVar(array(
        'ASSESS_ASSESS_ID' => $this->arg[0],
        'ASSESS_TITLE' => $assess['title'],
        'SECTION_ID' => '',
        'SEQUENCE' => $section->getNextSequence($this->arg[0])
    ));

    /* Question contents
     */
    $this->setBlock(TEMPLATE_content, 'allQuestions', 'allQuestions_block');

    $assessHandler->renderAssessContents($assess['assess_id'], $this);

    $this->parse('allQuestions_block', 'allQuestions');
}

?>

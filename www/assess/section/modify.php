<?php
require_once '../library/assess/assess.php';
require_once '../library/assess/assess-question.php';
require_once '../library/assess/assess-section.php';

if (!empty($_POST)) {
    $sectionHandler = new AssessSection();

    $sectionHandler->update(array(
        'assess_id' => $_POST['assess_id'],
        'section_id' => $_POST['section_id'],
        'sequence' => $_POST['sequence'],
        'title' => $_POST['title'],
        'random' => $_POST['random'],
        'pool' => $_POST['pool'],
        'description' => $_POST['description']
    ));

    /* Redirect to Section modify
     */
    localRedir('/assess/section/modify/' . $_POST['section_id']);
}
else {
    /* Render modify page
     */
    $this->setFile(TEMPLATE_content, 'assess/section/modify.html');
    $this->setBlock(TEMPLATE_content, 'section', 'section_block');
    $this->setBlock('section', 'question', 'question_block');
    $this->setTitle('Create Section');

    if (empty($this->arg[0])) {
        error();
    }

    $sectionHandler = new AssessSection();
    $section = $sectionHandler->getByID($this->arg[0]);

    $assessHandler = new Assess();
    $assess = $assessHandler->getByID($section['assess_id']);

    $this->setVar(array(
        'ASSESS_ASSESS_ID' => $section['assess_id'],
        'ASSESS_TITLE' => $assess['title'],
        'SECTION_ID' => $section['section_id'],
        'TITLE' => $section['title'],
        'DESCRIPTION' => $section['description'],
        'SEQUENCE' => $section['sequence'],
        'RANDOM_SELECTED' => $section['random'] == 0 ? '' : 'checked=\'checked\'',
        'POOL' => $section['pool'],
    ));

    $this->setBlock(TEMPLATE_content, 'allQuestions', 'allQuestions_block');

    $assessHandler->renderAssessContents($assess['assess_id'], $this);

    $this->parse('allQuestions_block', 'allQuestions');
}

?>

<?php
require_once '../library/assess/assess.php';
require_once '../library/assess/assess-question.php';
require_once '../library/assess/assess-section.php';

if(!empty($_POST)) {

    /* Modify existing Section
     */
    if (is_numeric($_POST['section_id'])) {
        
    }

    /* Create new Section
     */
    else {
        $section = new AssessSection();
        $section_id = $section->create($_POST);

        /* Redirect to question creation for this section
         */
        localRedir('/assess/create/question/' . $section_id);
    }
}
else {
    /* Render create page
     */
    $this->setFile(TEMPLATE_content, 'assess/create/section.html');
    $this->setTitle('Create Section');

    if (empty($this->arg[0])) {
        error(51);
    }

    /* Find out whether the test exists to which you are attempting to add a
     * new Section
     */
    $assess = new Assess();
    $assess = $assess->getByID($this->arg[0]);

    if (empty($assess)) {
        error();
    }

    $section = new AssessSection();

    $this->setVar(array(
        'ASSESS_ID' => $this->arg[0],
        'SECTION_ID' => '',
        'SEQUENCE' => $section->getNextSequence($this->arg[0])
    ));
}

?>

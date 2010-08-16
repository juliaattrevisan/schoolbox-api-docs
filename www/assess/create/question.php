<?php
/* 
 * To change this template, choose Tools | Templates
 * and open the template in the editor.
 */

/* Inlcude libraries */
require_once '../library/group.php';
require_once '../library/assess/assess.php';
require_once '../library/assess/assess-section.php';
require_once '../library/assess/assess-question.php';

$group_model    = new Group();
$assess_model   = new Assess();
$section_model  = new AssessSection();

if(!empty($_POST)) {
    $question_file = "../library/assess/assess-question-{$_POST['type']}.php";
    $question_class = 'AssessQuestion' . ucfirst($_POST['type']);

    /* Instantiate specific question model */
    if(!is_readable($question_file)) {
        error();
    }

    require_once($question_file);
    $question_model = new $question_class;
    
    /* Create question */
    $question_id = $question_model->create($_POST);

    if(!$question_id) {
        error();
    }
} else {
    /* Render create page */
    $this->setFile(TEMPLATE_content, 'assess/create/question.html');
    $this->setTitle('Create Question');

    /* Set section ID */
    if(empty($this->arg[0])) {
        error(51);
    }
    
    $section = $section_model->getByID($this->arg[0]);
    
    if(empty($section)) {
        error(51);
    }
    
    $this->setVar('SECTION_ID', $section['id']);

    /* Get last question position */
    $question_model = new AssessQuestion();
    $question = $question_model->getLast($section['id']);

    if(!empty($question)) {
        $this->setVar('ORDER', $question['order'] + 1);
    } else {
        $this->setVar('ORDER', 0);
    }
}

?>

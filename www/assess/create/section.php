<?php
/* 
 * To change this template, choose Tools | Templates
 * and open the template in the editor.
 */

/* Inlcude libraries */
require_once '../library/group.php';
require_once '../library/assess/assess.php';
require_once '../library/assess/assess-section.php';

$group_model    = new Group();
$assess_model   = new Assess();
$section_model  = new AssessSection();

if(!empty($_POST)) {

} else {
    /* Render create page */
    $this->setFile(TEMPLATE_content, 'assess/create/section.html');
    $this->setTitle('Create Section');

    if(empty($this->arg[0])) {
        error(51);
    }

    $assess = $assess_model->getByID($this->arg[0]);

    if(empty($assess)) {
        error();
    }

    $this->setVar('ASSESS_ID', $assess['id']);

    /* Get last question position */
    $section_model = new AssessQuestion();
    $section = $section_model->getLast($assess['id']);

    if(!empty($section)) {
        $this->setVar('ORDER', $section['order'] + 1);
    } else {
        $this->setVar('ORDER', 0);
    }
}

?>

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

/* Include helpers */
require_once 'include/time.php';

$group_model    = new Group();
$assess_model   = new Assess();
$section_model  = new AssessSection();

if(!empty($_POST)) {

    /* Format date, time
     */
    $start = ausDateToUnix($_POST['start']['date'].' '.$_POST['start']['hour'].':'.$_POST['start']['minute'].' '.$_POST['start']['meridiem'], true);
    $finish = ausDateToUnix($_POST['finish']['date'].' '.$_POST['finish']['hour'].':'.$_POST['finish']['minute'].' '.$_POST['finish']['meridiem'], true);

    $assess_id = $assess_model->create(array(
        'user_id'     => $_SESSION['uid'],
        'title'       => $_POST['title'],
        'description' => $_POST['description'],
        'start'       => date(DATE_database, $start),
        'finish'      => date(DATE_database, $finish),
        'duration'    => ($_POST['duration']['hour'] * 60 + $_POST['duration']['minute']) * 60,
    ));

    if (!$assess_id) {
        error();
    }

    localRedir('/assess/section/create/' . $assess_id);
}
else {
    /* Render create page 
     */
    $this->setFile(TEMPLATE_content, 'assess/create.html');
    $this->setTitle('Create Assessment');

    $this->setVar(array(
        'START_DATE'  => date('m/d/Y'),
        'FINISH_DATE' => date('m/d/Y', time() + 604800)
    ));
}

?>

<?php
require_once('../library/assess/assess.php');
require_once('include/time.php');

$assessHandler = new Assess();

if (!empty($_POST)) {

    /* Format date, time
     */
    $start = ausDateToUnix($_POST['start']['date'].' '.$_POST['start']['hour'].':'.$_POST['start']['minute'].' '.$_POST['start']['meridiem'], true);
    $finish = ausDateToUnix($_POST['finish']['date'].' '.$_POST['finish']['hour'].':'.$_POST['finish']['minute'].' '.$_POST['finish']['meridiem'], true);

    $assess_id = $assessHandler->create(array(
        'user_id'     => $_SESSION['id'],
        'title'       => $_POST['title'],
        'description' => $_POST['description'],
        'start'       => date(DATE_database, $start),
        'finish'      => date(DATE_database, $finish),
        'duration'    => ($_POST['duration']['hour'] * 60 + $_POST['duration']['minute']) * 60,
        'allow_resit' => $_POST['allow_resit'],
    ));

    if (!$assess_id) {
        error();
    }

    localRedir('/assess/section/create/' . $assess_id);
}
else {
    /* Render create page 
     */
    $this->setFile(TEMPLATE_content, 'assess/modify.html');
    $this->setBlock(TEMPLATE_content, 'startHour', 'startHour_block');
    $this->setBlock(TEMPLATE_content, 'startMinute', 'startMinute_block');
    $this->setBlock(TEMPLATE_content, 'startMeridiem', 'startMeridiem_block');
    $this->setBlock(TEMPLATE_content, 'finishHour', 'finishHour_block');
    $this->setBlock(TEMPLATE_content, 'finishMinute', 'finishMinute_block');
    $this->setBlock(TEMPLATE_content, 'finishMeridiem', 'finishMeridiem_block');
    $this->setBlock(TEMPLATE_content, 'durationHour', 'durationHour_block');
    $this->setBlock(TEMPLATE_content, 'durationMinute', 'durationMinute_block');
    $this->setTitle('Manage Assessment');

    $this->setVar(array(
        'ACTION' => 'create',
        'ASSESS_ASSESS_ID' => '',
        'ASSESS_TITLE' => '',
        'ASSESS_DESCRIPTION' => '',
        'ASSESS_START_DATE'  => date('d/m/Y'),
        'ASSESS_FINISH_DATE' => date('d/m/Y', time() + 604800)
    ));

    /* Render starting times
     */
    for ($i=1; $i<=12; $i++) {
        $this->setVar(array(
            'START_HOUR_VALUE' => $i,
            'START_HOUR_SELECTED' => ''
        ));

        $this->parse('startHour_block', 'startHour', true);
    }

    for ($i=0; $i<=45; $i += 15) {
        $this->setVar(array(
            'START_MINUTE_VALUE' => $i == 0 ? $i = '00' : $i,
            'START_MINUTE_SELECTED' => ''
        ));

        $this->parse('startMinute_block', 'startMinute', true);
    }

    foreach (array('am', 'pm') as $meridiem) {
        $this->setVar(array(
            'START_MERIDIEM_VALUE' => $meridiem,
            'START_MERIDIEM_SELECTED' => ''
        ));

        $this->parse('startMeridiem_block', 'startMeridiem', true);
    }

    /* Render finish times
     */
    for ($i=1; $i<=12; $i++) {
        $this->setVar(array(
            'FINISH_HOUR_VALUE' => $i,
            'FINISH_HOUR_SELECTED' => ''
        ));

        $this->parse('finishHour_block', 'finishHour', true);
    }

    for ($i=0; $i<=45; $i += 15) {
        $this->setVar(array(
            'FINISH_MINUTE_VALUE' => $i == 0 ? $i = '00' : $i,
            'FINISH_MINUTE_SELECTED' => ''
        ));

        $this->parse('finishMinute_block', 'finishMinute', true);
    }

    foreach (array('am', 'pm') as $meridiem) {
        $this->setVar(array(
            'FINISH_MERIDIEM_VALUE' => $meridiem,
            'FINISH_MERIDIEM_SELECTED' => ''
        ));

        $this->parse('finishMeridiem_block', 'finishMeridiem', true);
    }

    /* Render duration
     */
    for ($i=0; $i<=3; $i++) {
        $this->setVar(array(
            'DURATION_HOUR_VALUE' => $i,
            'DURATION_HOUR_SELECTED' => ''
        ));

        $this->parse('durationHour_block', 'durationHour', true);
    }

    for ($i=0; $i<=45; $i += 15) {
        $this->setVar(array(
            'DURATION_MINUTE_VALUE' => $i == 0 ? $i = '00' : $i,
            'DURATION_MINUTE_SELECTED' => ''
        ));

        $this->parse('durationMinute_block', 'durationMinute', true);
    }

    /* Don't display questions contents, which appears in Modify
     */
    $this->setBlock(TEMPLATE_content, 'allQuestions', 'allQuestions_block');
    $this->setVar('allQuestions_block', '');
}

?>

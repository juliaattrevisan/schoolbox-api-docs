<?php
/**
 * Assessment creation. Assessments must always belong in a Category.
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   2010 Alaress Pty Ltd
 * @link        http://www.alaress.com.au
 * @version     SVN: $Id$
 */
require_once('../library/assess/Assess.php');
require_once('../library/group.php');
require_once('include/time.php');

$groupHandler = new Group();

if (!$groupHandler->isStaff()) {
    error(126);
}

$assessHandler = new Assess();

if (!empty($_POST)) {

    /* Format date, time
     */
    $start = new Alaress_Date_Format($_POST['start']['date'].' '.$_POST['start']['hour'].':'.$_POST['start']['minute'].' '.$_POST['start']['meridiem']);
    $finish = new Alaress_Date_Format($_POST['finish']['date'].' '.$_POST['finish']['hour'].':'.$_POST['finish']['minute'].' '.$_POST['finish']['meridiem']);

    /* Cater for unlimited duration tests. Can't use zero because of countdown
     * timer, so we use -1
     */
    $duration = ($_POST['duration']['hour'] * 60 + $_POST['duration']['minute']) * 60;

    if ($duration == 0) {
        $duration = Assess::UNLIMITED_DURATION;
    }

    $assess_id = $assessHandler->create(array(
        'category_id' => scrubTEXT($_POST['category_id']),
        'user_id' => $_SESSION['id'],
        'title' => scrubTEXT($_POST['title']),
        'description' => scrubTEXT($_POST['description']),
        'start' => $start->getDatabase(),
        'finish' => $finish->getDatabase(),
        'duration' => scrubTEXT($duration),
        'allow_resit' => scrubTEXT($_POST['allow_resit']),
		'availability' => 'assess',
        'created_at' => 'NOW()',
    ));

    if (!$assess_id) {
        error(112);
    }

    localRedir('/assess/section/create/' . $assess_id);
}
else {
    if (!is_numeric($this->arg[0])) {
        error(117);
    }
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
    $this->setBlock(TEMPLATE_content, 'importXML', 'importXML_block');
    $this->setTitle('Manage Assessment');

    $this->setVar(array(
        'ACTION' => 'create',
        'ASSESS_ASSESS_ID' => '',
        'ASSESS_CATEGORY_ID' => $this->arg[0],
        'ASSESS_TITLE' => '',
        'ASSESS_DESCRIPTION' => '',
        'ASSESS_START_DATE'  => date('d/m/Y'),
        'ASSESS_FINISH_DATE' => date('d/m/Y')
    ));

    /* Select default settings
     */
    $startHour = date('g', time()+3600);
    $startMeridiem = date('a', time()+3600);

    $finishHour = date('g', time()+2*3600);
    $finishMeridiem = date('a', time()+2*3600);

    /* Render starting times
     */
    for ($i=1; $i<=12; $i++) {
        if ($i == $startHour) {
            $selected = 'selected=\'selected\'';
        }
        else {
            $selected = '';
        }

        $this->setVar(array(
            'START_HOUR_VALUE' => $i,
            'START_HOUR_SELECTED' => $selected
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
        if ($meridiem == $startMeridiem) {
            $selected = 'selected=\'selected\'';
        }
        else {
            $selected = '';
        }

        $this->setVar(array(
            'START_MERIDIEM_VALUE' => $meridiem,
            'START_MERIDIEM_SELECTED' => $selected
        ));

        $this->parse('startMeridiem_block', 'startMeridiem', true);
    }

    /* Render finish times
     */
    for ($i=1; $i<=12; $i++) {
        if ($i == $finishHour) {
            $selected = 'selected=\'selected\'';
        }
        else {
            $selected = '';
        }

        $this->setVar(array(
            'FINISH_HOUR_VALUE' => $i,
            'FINISH_HOUR_SELECTED' => $selected
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
        if ($meridiem == $finishMeridiem) {
            $selected = 'selected=\'selected\'';
        }
        else {
            $selected = '';
        }

        $this->setVar(array(
            'FINISH_MERIDIEM_VALUE' => $meridiem,
            'FINISH_MERIDIEM_SELECTED' => $selected
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
    $this->parse('importXML_block', 'importXML');
}

?>

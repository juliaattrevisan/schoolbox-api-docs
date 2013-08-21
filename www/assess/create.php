<?php
/**
 * Assessment creation. Assessments must always belong in a Category.
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   2010 Alaress Pty Ltd
 * @link        http://www.alaress.com.au
 * @version     SVN: $Id$
 */
require_once('include/time.php');

$groupHandler = new Group();
$assessHandler = new Assess_Main();

if (!empty($_POST)) {
    if ($groupHandler->getPermission($_POST['category_id']) < WRITE_ACCESS) {
        error(126);
    }

    /* Format date, time
     */
    $start = new Alaress_Date_Format($_POST['start']['date'] . ' ' . $_POST['start']['hour'] . ':' . $_POST['start']['minute'] . ' ' . $_POST['start']['meridiem'], 'd/m/y');
    $finish = new Alaress_Date_Format($_POST['finish']['date'] . ' ' . $_POST['finish']['hour'] . ':' . $_POST['finish']['minute'] . ' ' . $_POST['finish']['meridiem'], 'd/m/y');

    /* Check that finish time cannot be before start time
      */
    if ($start->getUnixTimestamp() >= $finish->getUnixTimestamp()) {
        error(145);
    }

    /* Cater for unlimited duration tests. Can't use zero because of countdown
     * timer, so we use -1
     */
    $duration = ($_POST['duration']['hour'] * 60 + $_POST['duration']['minute']) * 60;

    if ($duration == 0) {
        $duration = Assess_Main::UNLIMITED_DURATION;
    }

    $assess_id = $assessHandler->create(array(
        'category_id' => $_POST['category_id'],
        'user_id' => $_SESSION['id'],
        'title' => $_POST['title'],
        'description' => $_POST['description'],
        'start' => $start->getDatabase(),
        'finish' => $finish->getDatabase(),
        'duration' => $duration,
        'max_attempts' => $_POST['max_attempts'],
        'availability' => 'assess',
        'created_at' => 'NOW()',
    ));

    if (!$assess_id) {
        error(112);
    }

    SitePage::localRedir('/assess/section/create/' . $assess_id);
} else {
    $category_id = $this->arg[0];
    $is_task     = ($this->arg[1] === 'task');

    if (!is_numeric($category_id)) {
        error(117);
    }

    if ($groupHandler->getPermission($category_id) < WRITE_ACCESS) {
        error(126);
    }

    /* Render create page
     */
    $this->setFile(TEMPLATE_content,   'assess/modify.html');
    $this->setBlock(TEMPLATE_content,  'assess_task',     'assess_task_block');
    $this->setBlock(TEMPLATE_content,  'assess_timespan', 'assess_timespan_block');
    $this->setBlock('assess_timespan', 'startHour', 'startHour_block');
    $this->setBlock('assess_timespan', 'startHour', 'startHour_block');
    $this->setBlock('assess_timespan', 'startMinute', 'startMinute_block');
    $this->setBlock('assess_timespan', 'startMeridiem', 'startMeridiem_block');
    $this->setBlock('assess_timespan', 'finishHour', 'finishHour_block');
    $this->setBlock('assess_timespan', 'finishMinute', 'finishMinute_block');
    $this->setBlock('assess_timespan', 'finishMeridiem', 'finishMeridiem_block');
    $this->setBlock(TEMPLATE_content,  'importXML', 'importXML_block');
    $this->setBlock(TEMPLATE_content,  'max_attempts', 'max_attempts_block');
    $this->setTitle('Manage Assessment');

    $this->setVar('assess_task_block',     '');
    $this->setVar('assess_timespan_block', '');

    $assessDate = new Alaress_Date_Format(time(), 'U');
    $assessEndDate = new Alaress_Date_Format(strtotime('+1 week'), 'U');

    $this->setVar(array(
        'ACTION' => 'create',
        'ASSESS_ASSESS_ID' => '',
        'ASSESS_CATEGORY_ID' => $category_id,
        'ASSESS_TITLE' => '',
        'ASSESS_DESCRIPTION' => '',
        'ASSESS_START_DATE' => $assessDate->getLocalShort(),
        'ASSESS_FINISH_DATE' => $assessEndDate->getLocalShort()
    ));

    /* Select default settings
     */
    $startHour = date('g', time() + 3600);
    $startMeridiem = date('a', time() + 3600);

    $finishHour = date('g', time() + 2 * 3600);
    $finishMeridiem = date('a', time() + 2 * 3600);

    /* Render starting times
     */
    for ($i = 1; $i <= 12; $i++) {
        if ($i == $startHour) {
            $selected = 'selected=\'selected\'';
        } else {
            $selected = '';
        }

        $this->setVar(array(
            'START_HOUR_VALUE' => $i,
            'START_HOUR_SELECTED' => $selected
        ));

        $this->parse('startHour_block', 'startHour', true);
    }

    for ($i = 0; $i <= 45; $i += 15) {
        $this->setVar(array(
            'START_MINUTE_VALUE' => $i == 0 ? $i = '00' : $i,
            'START_MINUTE_SELECTED' => ''
        ));

        $this->parse('startMinute_block', 'startMinute', true);
    }

    foreach (array('am', 'pm') as $meridiem) {
        if ($meridiem == $startMeridiem) {
            $selected = 'selected=\'selected\'';
        } else {
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
    for ($i = 1; $i <= 12; $i++) {
        if ($i == $finishHour) {
            $selected = 'selected=\'selected\'';
        } else {
            $selected = '';
        }

        $this->setVar(array(
            'FINISH_HOUR_VALUE' => $i,
            'FINISH_HOUR_SELECTED' => $selected
        ));

        $this->parse('finishHour_block', 'finishHour', true);
    }

    for ($i = 0; $i <= 45; $i += 15) {
        $this->setVar(array(
            'FINISH_MINUTE_VALUE' => $i == 0 ? $i = '00' : $i,
            'FINISH_MINUTE_SELECTED' => ''
        ));

        $this->parse('finishMinute_block', 'finishMinute', true);
    }

    foreach (array('am', 'pm') as $meridiem) {
        if ($meridiem == $finishMeridiem) {
            $selected = 'selected=\'selected\'';
        } else {
            $selected = '';
        }

        $this->setVar(array(
            'FINISH_MERIDIEM_VALUE' => $meridiem,
            'FINISH_MERIDIEM_SELECTED' => $selected
        ));

        $this->parse('finishMeridiem_block', 'finishMeridiem', true);
    }

    // Task or instance?
    if ($is_task) {
        $this->parse('assess_task_block', 'assess_task');
    }
    else {
        $this->parse('assess_timespan_block', 'assess_timespan');
    }

    /* Resit amounts
      */
    for ($i = 1; $i < 11; $i++) {
        $this->setVar(array(
            'MAX_ATTEMPTS_VALUE' => $i,
            'MAX_ATTEMPTS_CHECKED' => '',
        ));

        $this->parse('max_attempts_block', 'max_attempts', true);
    }

    /* Set default to be 1 hour
      */
    $this->setVar(array(
        'DURATION_HOUR_VALUE' => 0,
        'DURATION_MINUTE_VALUE' => 0,
    ));

    /* Don't display questions contents, which appears in Modify
     */
    $this->setBlock(TEMPLATE_content, 'allQuestions', 'allQuestions_block');
    $this->setVar('allQuestions_block', '');
    $this->parse('importXML_block', 'importXML');
}



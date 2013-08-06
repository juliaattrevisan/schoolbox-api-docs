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
    if (!is_numeric($this->arg[0])) {
        error(117);
    }

    if ($groupHandler->getPermission($this->arg[0]) < WRITE_ACCESS) {
        error(126);
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
    $this->setBlock(TEMPLATE_content, 'importXML', 'importXML_block');
    $this->setBlock(TEMPLATE_content, 'max_attempts', 'max_attempts_block');
    $this->setTitle('Manage Assessment');

    $assessDate = new Alaress_Date_Format(time(), 'U');
    $assessEndDate = new Alaress_Date_Format(strtotime('+1 week'), 'U');

    $this->setVar(array(
        'ACTION' => 'create',
        'ASSESS_ASSESS_ID' => '',
        'ASSESS_CATEGORY_ID' => $this->arg[0],
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



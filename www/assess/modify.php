<?php
/**
 * Assessment modification.
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   2010 Alaress Pty Ltd
 * @link        http://www.alaress.com.au
 * @version     SVN: $Id$
 */
require_once('../library/assess/assess.php');
require_once('include/time.php');

$assessHandler = new Assess();

if (!empty($_POST)) {

    /* Format date, time
     */
    $start = ausDateToUnix($_POST['start']['date'].' '.$_POST['start']['hour'].':'.$_POST['start']['minute'].' '.$_POST['start']['meridiem'], true);
    $finish = ausDateToUnix($_POST['finish']['date'].' '.$_POST['finish']['hour'].':'.$_POST['finish']['minute'].' '.$_POST['finish']['meridiem'], true);

    $assessHandler->update(array(
        'assess_id'   => $this->arg[0],
        'category_id' => scrubTEXT($_POST['category_id']),
        'user_id'     => $_SESSION['id'],
        'title'       => scrubTEXT($_POST['title']),
        'description' => scrubTEXT($_POST['description']),
        'start'       => date(DATE_database, $start),
        'finish'      => date(DATE_database, $finish),
        'duration'    => scrubTEXT(($_POST['duration']['hour'] * 60 + $_POST['duration']['minute']) * 60),
        'allow_resit' => scrubTEXT($_POST['allow_resit']),
    ));

    localRedir('/assess/modify/' . $this->arg[0]);
}
else {
    /* Render modify page
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
    $this->setBlock(TEMPLATE_content, 'allQuestions', 'allQuestions_block');
    $this->setTitle('Manage Assessment');

    $assess = $assessHandler->getByID($this->arg[0]);

    if (!$assess) {
        error(112);
    }

    $assessStartTime = strtotime($assess['start']);
    $assessFinishTime = strtotime($assess['finish']);

    $this->setVar(array(
        'ACTION' => 'modify',
        'ASSESS_ASSESS_ID' => $assess['assess_id'],
        'ASSESS_CATEGORY_ID' => $assess['category_id'],
        'ASSESS_TITLE' => $assess['title'],
        'ASSESS_DESCRIPTION' => $assess['description'],
        'ASSESS_START_DATE'  => date('d/m/Y', $assessStartTime),
        'ASSESS_FINISH_DATE' => date('d/m/Y', $assessFinishTime),
        'ALLOW_RESIT_CHECKED' => $assess['allow_resit'] == 'true' ? 'checked=\'checked\'' : '',
        'ASSESS_DURATION_CHECKED' => $assess['duration'] != 0 ? 'checked=\'checked\'' : '',
    ));

    /* Render starting times
     */
    for ($i=1; $i<=12; $i++) {
        $this->setVar(array(
            'START_HOUR_VALUE' => $i,
            'START_HOUR_SELECTED' => $i == date('g', $assessStartTime) ? 'selected=\'selected\'' : '',
        ));

        $this->parse('startHour_block', 'startHour', true);
    }

    for ($i=0; $i<=45; $i += 15) {
        $this->setVar(array(
            'START_MINUTE_VALUE' => $i == 0 ? $i = '00' : $i,
            'START_MINUTE_SELECTED' => $i == date('i', $assessStartTime) ? 'selected=\'selected\'' : '',
        ));

        $this->parse('startMinute_block', 'startMinute', true);
    }

    foreach (array('am', 'pm') as $meridiem) {
        $this->setVar(array(
            'START_MERIDIEM_VALUE' => $meridiem,
            'START_MERIDIEM_SELECTED' => $meridiem == date('a', $assessStartTime) ? 'selected=\'selected\'' : '',
        ));

        $this->parse('startMeridiem_block', 'startMeridiem', true);
    }

    /* Render finish times
     */
    for ($i=1; $i<=12; $i++) {
        $this->setVar(array(
            'FINISH_HOUR_VALUE' => $i,
            'FINISH_HOUR_SELECTED' => $i == date('g', $assessFinishTime) ? 'selected=\'selected\'' : '',
        ));

        $this->parse('finishHour_block', 'finishHour', true);
    }

    for ($i=0; $i<=45; $i += 15) {
        $this->setVar(array(
            'FINISH_MINUTE_VALUE' => $i == 0 ? $i = '00' : $i,
            'FINISH_MINUTE_SELECTED' => $i == date('i', $assessFinishTime) ? 'selected=\'selected\'' : '',
        ));

        $this->parse('finishMinute_block', 'finishMinute', true);
    }

    foreach (array('am', 'pm') as $meridiem) {
        $this->setVar(array(
            'FINISH_MERIDIEM_VALUE' => $meridiem,
            'FINISH_MERIDIEM_SELECTED' => $meridiem == date('a', $assessFinishTime) ? 'selected=\'selected\'' : '',
        ));

        $this->parse('finishMeridiem_block', 'finishMeridiem', true);
    }

    /* Render duration
     */
    $duration = $assessHandler->parse_sec2hms($assessHandler->sec2hms($assess['duration']));

    for ($i=0; $i<=3; $i++) {
        $this->setVar(array(
            'DURATION_HOUR_VALUE' => $i,
            'DURATION_HOUR_SELECTED' => $i == $duration['hours'] ? 'selected=\'selected\'' : '',
        ));

        $this->parse('durationHour_block', 'durationHour', true);
    }

    for ($i=0; $i<=45; $i += 15) {
        $this->setVar(array(
            'DURATION_MINUTE_VALUE' => $i == 0 ? $i = '00' : $i,
            'DURATION_MINUTE_SELECTED' => $i == $duration['minutes'] ? 'selected=\'selected\'' : '',
        ));

        $this->parse('durationMinute_block', 'durationMinute', true);
    }

    /* Show this Assess' contents
     */
    $this->setFile('assessContentsFragment', 'assess/contents.html');
    $this->setBlock('assessContentsFragment', 'section', 'section_block');
    $this->setBlock('section', 'question', 'question_block');

    $assess = new Assess();
    $assess->renderAssessContents($this->arg[0], $this);

    $this->parse('allQuestions_block', 'assessContentsFragment', true);
}
?>

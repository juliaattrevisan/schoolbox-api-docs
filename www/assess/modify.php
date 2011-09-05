<?php
/**
 * Assessment modification.
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
$assessHandler = new Assess();

if (!$groupHandler->isStaff()) {
    error(126);
}

if ($assessHandler->isDeleted($this->arg[0])) {
	error(112);
}

if (!$assessHandler->isOwner($this->arg[0]) && !$groupHandler->isSuperuser()) {
    error(125);
}

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

    $assessHandler->update(array(
        'assess_id'   => $this->arg[0],
        'category_id' => Scrub::text($_POST['category_id']),
        'user_id'     => $_SESSION['id'],
        'title'       => Scrub::text($_POST['title']),
        'description' => Scrub::text($_POST['description']),
        'start'       => $start->getDatabase(),
        'finish'      => $finish->getDatabase(),
        'duration'    => Scrub::text($duration),
        'allow_resit' => Scrub::text($_POST['allow_resit']),
    ));

    localRedir('/assess/summary/' . $this->arg[0]);
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
    $this->setBlock(TEMPLATE_content, 'importXML', 'importXML_block');
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
        'ALLOW_RESIT_CHECKED' => $assess['allow_resit'] == 1 ? 'checked=\'checked\'' : '',
        'ASSESS_DURATION_CHECKED' => $assess['duration'] >= 0 ? 'checked=\'checked\'' : '',
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

    /* Prevent importXML form from appearing
     */
    $this->setVar('importXML_block', '');
}
?>

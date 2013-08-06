<?php
/**
 * Assessment modification.
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   2010 Alaress Pty Ltd
 * @link        http://www.alaress.com.au
 * @version     SVN: $Id$
 */
require_once('include/time.php');

$groupHandler = new Group();
$assessHandler = new Assess_Main();

$assess = $assessHandler->getByID($this->arg[0]);

if ($assessHandler->isDeleted($this->arg[0])) {
    error(112);
}

if (!$assessHandler->isOwner($this->arg[0]) && ($groupHandler->getPermission($assess['category_id']) < WRITE_ACCESS)) {
    error(125);
}

if (!empty($_POST)) {

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

    $assessHandler->update(array(
        'assess_id' => $this->arg[0],
        'category_id' => $_POST['category_id'],
        'user_id' => $_SESSION['id'],
        'title' => $_POST['title'],
        'description' => $_POST['description'],
        'start' => $start->getDatabase(),
        'finish' => $finish->getDatabase(),
        'duration' => $duration,
        'max_attempts' => $_POST['max_attempts'],
    ));

    SitePage::localRedir('/assess/summary/' . $this->arg[0]);
} else {

    /* Render modify page
     */
    $this->setFile(TEMPLATE_content, 'assess/modify.html');
    $this->setBlock(TEMPLATE_content, 'startHour', 'startHour_block');
    $this->setBlock(TEMPLATE_content, 'startMinute', 'startMinute_block');
    $this->setBlock(TEMPLATE_content, 'startMeridiem', 'startMeridiem_block');
    $this->setBlock(TEMPLATE_content, 'finishHour', 'finishHour_block');
    $this->setBlock(TEMPLATE_content, 'finishMinute', 'finishMinute_block');
    $this->setBlock(TEMPLATE_content, 'finishMeridiem', 'finishMeridiem_block');
    $this->setBlock(TEMPLATE_content, 'allQuestions', 'allQuestions_block');
    $this->setBlock(TEMPLATE_content, 'importXML', 'importXML_block');
    $this->setBlock(TEMPLATE_content, 'max_attempts', 'max_attempts_block');
    $this->setTitle('Manage Assessment');

    $this->setVar('question_block', '');

    $assess = $assessHandler->getByID($this->arg[0]);

    if (!$assess) {
        error(112);
    }

    $assessStartTime = strtotime($assess['start']);
    $assessFinishTime = strtotime($assess['finish']);

    $assessStartDate = new Alaress_Date_Format($assessStartTime, 'U');
    $assessFinishDate = new Alaress_Date_Format($assessFinishTime, 'U');

    $this->setVar(array(
        'ACTION' => 'modify',
        'ASSESS_ASSESS_ID' => $assess['assess_id'],
        'ASSESS_CATEGORY_ID' => $assess['category_id'],
        'ASSESS_TITLE' => Scrub::text($assess['title']),
        'ASSESS_DESCRIPTION' => $assess['description'],
        'ASSESS_START_DATE' => $assessStartDate->getLocalShort(),
        'ASSESS_FINISH_DATE' => $assessFinishDate->getLocalShort(),
        'ALLOW_RESIT_CHECKED' => $assess['allow_resit'] == 1 ? 'checked=\'checked\'' : '',
        'ASSESS_DURATION_CHECKED' => $assess['duration'] >= 0 ? 'checked=\'checked\'' : '',
    ));

    /* Render starting times
     */
    for ($i = 1; $i <= 12; $i++) {
        $this->setVar(array(
            'START_HOUR_VALUE' => $i,
            'START_HOUR_SELECTED' => $i == date('g', $assessStartTime) ? 'selected=\'selected\'' : '',
        ));

        $this->parse('startHour_block', 'startHour', true);
    }

    for ($i = 0; $i <= 45; $i += 15) {
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
    for ($i = 1; $i <= 12; $i++) {
        $this->setVar(array(
            'FINISH_HOUR_VALUE' => $i,
            'FINISH_HOUR_SELECTED' => $i == date('g', $assessFinishTime) ? 'selected=\'selected\'' : '',
        ));

        $this->parse('finishHour_block', 'finishHour', true);
    }

    for ($i = 0; $i <= 45; $i += 15) {
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

    $this->setVar(array(
        'DURATION_HOUR_VALUE' => $duration['hours'],
        'DURATION_MINUTE_VALUE' => $duration['minutes'],
    ));

    /* Resit amounts
      */
    for ($i = 1; $i < 11; $i++) {
        $this->setVar(array(
            'MAX_ATTEMPTS_VALUE' => $i,
            'MAX_ATTEMPTS_CHECKED' => $i == $assess['max_attempts'] ? 'selected="selected"' : '',
        ));

        $this->parse('max_attempts_block', 'max_attempts', true);
    }

    /* Show this Assess' contents
     */
    $this->setFile('assessContentsFragment', 'assess/contents.html');
    $this->setBlock('assessContentsFragment', 'section', 'section_block');
    $this->setBlock('section', 'question', 'question_block');

    $assess = new Assess_Main();
    $assess->renderAssessContents($this->arg[0], $this);

    $this->parse('allQuestions_block', 'assessContentsFragment', true);

    /* Prevent importXML form from appearing
     */
    $this->setVar('importXML_block', '');
}


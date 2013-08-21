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

use Schoolbox\Assessment\Component\OnlineAssessment;
use Schoolbox\Assessment\Component\OnlineAssessmentMapper;

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

    /** @var OnlineAssessment $assessment */
    $assessment = OnlineAssessmentMapper::getInstance()->findById($this->arg[0]);
    $assessment
        ->setCategory($_POST['category_id'])
        ->setUser($_SESSION['id'])
        ->setTitle($_POST['title'])
        ->setDescription($_POST['description'])
        ->setStart($start)
        ->setFinish($finish)
        ->setDuration($duration)
        ->setMaxAttempts($_POST['max_attempts'])
    ;
    OnlineAssessmentMapper::getInstance()->save($assessment);
    SitePage::localRedir('/assess/summary/' . $this->arg[0]);
} else {

    /* Render modify page
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

    $this->setVar('question_block', '');
    $this->setVar('assess_task_block',     '');
    $this->setVar('assess_timespan_block', '');

    // Replace this with a damn mapper
    /** @var OnlineAssessment $assessment */
    try {
        $assessment = OnlineAssessmentMapper::getInstance()->findById($this->arg[0]);
    }
    catch (Exception $ex) {
        error(112);
    }
    $assess = $assessHandler->getByID($this->arg[0]);

    if (!$assess) {
        error(112);
    }

    $assessStartDate  = $assessment->getStart();
    $assessFinishDate = $assessment->getFinish();

    $this->setVar(array(
        'ACTION' => 'modify',
        'ASSESS_ASSESS_ID' => $assessment->getId(),
        'ASSESS_CATEGORY_ID' => $assessment->getCategory()->getId(),
        'ASSESS_TITLE' => Scrub::text($assessment->getTitle()),
        'ASSESS_DESCRIPTION' => Scrub::text($assessment->getDescription()),
        'ASSESS_START_DATE' => $assessStartDate->getLocalShort(),
        'ASSESS_FINISH_DATE' => $assessFinishDate->getLocalShort(),
        'ALLOW_RESIT_CHECKED' => $assessment->getMaxAttempts() == 1 ? 'checked=\'checked\'' : '',
        'ASSESS_DURATION_CHECKED' => $assessment->getDuration() >= 0 ? 'checked=\'checked\'' : '',
    ));

    /* Render starting times
     */
    for ($i = 1; $i <= 12; $i++) {
        $this->setVar(array(
            'START_HOUR_VALUE' => $i,
            'START_HOUR_SELECTED' => $i == $assessStartDate->getUsingFormat('g') ? 'selected=\'selected\'' : '',
        ));

        $this->parse('startHour_block', 'startHour', true);
    }

    for ($i = 0; $i <= 45; $i += 15) {
        $this->setVar(array(
            'START_MINUTE_VALUE' => $i == 0 ? $i = '00' : $i,
            'START_MINUTE_SELECTED' => $i == $assessStartDate->getUsingFormat('i') ? 'selected=\'selected\'' : '',
        ));

        $this->parse('startMinute_block', 'startMinute', true);
    }

    foreach (array('am', 'pm') as $meridiem) {
        $this->setVar(array(
            'START_MERIDIEM_VALUE' => $meridiem,
            'START_MERIDIEM_SELECTED' => $meridiem == $assessStartDate->getUsingFormat('a') ? 'selected=\'selected\'' : '',
        ));

        $this->parse('startMeridiem_block', 'startMeridiem', true);
    }

    /* Render finish times
     */
    for ($i = 1; $i <= 12; $i++) {
        $this->setVar(array(
            'FINISH_HOUR_VALUE' => $i,
            'FINISH_HOUR_SELECTED' => $i == $assessFinishDate->getUsingFormat('g') ? 'selected=\'selected\'' : '',
        ));

        $this->parse('finishHour_block', 'finishHour', true);
    }

    for ($i = 0; $i <= 45; $i += 15) {
        $this->setVar(array(
            'FINISH_MINUTE_VALUE' => $i == 0 ? $i = '00' : $i,
            'FINISH_MINUTE_SELECTED' => $i == $assessFinishDate->getUsingFormat('i') ? 'selected=\'selected\'' : '',
        ));

        $this->parse('finishMinute_block', 'finishMinute', true);
    }

    foreach (array('am', 'pm') as $meridiem) {
        $this->setVar(array(
            'FINISH_MERIDIEM_VALUE' => $meridiem,
            'FINISH_MERIDIEM_SELECTED' => $assessFinishDate->getUsingFormat('a') ? 'selected=\'selected\'' : '',
        ));

        $this->parse('finishMeridiem_block', 'finishMeridiem', true);
    }

    /* Render duration
     */
    $duration = $assessHandler->parse_sec2hms($assessHandler->sec2hms($assessment->getDuration()));

    $this->setVar(array(
        'DURATION_HOUR_VALUE' => $duration['hours'],
        'DURATION_MINUTE_VALUE' => $duration['minutes'],
    ));

    $this->parse('assess_timespan_block', 'assess_timespan');

    /* Resit amounts
      */
    for ($i = 1; $i < 11; $i++) {
        $this->setVar(array(
            'MAX_ATTEMPTS_VALUE' => $i,
            'MAX_ATTEMPTS_CHECKED' => $i == $assessment->getMaxAttempts() ? 'selected="selected"' : '',
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


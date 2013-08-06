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
$assessHandler = new Survey_Main();

$assess = $assessHandler->getByID($this->arg[0]);

if (!$assess) {
    error(112);
}

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

    /* Do not update user_id, in case a superuser makes changes to some other teacher's survey.
      * Doing so would change ownership; thus, changes made by a superuser would prevent the
      * original author from being able to modify it.
      */
    $assessHandler->update(array(
        'assess_id' => $this->arg[0],
        'category_id' => $_POST['category_id'],
        'title' => $_POST['title'],
        'description' => $_POST['description'],
        'start' => $start->getDatabase(),
        'finish' => $finish->getDatabase(),
        'duration' => Survey_Main::UNLIMITED_DURATION,
        'anonymous_result' => $_POST['anonymous_result'],
        'notify_author' => $_POST['notify_author'],
        'max_attempts' => 1,
    ));

    SitePage::localRedir('/survey/summary/' . $this->arg[0]);
} else {

    /* Render modify page
     */
    $this->setFile(TEMPLATE_content, 'survey/modify.html');
    $this->setBlock(TEMPLATE_content, 'startHour', 'startHour_block');
    $this->setBlock(TEMPLATE_content, 'startMinute', 'startMinute_block');
    $this->setBlock(TEMPLATE_content, 'startMeridiem', 'startMeridiem_block');
    $this->setBlock(TEMPLATE_content, 'finishHour', 'finishHour_block');
    $this->setBlock(TEMPLATE_content, 'finishMinute', 'finishMinute_block');
    $this->setBlock(TEMPLATE_content, 'finishMeridiem', 'finishMeridiem_block');
    $this->setBlock(TEMPLATE_content, 'allQuestions', 'allQuestions_block');
    $this->setBlock(TEMPLATE_content, 'durationHour', 'durationHour_block');
    $this->setBlock(TEMPLATE_content, 'durationMinute', 'durationMinute_block');
    $this->setBlock(TEMPLATE_content, 'importXML', 'importXML_block');
    $this->setTitle('Manage Survey');

    $assessStartDate = new Alaress_Date_Format(strtotime($assess['start']), 'U');
    $assessFinishDate = new Alaress_Date_Format(strtotime($assess['finish']), 'U');

    $this->setVar(array(
        'ACTION' => 'modify',
        'ASSESS_ASSESS_ID' => $assess['assess_id'],
        'ASSESS_CATEGORY_ID' => $assess['category_id'],
        'ASSESS_TITLE' => Scrub::text($assess['title']),
        'ASSESS_DESCRIPTION' => $assess['description'],
        'ASSESS_START_DATE' => $assessStartDate->getLocalShort(),
        'ASSESS_FINISH_DATE' => $assessFinishDate->getLocalShort(),
        'ASSESS_ANONYMOUS_RESULT_CHECKED' => $assess['anonymous_result'] ? 'checked="checked"' : '',
        'ASSESS_NOTIFY_AUTHOR_CHECKED' => $assess['notify_author'] ? 'checked="checked"' : '',
    ));

    /* Render starting times
     */
    $assessStartHour = $assessStartDate->getUsingFormat('h');
    $assessStartMinute = $assessStartDate->getUsingFormat('i');
    $assessStartMeridiem = $assessStartDate->getUsingFormat('a');

    for ($i = 1; $i <= 12; $i++) {
        $this->setVar(array(
            'START_HOUR_VALUE' => $i,
            'START_HOUR_SELECTED' => ($i == $assessStartHour) ? 'selected=\'selected\'' : '',
        ));

        $this->parse('startHour_block', 'startHour', true);
    }

    for ($i = 0; $i <= 45; $i += 15) {
        $this->setVar(array(
            'START_MINUTE_VALUE' => $i == 0 ? $i = '00' : $i,
            'START_MINUTE_SELECTED' => ($i == $assessStartMinute) ? 'selected=\'selected\'' : '',
        ));

        $this->parse('startMinute_block', 'startMinute', true);
    }

    foreach (array('am', 'pm') as $meridiem) {
        $this->setVar(array(
            'START_MERIDIEM_VALUE' => $meridiem,
            'START_MERIDIEM_SELECTED' => ($meridiem == $assessStartMeridiem) ? 'selected=\'selected\'' : '',
        ));

        $this->parse('startMeridiem_block', 'startMeridiem', true);
    }

    /* Render finish times
     */
    $assessFinishHour = $assessFinishDate->getUsingFormat('h');
    $assessFinishMinute = $assessFinishDate->getUsingFormat('i');
    $assessFinishMeridiem = $assessFinishDate->getUsingFormat('a');

    for ($i = 1; $i <= 12; $i++) {
        $this->setVar(array(
            'FINISH_HOUR_VALUE' => $i,
            'FINISH_HOUR_SELECTED' => ($i == $assessFinishHour) ? 'selected=\'selected\'' : '',
        ));

        $this->parse('finishHour_block', 'finishHour', true);
    }

    for ($i = 0; $i <= 45; $i += 15) {
        $this->setVar(array(
            'FINISH_MINUTE_VALUE' => $i == 0 ? $i = '00' : $i,
            'FINISH_MINUTE_SELECTED' => ($i == $assessFinishMinute) ? 'selected=\'selected\'' : '',
        ));

        $this->parse('finishMinute_block', 'finishMinute', true);
    }

    foreach (array('am', 'pm') as $meridiem) {
        $this->setVar(array(
            'FINISH_MERIDIEM_VALUE' => $meridiem,
            'FINISH_MERIDIEM_SELECTED' => ($meridiem == $assessFinishMeridiem) ? 'selected=\'selected\'' : '',
        ));

        $this->parse('finishMeridiem_block', 'finishMeridiem', true);
    }

    /* Show this Assess' contents
      */
    $this->setFile('assessContentsFragment', 'survey/contents.html');
    $this->setBlock('assessContentsFragment', 'section', 'section_block');
    $this->setBlock('section', 'question', 'question_block');

    $assess = new Survey_Main();
    $assess->renderAssessContents($this->arg[0], $this);

    $this->parse('allQuestions_block', 'assessContentsFragment', true);

    /* Prevent importXML form from appearing
     */
    $this->setVar('importXML_block', '');
}


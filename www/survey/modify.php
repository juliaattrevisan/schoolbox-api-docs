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
    $start = new Alaress_Date_Format($_POST['start']['date'] . ' ' . $_POST['start']['hour'] . ':' . $_POST['start']['minute'] . ' ' . $_POST['start']['meridiem'], 'd/m/Y h:i a');
    $finish = new Alaress_Date_Format($_POST['finish']['date'] . ' ' . $_POST['finish']['hour'] . ':' . $_POST['finish']['minute'] . ' ' . $_POST['finish']['meridiem'], 'd/m/Y h:i a');

	/* Check for valid dates
	 */
	if (!$start->isValid() || !$finish->isValid()) {
		error(15);
	}

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
        'max_attempts' => $_POST['max_attempts'],
    ));

    SitePage::localRedir('/survey/summary/' . $this->arg[0]);
}
else {

    /* Render modify page
     */
    $this->setFile(TEMPLATE_content, 'survey/modify.html');
    $this->setBlock(TEMPLATE_content, 'startMeridiem', 'startMeridiem_block');
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
        'ASSESS_ACTION'    => 'Modify',
        'ASSESS_KIND'      => 'Survey',
        'ASSESS_KIND_LC'   => 'survey',
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
    $assessStartHour = $assessStartDate->get24Hour();
    $assessStartMinute = $assessStartDate->getUsingFormat('i');
    $assessStartMeridiem = $assessStartDate->getMeridiem();

	$this->setVar(array(
		'START_HOUR_VALUE' => $assessStartHour,
		'START_MINUTE_VALUE' => $assessStartMinute,
	));

	foreach (array('AM', 'PM') as $meridiem) {
        $this->setVar(array(
            'START_MERIDIEM_VALUE' => $meridiem,
            'START_MERIDIEM_SELECTED' => ($meridiem == $assessStartMeridiem) ? 'selected=\'selected\'' : '',
        ));

        $this->parse('startMeridiem_block', 'startMeridiem', true);
    }

    /* Render finish times
     */
    $assessFinishHour = $assessFinishDate->get24Hour();
    $assessFinishMinute = $assessFinishDate->getUsingFormat('i');
    $assessFinishMeridiem = $assessFinishDate->getMeridiem();

	/* Render finish times
	 */
	$this->setVar(array(
		'FINISH_HOUR_VALUE' => $assessFinishHour,
		'FINISH_MINUTE_VALUE' => $assessFinishMinute,
	));

	foreach (array('AM', 'PM') as $meridiem) {
        $this->setVar(array(
            'FINISH_MERIDIEM_VALUE' => $meridiem,
            'FINISH_MERIDIEM_SELECTED' => ($meridiem == $assessFinishMeridiem) ? 'selected=\'selected\'' : '',
        ));

        $this->parse('finishMeridiem_block', 'finishMeridiem', true);
    }

	/* Resit amounts
	 */
	$this->setVar(array(
		'MAX_ATTEMPTS_CHECKED' => $assess['max_attempts'] == 0 ? 'checked="checked"' : '',
	));

    /* Show this Survey's contents
     */
    $this->setFile('assessContentsFragment', 'assess/contents.html');

    $assess = new Survey_Main();
    $assess->renderAssessContents($this->arg[0], $this);

    $this->parse('allQuestions_block', 'assessContentsFragment', true);

    /* Prevent importXML form from appearing
     */
    $this->setVar('importXML_block', '');
}

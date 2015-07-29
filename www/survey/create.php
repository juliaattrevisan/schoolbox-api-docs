<?php
/**
 * Assessment creation. Assessments must always belong in a Category.
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   2010 Alaress Pty Ltd
 * @link        http://www.alaress.com.au
 * @version     SVN: $Id$
 */

use Schoolbox\Resource\Folder\Mapper as FolderMapper;

$groupHandler  = new Group();
$assessHandler = new Survey_Main();

if (!empty($_POST)) {
    if ($groupHandler->getPermission(Scrub::text($_POST['category_id'])) < WRITE_ACCESS) {
        error(126);
    }

    /* Format date, time
     */
    $start = Alaress_Date_Format::newLocalShort($_POST['start']['date'])
        ->set12Hour($_POST['start']['hour'], $_POST['start']['meridiem'])
        ->setMinute($_POST['start']['minute'])
    ;
    $finish = Alaress_Date_Format::newLocalShort($_POST['finish']['date'])
        ->set12Hour($_POST['finish']['hour'], $_POST['finish']['meridiem'])
        ->setMinute($_POST['finish']['minute'])
    ;

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

    $assess_id = $assessHandler->create(
        array(
            'category_id'        => $_POST['category_id'],
            'user_id'            => $_SESSION['id'],
            'title'              => $_POST['title'],
            'description'        => $_POST['description'],
            'start'              => $start->getDatabase(),
            'finish'             => $finish->getDatabase(),
            'duration'           => Survey_Main::UNLIMITED_DURATION,
            'availability'       => 'survey',
            'anonymous_result'   => $_POST['anonymous_result'],
            'notify_author'      => $_POST['notify_author'],
            'max_attempts'       => $_POST['max_attempts'],
            'completion_message' => $_POST['completion_message'],
            'created_at'         => 'NOW()',
        )
    );

    if (!$assess_id) {
        error(112);
    }

    SitePage::localRedir('/survey/section/create/' . $assess_id);
} else {
    if (!is_numeric($this->arg[0])) {
        error(13);
    }

    try {
        $folderMapper = new FolderMapper();
        $folder = $folderMapper->findById($this->arg[0]);
    } catch (\Schoolbox\Mapper\Exception $e) {
        error(13);
        exit;
    }

    if ($groupHandler->getPermission($this->arg[0]) < WRITE_ACCESS) {
        error(126);
    }

    /* Render create page
     */
    $this->setFile(TEMPLATE_content, 'survey/modify.html');
    $this->setBlock(TEMPLATE_content, 'startMeridiem', 'startMeridiem_block');
    $this->setBlock(TEMPLATE_content, 'finishMeridiem', 'finishMeridiem_block');
    $this->setBlock(TEMPLATE_content, 'durationHour', 'durationHour_block');
    $this->setBlock(TEMPLATE_content, 'durationMinute', 'durationMinute_block');
    $this->setBlock(TEMPLATE_content, 'importXML', 'importXML_block');
    $this->setBlock(TEMPLATE_content, 'has_due_date', 'has_due_date_block');

    $this->setVar(array(
        'has_due_date_block' => ''
    ));

    $this->setTitle('Manage Survey');

    $assessDate    = new Alaress_Date_Format(time(), 'U');
    $assessEndDate = new Alaress_Date_Format(strtotime('+1 week'), 'U');

    $this->setVar(
        array(
            'ACTION'                          => 'create',
            'ASSESS_ACTION'                   => 'Create',
            'ASSESS_KIND_LC'                  => 'survey',
            'ASSESS_KIND'                     => 'Survey',
            'ASSESS_ASSESS_ID'                => '',
            'ASSESS_CATEGORY_ID'              => $folder->getId(),
            'ASSESS_CATEGORY_NAME'            => $folder->getName(),
            'ASSESS_TITLE'                    => '',
            'ASSESS_DESCRIPTION'              => '',
            'ASSESS_START_DATE'               => $assessDate->getLocalShort(),
            'ASSESS_FINISH_DATE'              => $assessEndDate->getLocalShort(),
            'ASSESS_ANONYMOUS_RESULT_CHECKED' => '',
            'ASSESS_NOTIFY_AUTHOR_CHECKED'    => '',
            'ASSESS_COMPLETION_MESSAGE'       => '',
            'HAS_ALL_QUESTIONS'               => 'large-12'
        )
    );

    /* Select default settings
     */
    $startHour     = date('g', time() + 3600);
    $startMinute   = '00';
    $startMeridiem = date('A', time() + 3600);

    $finishHour     = date('g', time() + 2 * 3600);
    $finishMinute   = '00';
    $finishMeridiem = date('A', time() + 2 * 3600);

    /* Render starting times
     */
    $this->setVar(
        array(
            'START_HOUR_VALUE'   => $startHour,
            'START_MINUTE_VALUE' => $startMinute,
        )
    );

    foreach (array('AM', 'PM') as $meridiem) {
        if ($meridiem == $startMeridiem) {
            $selected = 'selected=\'selected\'';
        } else {
            $selected = '';
        }

        $this->setVar(
            array(
                'START_MERIDIEM_VALUE'    => $meridiem,
                'START_MERIDIEM_SELECTED' => $selected
            )
        );

        $this->parse('startMeridiem_block', 'startMeridiem', true);
    }

    /* Render finish times
     */
    $this->setVar(
        array(
            'FINISH_HOUR_VALUE'   => $finishHour,
            'FINISH_MINUTE_VALUE' => $finishMinute,
        )
    );

    foreach (array('AM', 'PM') as $meridiem) {
        if ($meridiem == $finishMeridiem) {
            $selected = 'selected=\'selected\'';
        } else {
            $selected = '';
        }

        $this->setVar(
            array(
                'FINISH_MERIDIEM_VALUE'    => $meridiem,
                'FINISH_MERIDIEM_SELECTED' => $selected
            )
        );

        $this->parse('finishMeridiem_block', 'finishMeridiem', true);
    }

    /* Resit amounts
     */
    $this->setVar(
        array(
            'MAX_ATTEMPTS_CHECKED' => '',
        )
    );

    /* Don't display questions contents, which appears in Modify
     */
    $this->setBlock(TEMPLATE_content, 'allQuestions', 'allQuestions_block');
    $this->setVar('allQuestions_block', '');
    $this->parse('importXML_block', 'importXML');

    /* Render out breadcrumbs
     */
    $this->setBlock(TEMPLATE_content, 'breadcrumb', 'breadcrumb_block');

    $allBreadcrumbs = $folderMapper->breadcrumb($folder, true);
    $i = 1;

    foreach ($allBreadcrumbs as $currentBreadcrumb) {
        if ($i == $allBreadcrumbs->count()) {
            $active = 'active';
        }
        else {
            $active = '';
        }

        $this->setVar(array(
            'BREADCRUMB_FOLDER_ID' => $currentBreadcrumb->getId(),
            'BREADCRUMB_FOLDER_NAME' => $currentBreadcrumb->getName(),
            'BREADCRUMB_ACTIVE' => $active,
        ));

        $this->parse('breadcrumb_block', 'breadcrumb', true);

        $i++;
    }
}


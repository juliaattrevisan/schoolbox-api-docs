<?php
/**
 * Assessment modification.
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   2010 Alaress Pty Ltd
 * @link        http://www.alaress.com.au
 * @version     SVN: $Id$
 */
use Schoolbox\Resource\Folder\Mapper as FolderMapper;
use Schoolbox\Breadcrumbs;

$groupHandler  = new Group();
$surveyHandler = new Survey_Main();

if (!$surveyHandler->isValidUrlAccess($this->arg[0])) {
    $this->pageNotFound();
}

$survey = $surveyHandler->getByID($this->arg[0]);

if ($surveyHandler->isDeleted($this->arg[0])) {
    error(112);
}

if (!$surveyHandler->isOwner($this->arg[0]) && ($groupHandler->getPermission($survey['category_id']) < WRITE_ACCESS)) {
    error(125);
}

if (!empty($_POST)) {

    /* Format date, time
     */
    $start = Alaress_Date_Format::newLocal($_POST['start']['date']);
    $finish = Alaress_Date_Format::newLocal($_POST['finish']['date']);

    /* Check for valid dates
     */
    if (!$start->isValid() || !$finish->isValid()) {
        error(15);
    }

    /* Check that finish time cannot be before start time
      */
    if ($start->getUnixTimeStamp() >= $finish->getUnixTimeStamp()) {
        error(145);
    }

    /* Do not update user_id, in case a superuser makes changes to some other teacher's survey.
      * Doing so would change ownership; thus, changes made by a superuser would prevent the
      * original author from being able to modify it.
      */
    $surveyHandler->update(
        array(
            'assess_id'          => $this->arg[0],
            'category_id'        => $_POST['category_id'],
            'title'              => $_POST['title'],
            'description'        => $_POST['description'],
            'start'              => $start->getDatabase(),
            'finish'             => $finish->getDatabase(),
            'duration'           => Survey_Main::UNLIMITED_DURATION,
            'anonymous_result'   => $_POST['anonymous_result'],
            'notify_author'      => $_POST['notify_author'],
            'max_attempts'       => $_POST['max_attempts'],
            'completion_message' => $_POST['completion_message'],
        )
    );

    SitePage::localRedir('/survey/summary/' . $this->arg[0]);
} else {

    /* Render modify page
     */
    $this->setFile(TEMPLATE_content, 'survey/modify.html');
    $this->setBlock(TEMPLATE_content, 'durationHour', 'durationHour_block');
    $this->setBlock(TEMPLATE_content, 'durationMinute', 'durationMinute_block');
    $this->setBlock(TEMPLATE_content, 'importXML', 'importXML_block');
    $this->setBlock(TEMPLATE_content, 'has_due_date', 'has_due_date_block');

    $this->setTitle('Manage Survey');

    $assessStartDate  = new Alaress_Date_Format(strtotime($survey['start']), 'U');
    $assessFinishDate = new Alaress_Date_Format(strtotime($survey['finish']), 'U');

    $folderMapper = new FolderMapper();

    try {
        $folder = $folderMapper->findById($survey['category_id']);
    }
    catch (\Exception $e) {
        error(117);
    }

    $this->setVar(
        array(
            'ACTION'                          => 'modify',
            'ASSESS_HEADING'                  => Scrub::text($survey['title']),
            'ASSESS_ASSESS_ID'                => $survey['assess_id'],
            'ASSESS_ACTION'                   => 'Modify',
            'ASSESS_KIND'                     => 'Survey',
            'ASSESS_KIND_LC'                  => 'survey',
            'ASSESS_CATEGORY_ID'              => $folder->getId(),
            'ASSESS_CATEGORY_NAME'            => Scrub::text($folder->getName()),
            'ASSESS_TITLE'                    => Scrub::text($survey['title']),
            'ASSESS_DESCRIPTION'              => $survey['description'],
            'ASSESS_START_DATE'               => $assessStartDate->getLocal(),
            'ASSESS_FINISH_DATE'              => $assessFinishDate->getLocal(),
            'ASSESS_ANONYMOUS_RESULT_CHECKED' => $survey['anonymous_result'] ? 'checked="checked"' : '',
            'ASSESS_NOTIFY_AUTHOR_CHECKED'    => $survey['notify_author'] ? 'checked="checked"' : '',
            'ASSESS_COMPLETION_MESSAGE'       => Scrub::text($survey['completion_message']),
            'HAS_ALL_QUESTIONS'               => 'large-8',
        )
    );

    /* Resit amounts
     */
    $this->setVar(
        array(
            'MAX_ATTEMPTS_CHECKED' => $survey['max_attempts'] == 0 ? 'checked="checked"' : '',
        )
    );

    /* Prevent importXML form from appearing
     */
    $this->setVar('importXML_block', '');

    /* Render out breadcrumbs
     */
    $trail = new Breadcrumbs(Schoolbox\Resource\Folder\Mapper::getInstance());
    $trail->renderIntoSitePage($this, 'BREADCRUMB', [
        [
            'text' => $folder->getName(),
            'href' => "/homepage/{$folder->getId()}",
            'class' => '',
        ],
        [
            'text' => $survey['title'],
            'href' => "/survey/summary/{$survey['assess_id']}",
            'class' => 'active',
        ]
    ]);
}

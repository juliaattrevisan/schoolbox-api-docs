<?php
require_once(dirname(__FILE__) . '/../../include/Bootstrap.php');

// DETERMINE A COURSE OF ACTION

// ID provided, DueWork already exists
$is_task = isset($_POST['task_title']);
if (is_numeric($_POST['id']) && ($is_task || $_POST['due_date']) && is_numeric($_POST['category'])) {
    $action = 'update';
}
// no ID, create a new DueWork
elseif (($is_task || $_POST['due_date']) && is_numeric($_POST['category'])) {
    $action = 'create';
}
// deletion flagged, delete existing DueWork
elseif (isset($_GET['deleteBox']) && is_numeric($_GET['deleteBox'])) {
    $action = 'delete';
}
// data was POSTed, but nothing received: file upload size exceeded
elseif (upload_failed()) {
    error(10);
}
// unknown case, bug out
else {
    error(2);
}

// create required objects
$page = new SitePage();

use Schoolbox\Assessment\Component\SubmissionBox;
use Schoolbox\Assessment\Component\SubmissionBoxMapper;
use Schoolbox\Assessment\Component\SubmissionBox\Notification;
use Schoolbox\Assessment\Course\Task;
use Schoolbox\Assessment\Course\TaskMapper;

$mapper = new SubmissionBoxMapper();

if($action == 'delete') {
    $mapper->delete($mapper->findById($_GET['deleteBox']));
    $page->localRedir("/submission/listSubmission/");
}

// CREATE OR UPDATE

// localise important variables
$submissionBoxID = isset($_POST['id']) && $_POST['id'] ? $_POST['id'] : 0;
$submission = !empty($submissionBoxID) ? $mapper->findById($submissionBoxID) : new SubmissionBox();
$groupHandler = new Group();
//
// Auth: submission's category must be writable by this user.
$check_id = $submission->getCategory()->getId();
if (empty($check_id)) {
    $check_id = $_POST['category'];
}
if (empty($check_id) || $groupHandler->getPermission($check_id) < WRITE_ACCESS) {
    error(19);
}

$categoryID = $_POST['category'];
$markTypeID = $_POST['marktype'];
$resultSetID = $_POST['resultset'];

$acceptSubmissions = !empty($_POST['accept_submission']);
$multiSubmissions = !empty($_POST['multi_submission']);

$descriptors = $_POST['des'];
$criteria = $_POST['crit'];
$useDescriptors = $resultSetID == 0;

//
// Attach any files that were passed (they will have been saved in fileForm)
if (!empty($_POST['file_ids']) && is_array($_POST['file_ids'])) {
    foreach ($_POST['file_ids'] as $file_id) {
        if (!empty($file_id)) {
            $submission->getFileLinks()->addFromFileId($file_id);
        }
    }
}
//
// Legacy file ID (if passed through createSubmission/new/{catId}/{fileId})
// submission's save method stores this as a storage file anyway.
if (!empty($_POST['fileId'])) {
    $submission->setLegacyFileId($_POST['fileId']);
}


/* Prevent 12pm to 12am-next-day bug by checking that the hour submitted is
 * not equal to 12. This stops the date incrementing to the next day.
 *
 * Also set the time to the start or end of the day if all_day is set
 */

$dueDate = generateDate('due', '23:59:59');

// if we're not accepting submissions. override the open date to now() and late date to due date
if(!$acceptSubmissions) {
    $openDate = new Alaress_Date_Format(time(), 'U');
    $lateDate = $dueDate;
}
else {
    $openDate = generateDate('open', '00:00:00');
    $lateDate = generateDate('late', '23:59:59');
}

// If not a task: check if any of the dates failed to parse
if ($is_task) {
    if (strlen(trim($_POST['task_title'])) === 0) {
        error(2);
    }
}
else {
    if (!($openDate->isValid() && $dueDate->isValid() && $lateDate->isValid())) {
        error(15);
    }

    // late date is greater than due date
    if (!empty($_POST['accept_submission']) && $dueDate->getDatabase() > $lateDate->getDatabase()) {
        error(15);
    }
}

$markMax = !$_POST['markvalue'] ? '100' : $_POST['markvalue']; // if not set, default to 100

// if Total Mark selected, mark value must be specified
if ($markTypeID == SubmissionBox\MarkType::TYPE_RAW && empty($_POST['markvalue'])) {
    error(2);
}
// if Rubric selected, generate the objects needed
elseif($markTypeID == SubmissionBox\MarkType::TYPE_RUBRIC) {
    $rubricFactory = new DueWork_Rubric_Factory($criteria);

    if($useDescriptors) {
        $specification = array();
        foreach($descriptors as $critID => $values) {
            if($criteria[$critID]) {
                foreach($values as $k => $v) {
                    $specification[$critID][$k] = new DueWork_Rubric_Descriptor($v, $k);
                }
            }
        }
        $rubric = $rubricFactory->buildRubric($specification, $submissionBoxID);
    }
    else {
        $rubric = $rubricFactory->buildRubric($resultSetID, $submissionBoxID);
    }

    // need to determine if the resultset is different, and if it is, that any marks already given are invalidated
    if (!empty($submissionBoxID)) {
        $this_submission = $mapper->findById($submissionBoxID);
        if ($resultSetID != $this_submission->getRubricResultSet()) {
            $invalidateExistingMarks = true;
        }
    }

    $markMax = $rubric->getCriteriaCount() * ($rubric->getResultCount() - 1);
}


// define submissionBox 'object': elements common to both scenarios
$submission->setAcceptOnline(!empty($_POST['accept_submission']) ? true : false);
$submission->setAcceptMultiple(!empty($_POST['multi_submission'])  ? true : false);
if (!$is_task) {
    $submission->setOpenDate($openDate);
    $submission->setDueDate($dueDate);
    $submission->setLateDate($lateDate);
}
$submission->setName($_POST['name']);
$submission->setDescription($_POST['description']);
$submission->getWorkType()->setId($_POST['type']);
$submission->getMarkType()->setId($markTypeID);
$submission->setRubricResultSet($resultSetID ?: 0);
$submission->setMaxMark($markMax);
$submission->setMarkVisibility($_POST['mark_visibility']);

// append the notification data to be parsed in
foreach(array('overdue', 'upcoming', 'returned') as $i) {
    $notification     = $submission->getNotifications()->getOfType($i);
    $new_notification = false;
    if (!$notification instanceof Notification) {
        $notification     = new Notification();
        $new_notification = true;
    }
    $notification->setType($i);
    $notification->setEnabled(isset($_POST['notif_'.$i]));
    if ($new_notification) {
        $submission->addNotification($notification);
    }
}

// create new
try {
    if ($action == 'create') {
        $submission->setCategory($categoryID);
        $submission->setOwner($_SESSION['id']);

        if ($is_task) {
            $task = new Task();
            $task
                ->setCategory($categoryID)
                ->setOwner($_SESSION['id'])
                ->setTitle($_POST['task_title']);
            TaskMapper::getInstance()->save($task);
            $submission->setTask($task);
        }

        $mapper->save($submission);

        $submissionBoxID = $submission->getId();
        if ($is_task) {
            $task = $submission->getTask();
            $task->setSubmissionBox($submission);
            TaskMapper::getInstance()->save($task);
        }
    }
    // update existing
    elseif ($action == 'update') {
        $mapper->save($submission);
        if ($is_task) {
            // Load this submission's task from database
            $task = TaskMapper::getInstance()->findById($submission->getTask()->getId());
            $task->setTitle($_POST['task_title']);
            TaskMapper::getInstance()->save($task);
        }
    }
    // something happened that shouldn't have
    else {
        error(105);
    }
}
catch (Schoolbox\Resource\Exception $e) {
    if ($e->getCode() == Schoolbox\Resource\Exception::CODE_COPY_TO_STORAGE_FAILED) {
        error(105, 'The attached file could not be processed - try deleting it and re-attaching it.');
    }
    else {
        error(105);
    }
}

// persist rubric data
if(isset($rubric)) {
    $rubricHandler = new DueWork_Rubric_Handler();
    $rubricHandler->persistRubric($rubric, isset($invalidateExistingMarks) ? $invalidateExistingMarks : false, $submissionBoxID);
}

$page->localRedir($is_task ? "/homepage/$categoryID" : '/submission/listSubmission/');

/**
 * Uses POST variables to create dates
 * @param $field
 * @param $placeholder
 * @return Alaress_Date_Format
 */
function generateDate($field, $placeholder) {
    if(empty($_POST[$field . '_hour'])) {
        $adf = new Alaress_Date_Format(sprintf("%s %s", $_POST[$field.'_date'], $placeholder), 'd/m/y');
    }
    else {
        $adf = new Alaress_Date_Format(sprintf("%s %s:%s %s", $_POST[$field.'_date'], $_POST[$field.'_hour'], $_POST[$field.'_minute'], $_POST[$field.'_period']), 'd/m/y');
    }
    return $adf;
}

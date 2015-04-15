<?php
require_once(dirname(__FILE__) . '/../../include/Bootstrap.php');

// DETERMINE A COURSE OF ACTION

// ID provided, DueWork already exists
$is_task = !empty($_POST['is_task']);
if (is_numeric($_POST['id']) && ($is_task || $_POST['due_date']) && is_numeric($_POST['category'])) {
    $action          = 'update';
    $submissionBoxID = $_POST['id'];
} // no ID, create a new DueWork
elseif (($is_task || $_POST['due_date']) && is_numeric($_POST['category'])) {
    $action          = 'create';
    $submissionBoxID = 0;
} // deletion flagged, delete existing DueWork
elseif (isset($_GET['deleteBox']) && is_numeric($_GET['deleteBox'])) {
    $action          = 'delete';
    $submissionBoxID = $_GET['deleteBox'];
} elseif (isset($_GET['id']) && isset($_GET['mark_visibility'])) {
    $action          = 'visibility';
    $submissionBoxID = $_GET['id'];
} // data was POSTed, but nothing received: file upload size exceeded
elseif (upload_failed()) {
    error(10);
} // unknown case, bug out
else {
    error(2);
}

// create required objects
$page = new SitePage();

use Schoolbox\Assessment\Component\SubmissionBox;
use Schoolbox\Assessment\Component\SubmissionBoxMapper;
use Schoolbox\Assessment\Course\Task;
use Schoolbox\Assessment\Course\TaskMapper;
use Schoolbox\Assessment\Course\UnitInstance;
use Schoolbox\Assessment\Course\UnitInstanceMapper;
use Schoolbox\Resource\Attachment\Collector\CollectorFactory;

$mapper = new SubmissionBoxMapper();
// localise important variables
$submission   = !empty($submissionBoxID) ? $mapper->findById($submissionBoxID) : new SubmissionBox();
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


if ($action == 'delete') {
    $mapper->delete($mapper->findById($_GET['deleteBox']));
    // BTFF this thing
    $page->backToTheFuture(isset($_GET['next']) ? $_GET['next'] : 1);
} elseif ($action == 'visibility') {
    /* This is used for an ajax update request of just the visibility */
    try {
        $subBox = $mapper->findById($submissionBoxID);
        $subBox->setMarkVisibility($_GET['mark_visibility']);
        $mapper->save($subBox);
        print 'success';
    } catch (Exception $e) {
        print 'failure';
    }
    exit;
}

// CREATE OR UPDATE

$categoryID  = $_POST['category'];
$markTypeID  = $_POST['marktype'];
$resultSetID = $_POST['resultset'];

$acceptSubmissions = !empty($_POST['accept_submission']);
$multiSubmissions  = !empty($_POST['multi_submission']);

$descriptors    = $_POST['des'];
$values         = $_POST['value'];
$criteria       = $_POST['crit'];
$useDescriptors = $resultSetID == 0;

/* Prevent 12pm to 12am-next-day bug by checking that the hour submitted is
 * not equal to 12. This stops the date incrementing to the next day.
 *
 * Also set the time to the start or end of the day if all_day is set
 */

$publishDate = generateDate('publish', 0, 0, 0);
$openDate    = generateDate('open', 0, 0, 0);
$dueDate     = generateDate('due', 23, 59, 59);

// if we're not accepting submissions. override the late date to due date
// Open date: the date on which the event shows on student's calendars
if (!$acceptSubmissions) {
    $lateDate = $dueDate;
} else {
    $lateDate = generateDate('late', 23, 59, 59);
}

// If not a task: check if any of the dates failed to parse
if (!$is_task) {
    if (!($publishDate->isValid() && $openDate->isValid() && $dueDate->isValid() && $lateDate->isValid())) {
        error(15);
    }

    // late date is greater than due date
    if (!empty($_POST['accept_submission']) && $dueDate->getDatabase() > $lateDate->getDatabase()) {
        error(15);
    }
}

$markMax = !$_POST['markvalue'] ? '100' : $_POST['markvalue']; // if not set, default to 100

// if Total Mark selected, mark value must be specified
if ($markTypeID == SubmissionBox\MarkType::TYPE_TOTAL && empty($_POST['markvalue'])) {
    error(2);
} // if Rubric selected, generate the objects needed
elseif ($markTypeID == SubmissionBox\MarkType::TYPE_RUBRIC) {
    $rubricFactory = new DueWork_Rubric_Factory($criteria);

    if ($useDescriptors) {
        $specification = array();
        foreach ($descriptors as $critID => $desc) {
            if ($criteria[$critID]) {
                foreach ($desc as $k => $v) {
                    $specification[$critID][$k] = new DueWork_Rubric_Descriptor($v, $values[$critID][$k]);
                }
            }
        }
        $rubric = $rubricFactory->buildRubric($specification, $submissionBoxID);
    } else {
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
$submission->setAcceptMultiple(!empty($_POST['multi_submission']) ? true : false);
if (!$is_task) {
    $submission->setPublishDate($publishDate);
    $submission->setOpenDate($openDate);
    $submission->setDueDate($dueDate);
    $submission->setLateDate($lateDate);
}
$submission->setName($_POST['name']);
$submission->setDescription($_POST['description']);
$submission->getWorkType()->setId($_POST['type']);
$submission->getMarkType()->setId($markTypeID);
$submission->getGradeGroup()->setId($_POST['gradegroup']);
$submission->setRubricResultSet($resultSetID ?: 0);
$submission->setMaxMark($markMax);
$submission->setMarkVisibility($_POST['mark_visibility']);
$submission->setWeight(max(intval($_POST['weight']), 0));
if (isset($_POST['curriculumCodes'])) {
    $submission->setCurriculumNodes(
        Schoolbox\Curriculum\Node\Mapper::getInstance()
            ->findByColumn('id', $_POST['curriculumCodes'])
    );
}

// create new
try {
    if ($action == 'create') {
        $submission->setCategory($categoryID);
        $submission->setOwner($_SESSION['id']);

        // If a task, save the task entry first and then attach to submission.
        if ($is_task) {
            $task = new Task();
            $task
                ->setCategory($categoryID)
                ->setOwner($_SESSION['id'])
                ->setTitle($_POST['name']);
            TaskMapper::getInstance()->save($task);
            $submission->setTask($task);
        }

        // If a unit was passed, create a task instance within that unit for
        // this submission box.
        $is_unit       = !$is_task && !empty($_POST['unit_id']);
        $task_instance = new Task\Instance();
        if ($is_unit) {
            /** @var UnitInstance $unit */
            try {
                $unit = UnitInstanceMapper::getInstance()->findByIdAndCategoryId($_POST['unit_id'], $categoryID);
            } catch (Schoolbox\Mapper\Exception $e) {
                error(2);
            }
            $task_instance
                ->setTitle($submission->getName())
                ->setUnitInstance($unit)
                ->setOwner($_SESSION['id']);
            Task\InstanceMapper::getInstance()->save($task_instance);
            $submission->setTaskInstance($task_instance);
        }

        $mapper->save($submission);
        $submissionBoxID = $submission->getId();

        // Save the task or task instance for this box.
        if ($is_task) {
            $task = $submission->getTask();
            $task->setSubmissionBox($submission);
            TaskMapper::getInstance()->save($task);
        }
        if ($is_unit) {
            $task_instance = $submission->getTaskInstance();
            $task_instance->setSubmissionBox($submission);
            Task\InstanceMapper::getInstance()->save($task_instance);
        }
    } // update existing
    elseif ($action == 'update') {
        $mapper->save($submission);
        // Update task or task instance for this box
        if ($is_task) {
            $task = TaskMapper::getInstance()->findById($submission->getTask()->getId());
            $task->setTitle($_POST['name']);
            TaskMapper::getInstance()->save($task);
        }
        if ($submission->getTaskInstance()->getId()) {
            $task_instance = Task\InstanceMapper::getInstance()->findById($submission->getTaskInstance()->getId());
            $task_instance->setTitle($_POST['name']);
            Task\InstanceMapper::getInstance()->save($task_instance);
        }
    } // something happened that shouldn't have
    else {
        error(105);
    }
} catch (Schoolbox\Resource\Exception $e) {
    if ($e->getCode() == Schoolbox\Resource\Exception::CODE_COPY_TO_STORAGE_FAILED) {
        error(105, 'The attached file could not be processed - try deleting it and re-attaching it.');
    } else {
        error(105);
    }
}

// persist rubric data
if (isset($rubric)) {
    $rubricHandler = new DueWork_Rubric_Handler();
    $rubricHandler->persistRubric(
        $rubric,
        isset($invalidateExistingMarks) ? $invalidateExistingMarks : false,
        $submissionBoxID
    );
}

// clean and rebuild the manymany file relation
SubmissionBox\FileMapper::getInstance()->deleteByColumn('box_id', $submissionBoxID);

if (isset($_POST['attachment'])) {
    $factory = new CollectorFactory();
    /** @var \Schoolbox\Storage[] $storages */
    $storages = $factory->build($_POST['attachment'])->getAll();

    $files = new \Schoolbox\Collection();

    // iterate and link up
    foreach ($storages as $storage) {
        $file = new SubmissionBox\File();
        $file->setFile($storage);
        $file->setFileId($storage->getId());
        $file->setSubmissionBoxId($submissionBoxID);
        $files->add($file);
    }
    SubmissionBox\FileMapper::getInstance()->save($files);
}

$page->localRedir($is_task ? "/homepage/$categoryID" : '/submission/listSubmission/');

/**
 * Uses POST variables to create dates
 *
 * @param string $field
 * @param int    $placeholderHour
 * @param int    $placeholderMinute
 * @param int    $placeholderSecond
 *
 * @return Alaress_Date_Format
 */
function generateDate($field, $placeholderHour, $placeholderMinute, $placeholderSecond)
{
    $adf = Alaress_Date_Format::newLocalShort($_POST[$field . '_date']);
    if (empty($_POST[$field . '_hour'])) {
        $adf->setTime($placeholderHour, $placeholderMinute, $placeholderSecond);
    } else {
        $adf
            ->set12Hour($_POST[$field . '_hour'], $_POST[$field . '_period'])
            ->setMinute($_POST[$field . '_minute'])
        ;
    }
    return $adf;
}

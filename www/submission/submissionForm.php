<?php
require_once(dirname(__FILE__) . '/../../include/Bootstrap.php');

// DETERMINE A COURSE OF ACTION

// ID provided, DueWork already exists
if(is_numeric($_POST['id']) && $_POST['due_date'] && is_numeric($_POST['category'])) {
    $action = 'update';
}
// no ID, create a new DueWork
elseif($_POST['due_date'] && is_numeric($_POST['category'])) {
    $action = 'create';
}
// deletion flagged, delete existing DueWork
elseif(isset($_GET['deleteBox']) && is_numeric($_GET['deleteBox'])) {
    $action = 'delete';
}
// data was POSTed, but nothing received: file upload size exceeded
elseif(upload_failed()) {
    error(10);
}
// unknown case, bug out
else {
    error(2);
}

// create required objects
$page = new SitePage();

use Schoolbox\File;
use Schoolbox\Assessment\Component\SubmissionBox;
use Schoolbox\Assessment\Component\SubmissionBoxMapper;
use Schoolbox\Assessment\Component\SubmissionBox\Notification;
$mapper = new SubmissionBoxMapper();

if($action == 'delete') {
	$mapper->delete($mapper->findById($_GET['deleteBox']));
    $page->localRedir("/submission/listSubmission/");
}

// CREATE OR UPDATE

// localise important variables
$submissionBoxID = isset($_POST['id']) && $_POST['id'] ? $_POST['id'] : 0;
$submission = !empty($submissionBoxID) ? $mapper->findById($submissionBoxID) : new SubmissionBox();

$categoryID = $_POST['category'];
$markTypeID = $_POST['marktype'];
$resultSetID = $_POST['resultset'];

$acceptSubmissions = !empty($_POST['accept_submission']);
$multiSubmissions = !empty($_POST['multi_submission']);

$descriptors = $_POST['des'];
$criteria = $_POST['crit'];
$useDescriptors = $resultSetID == 0;

//
// File uploads are now stored in the storage engine. (eventually will support
// multiple uploads)
// Legacy file ID: files stored directly on the file system.
$legacy_file_id = 0;
$storage_id     = 0;
if (isset($_FILES['file']['name']) && $_FILES['file']['name']) {
    $allowedFileHandler = new AllowedFile();
    $allowedFileHandler->handleError($allowedFileHandler->fileSecurityCheck($_FILES['file']));

	//
	// Add the uploaded file to the submission box.
	$file = new File();
	$file->setFilename($_FILES['file']['name']);
	$file->setTempLocation($_FILES['file']['tmp_name']);
	$file->setUploadedBy($_SESSION['id']);
	$file->loadHashFromTempLocation();
	$file->loadMimetypeFromFilename();
	$file->loadFilesizeFromTempLocation();

	$file_link = new SubmissionBox\File();
	$file_link->setFile($file);

	$submission->addFileLink($file_link);
}
else {
	$legacy_file_id = $action == 'create' ? $_POST['fileId']    : 0;
	$storage_id     = $action == 'create' ? $_POST['storageId'] : 0;

	//
	// Multifile handling:
	// Convert legacy file to new file when uploading any files?
	// If many files were selected, connect them here.
	foreach ($_POST['file_ids'] as $file_id) {
		if (!empty($file_id)) {
			$submission->getFileLinks()->addFromFileId($file_id);
		}
	}
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

// if any of the dates failed to parse
if (!($openDate->isValid() && $dueDate->isValid() && $lateDate->isValid())) {
    error(15);
}

// late date is greater than due date
if (!empty($_POST['accept_submission']) && $dueDate->getDatabase() > $lateDate->getDatabase()) {
    error(15);
}

$markMax = !$_POST['markvalue'] ? '100' : $_POST['markvalue']; // if not set, default to 100
$resultSetID = 0;
// if Total Mark selected, mark value must be specified
if ($markTypeID == SubmissionBox\MarkType::TYPE_RAW && empty($_POST['markvalue'])) {
    error(15);
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
$submission->setOpenDate($openDate->getDatabase());
$submission->setDueDate($dueDate->getDatabase());
$submission->setLateDate($lateDate->getDatabase());
$submission->setName($_POST['name']);
$submission->setDescription($_POST['description']);
$submission->setWorkType($_POST['type']);
$submission->setMarkType($markTypeID);
$submission->setRubricResultSet($resultSetID);
$submission->setMaxMark($markMax);
if ($legacy_file_id) {
	$submission->setLegacyFileId($legacy_file_id);
}

// append the notification data to be parsed in
foreach(array('overdue', 'upcoming', 'returned') as $i) {
	$notification = new Notification();
	$notification->setType($i);
	$notification->setEnabled(isset($_POST['notif_'.$i]));
	$submission->addNotification($notification);
}

// create new
if($action == 'create') {
	$submission->setCategory($categoryID);
	$submission->setOwner($_SESSION['id']);
	$mapper->save($submission);
	$submissionBoxID = $submission->getId();
}
// update existing
elseif($action == 'update') {
	$mapper->save($submission);
}
// something happened that shouldn't have
else {
    error(15);
}

// persist rubric data
if(isset($rubric)) {
    $rubricHandler = new DueWork_Rubric_Handler();
    $rubricHandler->persistRubric($rubric, isset($invalidateExistingMarks) ? $invalidateExistingMarks : false, $submissionBoxID);
}

$page->localRedir('/submission/listSubmission/');

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

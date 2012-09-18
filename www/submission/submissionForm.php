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
$submissionBoxHandler = new Submission();

if($action == 'delete') {
    $submissionBoxHandler->delete($_GET['deleteBox']);
    $page->localRedir("/submission/listSubmission/");
}

// CREATE OR UPDATE

// localise important variables
$submissionBoxID = isset($_POST['id']) ? $_POST['id'] : 0;
$categoryID = $_POST['category'];
$markTypeID = $_POST['marktype'];
$resultSetID = $_POST['resultset'];

$acceptSubmissions = !empty($_POST['accept_submission']);
$multiSubmissions = !empty($_POST['multi_submission']);

$descriptors = $_POST['des'];
$criteria = $_POST['crit'];
$useDescriptors = $resultSetID == 0;

// parse file upload if there's one to parse
if (isset($_FILES['file']['name']) && $_FILES['file']['name']) {
    $allowedFileHandler = new AllowedFile();
    $allowedFileHandler->handleError($allowedFileHandler->fileSecurityCheck($_FILES['file']));

    $fileHandler = new File();
    $data = array(
        'owner'       => $_SESSION['id'],
        'description' => $_POST['name'],
        'type'        => $_POST['type'],
        'comment'     => $_POST['comment'],
        'category'    => $categoryID,
        'hidden'      => '1',
    );
    $fileId = $fileHandler->create($data, $_FILES['file']);
}
else {
    $fileId = $action == 'create' ? $_POST['fileId'] : 0;
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
if (!($openDate && $dueDate && $lateDate)) {
    error(15);
}

// late date is greater than due date
if (!empty($_POST['accept_submission']) && $dueDate > $lateDate) {
    error(15);
}

$markType = $submissionBoxHandler->getMarkTypeByID($markTypeID);

$markMax = !$_POST['markvalue'] ? '100' : $_POST['markvalue']; // if not set, default to 100

// if Total Mark selected, mark value must be specified
if ($markType['slug'] == 'raw' && empty($_POST['markvalue'])) {
    error(15);
}
// if Rubric selected, generate the objects needed
elseif($markType['slug'] == 'rubric') {
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
    $subbox = $submissionBoxHandler->getByID($submissionBoxID);
    if($resultSetID != $subbox['rubric_result_set']) {
        // invalidate
        $invalidateExistingMarks = true;
    }

    $markMax = $rubric->getCriteriaCount() * ($rubric->getResultCount() - 1);
}


// define submissionBox 'object': elements common to both scenarios
$submissionBox = array(
    'accept_submission' => !empty($_POST['accept_submission']) ? true : false,
    'multi_submission'  => !empty($_POST['multi_submission'])  ? true : false,
    'open_date'         => $openDate,
    'date'              => $dueDate,
    'late_date'         => $lateDate,
    'name'              => $_POST['name'],
    'description'       => $_POST['description'],
    'type'              => $_POST['type'],
    'mark_type'         => $markTypeID,
    'rubric_result_set' => $markType['slug'] == 'rubric' ? $resultSetID : 0,
    'max_mark'          => $markMax,
    'notif_enabled'     => !empty($_POST['notif_enabled']) ? true : false,
);

// only set this field if there's actually something to put in it
if($fileId) {
    $submissionBox['fid'] = $fileId;
}

//die();
// create new
if($action == 'create') {
    // additonal fields unique to creation
    $submissionBox['category'] = $categoryID;
    $submissionBox['created'] = 'NOW()';
    $submissionBox['owner'] = $_SESSION['id'];
    $submissionBoxID = $submissionBoxHandler->create($submissionBox);
}
// update existing
elseif($action == 'update') {
    // additonal field unique to updating
    $submissionBox['id'] = $submissionBoxID;
    $submissionBoxHandler->update($submissionBox);
}
// something happened that shouldn't have
else {
    error(15);
}

// persist rubric data
if(isset($rubric)) {

    // TODO refactor this SQL into handlers or DMs or something...BUT IT CANNOT LIVE HERE!!!
    $db = Database::getInstance();

    // 0) invalidate existing marks if required
    if(isset($invalidateExistingMarks) && $invalidateExistingMarks === true) {
        $stmt = $db->prepare("UPDATE rubric_mark SET valid = 0 WHERE submission_box_id = :sbid");
        $stmt->execute(array('sbid' => $submissionBoxID));
    }

    // 1) remove existing rubric_usage entries for this submissionbox
    $stmt = $db->prepare("DELETE FROM rubric_usage WHERE submission_box_id = :sbid");
    $stmt->execute(array('sbid' => $submissionBoxID));

    // 2) add the rubric usages for this submission box
    $stmt = $db->prepare("INSERT INTO rubric_usage VALUES (:cid, :sbid)");
    if($rubric->getCriteriaCount()) {
        foreach($rubric->getCriteriaIndices() as $i) {
            $stmt->execute(array(
                'cid'  => $i,
                'sbid' => $submissionBoxID
            ));
        }
    }

    // 3) add the descriptors into into the database, if necessary
    // the ResultSetRubric doesn't need any additional data stored
    if($rubric instanceof DueWork_Rubric_DescriptorRubric) {
        $stmt = $db->prepare("INSERT INTO rubric_descriptor VALUES (:cid, :sbid, :val, :descr) ON DUPLICATE KEY UPDATE `description` = :descr");

        if($rubric->getCriteriaCount() && $rubric->getResultCount()) {
            foreach($rubric->getCriteriaIndices() as $i) {
                foreach($rubric->getResultIndices() as $j) {
                    $stmt->execute(array(
                        'cid'   => $i,
                        'sbid'  => $submissionBoxID,
                        'val'   => $j,
                        'descr' => $rubric->getResult($i, $j)->getDescription()
                    ));
                }
            }
        }
    }
}

$page->localRedir('/submission/listSubmission/');

/**
 * Uses POST variables to create dates
 * @param $field
 * @param $placeholder
 * @return mixed
 */
function generateDate($field, $placeholder) {
    if(empty($_POST[$field . '_hour'])) {
        $adf = new Alaress_Date_Format(sprintf("%s %s", $_POST[$field.'_date'], $placeholder), 'd/m/y');
    }
    else {
        $adf = new Alaress_Date_Format(sprintf("%s %s:%s %s", $_POST[$field.'_date'], $_POST[$field.'_hour'], $_POST[$field.'_minute'], $_POST[$field.'_period']), 'd/m/y');
    }
    return $adf->getDatabase();
}

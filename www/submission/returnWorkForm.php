<?php
require_once(dirname(__FILE__) . '/../../include/Bootstrap.php');

$page = new SitePage;
$returnHandler = new SubmissionReturn;
$subHandler = new Submission();
$rubricHandler = new DueWork_Rubric_Handler();

// TODO: refactor this to somwhere else, not sure where it should live though
const update_window = 21600; // 60 * 60 * 6 seconds = 6 hours

// Ajax from mark sheet
if(isset($_POST['uid']) && isset($_POST['comment']) /* && isset($_POST['mark']) */ && isset($_POST['box_id']) && $_POST['markUpdate'] == 'true') {
    $response = array();

    if(is_numeric($_POST['uid']) && is_numeric($_POST['box_id'])) {
        $response[] = ajaxUpdate($_POST['mark'], $_POST['comment'], $_POST['uid'], $_POST['box_id'], $_POST['year'], $_POST['semester']);
    }
    elseif(is_array($_POST['uid']) /* && is_array($_POST['mark'])*/ ) {
        for($i = 0; $i < count($_POST['uid']); ++$i) {
            $response[] = ajaxUpdate($_POST['mark'][$i], $_POST['comment'][$i], $_POST['uid'][$i], $_POST['box_id'], $_POST['year'], $_POST['semester']);
        }
    }

    header('Content-Type: application/json');
    die(json_encode($response));
}

// inline upload of files
elseif(isset($_POST['uid']) && isset($_POST['box_id']) && $_POST['markUpdate'] == 'false') {
    if(is_array($_POST['uid'])) {
        $allowedFileHandler = new AllowedFile;
        $subbox = $subHandler->getByID($_POST['box_id']);
        $marksheet = Marksheet_Factory::build($subbox['mark_type_slug']);

        foreach ($_POST['uid'] as $k => $id) {
            $uploadIndex = "file-return-$id";

            //
            // Get last mark and comment, and merge it with this one.
            $result = $returnHandler->getByUser($_POST['uid'][$k], $_POST['box_id'], 1);
            $return = array(
                'box_id'    => $_POST['box_id'],
                'owner'     => $_POST['uid'][$k],
                'comment'   => $_POST['comment'][$k],
                'mark'      => (!empty($result[0]) ? $result[0]['mark']      : null),
                'norm_mark' => (!empty($result[0]) ? $result[0]['norm_mark'] : null),
            );
            $uploaded_file = null;

            //
            // Add file, if there was one.
            if (isset($_FILES[$uploadIndex])) {
                if ($_FILES[$uploadIndex]['name']) {
                    $returnHandler->securityCheck($_POST['box_id'], 0);
                    $allowedFileHandler->handleError($allowedFileHandler->fileSecurityCheck($_FILES[$uploadIndex]));
                    $mark = $marksheet->normaliseMark($_POST['mark'][$k], $subbox);
                    $uploaded_file = $_FILES[$uploadIndex];
                }
            }
            saveReturn($return, null, $uploaded_file);
        }
    }
    $page->localRedir('/submission/viewSubmission/' . $_POST['box_id']);
}


// posting from returnWork
// test that the rubric is given
elseif(is_array($_POST['rubric']) && count($_POST['rubric'])) {
    $returnHandler->securityCheck($_POST['box_id'], $_POST['uid']);

    $subbox = $subHandler->getByID($_POST['box_id']);
    $marksheet = Marksheet_Factory::build($subbox['mark_type_slug']);
    $mark = $marksheet->normaliseMark($_POST['rubric'], $subbox);

    $return = array(
        'box_id'    => $_POST['box_id'],
        'owner'     => $_POST['uid'],
        'mark'      => $mark['mark'],
        'norm_mark' => $mark['norm_mark']
    );
    saveReturn($return, $_POST['rubric']);

    // in all possible scenarios, *something* happened above, so redirect the user
    $page->localRedir('/submission/viewSubmission/'.$_POST['box_id']);
}


/* Exceeded PHP max file size */
elseif(upload_failed()) {
    error(10);
}
// not enough information submitted to this form
else {
    error(2);
}

/**
 * @param array $return The returned work to save.
 * @param array $rubric The rubric data to save, if there is one.
 * @param array $file   The file to add, if there is one.
 *
 * @return bool
 */
function saveReturn($return, $rubric = null, $file = null) {
    $rubric_handler = new DueWork_Rubric_Handler();
    $return_handler = new SubmissionReturn();
    $box_id         = $return['box_id'];
    $student_id     = $return['owner'];
    $result         = $return_handler->getByUser($student_id, $box_id, 1);

    $success = false;
    if (is_array($result)) {
        $return_time = new Alaress_Date_Format($result[0]['returned'], 'y-m-d h:i:s');
        $window_ago  = new Alaress_Date_Format(time() - update_window, 'U');

        // is this submission within the window?
        if ($return_time->getUnixTimestamp() < $window_ago->getUnixTimestamp()) {
            // outside window: create new
            $success = $return_handler->create($return, $file);
            if ($rubric) {
                $rubric_handler->saveMarks($rubric, $student_id, $box_id);
            }
        }
        else {
            // inside window: update exising
            $return['id'] = $result[0]['id'];
            //
            // Is there a file? Update it with the most recent mark.
            $success = $return_handler->update($return, $file);
            if ($rubric) {
                $stamp = $rubric_handler->getLatestMarkTimestamp($_POST['uid'], $_POST['box_id']);
                // if a valid timestamp is found, update, otherwise, create a new one (selfcontained logic)
                $rubric_handler->saveMarks($rubric, $student_id, $box_id, $stamp);
            }
        }
    }
    else {
        // no previous submission
        $success = $return_handler->create($return, $file);
        if ($rubric) {
            $rubric_handler->saveMarks($rubric, $student_id, $box_id);
        }
    }
    return $success;
}

/**
 * @param int $mark
 * @param string $comment
 * @param int $studentID
 * @param int $boxID
 * @param bool $respond
 */
function ajaxUpdate($givenMark, $comment, $studentID, $boxID, $year = 0, $semester = 0) {

    $year = (int)$year;
    $semester = (int)$semester;

    $returnHandler = new SubmissionReturn;
    $subHandler = new Submission();

    $returnHandler->securityCheck($boxID, $studentID);

    // get the most recent return, or null if it doesn't exist.
    $result = $returnHandler->getByUser($studentID, $boxID, 1);

    // get the submission box
    $subbox = $subHandler->getByID($boxID);

    $marksheet = Marksheet_Factory::build($subbox['mark_type_slug']);

    // echo $subbox['mark_type_slug'];

    // retrieve error codes
    $dem = DueWork_ErrorMapper::getInstance();
    $err = $dem->findAll();

    // make associative by label (since that's what's being transmitted)
    $errors = array();
    foreach($err as $e) {
        $errors[$e->displayAs] = $e;
    }

    //
    // Empty mark (not zero) - treat as null
    if (trim($givenMark) === '') {
        $mark['mark']      = null;
        $mark['norm_mark'] = 'NULL';
    }
    elseif (isset($errors[$givenMark])) {
        // error given
        $mark['mark'] = $errors[$givenMark]->displayAs;
        $mark['norm_mark'] = $errors[$givenMark]->normaliseAs === NULL ? 'NULL' : $errors[$givenMark]->normaliseAs;
    }
    // not a recognised error code, check for rubric
    elseif($subbox['mark_type_slug'] == 'rubric') {
        // in this context, no mark is supplied, so retrieve, smash to pieces, and reinsert
        $mark = $marksheet->retrieveMark($studentID, $boxID);
        $marksheet = Marksheet_Factory::build('raw');
        $mark = $marksheet->normaliseMark($mark['mark'], $subbox);
    }
    else {
        // normalise the new mark given, ready to be inserted
        $mark = $marksheet->normaliseMark($givenMark, $subbox);
    }

    // print_r($mark);

    // set up common values for the return
    $return = array(
        'box_id'    => $boxID,
        'owner'     => $studentID,
        'mark'      => $mark['mark'],
        'norm_mark' => $mark['norm_mark'],
        'comment'   => $comment,
    );
    saveReturn($return);

    $marks = $returnHandler->getStudentMarksForCategory($subbox['category'], $studentID, $_SESSION['id'], $year, $semester);

    return array(
        'id' => $studentID,
        'marks' => $marks
    );

    // var_dump($success);

    /*
        INSERT INTO `submission_return` (`box_id`,`owner`,`mark`,`norm_mark`,`comment`,`filename`,`returned`) VALUES ('7','17','47','47','comment here!!!!','',NOW());
        UPDATE `student_submission` SET `marked`='1' WHERE 1=1 AND `box_id`= '7' AND `owner`= '17';
     */
}

<?php
require_once(dirname(__FILE__) . '/../../include/Bootstrap.php');

use Schoolbox\UserMapper;
use Schoolbox\Assessment\Component\SubmissionBox;
use Schoolbox\Assessment\Component\SubmissionBoxMapper;
use Schoolbox\Assessment\Component\SubmissionBox\ReturnedWork;
use Schoolbox\Assessment\Component\SubmissionBox\ReturnedWorkMapper;

$page = new SitePage;
$returnHandler = new SubmissionReturn;
$subHandler = new Submission();
$rubricHandler = new DueWork_Rubric_Handler();
print_r($_POST);
// Ajax from mark sheet
if(isset($_POST['uid']) && isset($_POST['comment']) && isset($_POST['box_id']) && $_POST['markUpdate'] == 'true') {
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
        $returnHandler->securityCheck($_POST['box_id']);
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
                'marker'    => $_SESSION['id'],
                'owner'     => $_POST['uid'][$k],
                'comment'   => $_POST['comment'][$k],
                'mark'      => (!empty($result[0]) ? $result[0]['mark']      : null),
                'norm_mark' => (!empty($result[0]) ? $result[0]['norm_mark'] : null),
            );
            if (!isset($return['norm_mark'])) {
                unset($return['mark'], $return['norm_mark']);
            }
            $uploaded_file = null;

            //
            // Add file and save, only if there was a file.
            if (isset($_FILES[$uploadIndex])) {
                if ($_FILES[$uploadIndex]['name']) {
                    $allowedFileHandler->handleError($allowedFileHandler->fileSecurityCheck($_FILES[$uploadIndex]));
                    $mark = $marksheet->normaliseMark($_POST['mark'][$k], $subbox);
                    $uploaded_file = $_FILES[$uploadIndex];

					/* Save filesize in db
					 */
					$return['filesize'] = $_FILES[$uploadIndex]['size'];
					$return['mime_type'] = $uploaded_file['type'];

					$saveReturn = new \Schoolbox\Assessment\Component\SubmissionBox\ReturnedWorkSaver();
                    $saveReturn->saveReturn($return, null, $uploaded_file);
                }
            }
        }
    }
	else {
		// posting from returnWork
		// test that the rubric is given
		if(is_array($_POST['rubric']) && count($_POST['rubric'])) {
			$mark = $marksheet->normaliseMark($_POST['rubric'], $subbox);

			$return = array(
				'box_id'    => $_POST['box_id'],
				'marker'    => $_SESSION['id'],
				'owner'     => $_POST['uid'],
				'comment'   => $_POST['comment'],
				'mark'      => $mark['mark'],
				'norm_mark' => $mark['norm_mark']
			);

			$saveReturn = new \Schoolbox\Assessment\Component\SubmissionBox\ReturnedWorkSaver();
			$saveReturn->saveReturn($return, $_POST['rubric']);
		}

	}

	/* Redirect back to crocodoc marking screen
	 */
	if (strlen($_POST['redirect'])) {
		$page->localRedir($_POST['redirect']);
	}
	else {
	    $page->localRedir('/submission/viewSubmission/' . $_POST['box_id']);
	}
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
    /* @var DueWork_Error[] $errors */
    $errors = array();
    foreach($err as $e) {
        /* @var $e DueWork_Error */
        $errors[$e->getDisplayAs()] = $e;
    }

    // Empty mark (not zero), or negative mark - treat as null
    if (trim($givenMark) === '' || $givenMark < 0) {
        $mark['mark']      = null;
        $mark['norm_mark'] = null;
    }
    elseif (isset($errors[$givenMark])) {
        // error given
        $mark['mark']      = $errors[$givenMark]->getDisplayAs();
        $mark['norm_mark'] = $errors[$givenMark]->getNormaliseAs();
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

    // set up common values for the return
    $return = array(
        'box_id'    => $boxID,
        'owner'     => $studentID,
        'mark'      => $mark['mark'],
        'norm_mark' => $mark['norm_mark'],
        'comment'   => $comment,
    );

	$saveReturn = new \Schoolbox\Assessment\Component\SubmissionBox\ReturnedWorkSaver();
    $saveReturn->saveReturn($return);

    $marks = $returnHandler->getStudentMarksForCategory($subbox['category'], $studentID, $_SESSION['id'], $year, $semester);

    return array(
        'id' => $studentID,
        'marks' => $marks
    );
}

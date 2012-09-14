<?php
require_once(dirname(__FILE__) . '/../../include/Bootstrap.php');

$page = new SitePage;
$returnHandler = new SubmissionReturn;
$subHandler = new Submission();

// TODO: refactor this to somwhere else, not sure where it should live though
const update_window = 21600; // 60 * 60 * 6 seconds = 6 hours

// Ajax from mark sheet
if(isset($_POST['uid']) && isset($_POST['comment']) && isset($_POST['mark']) && isset($_POST['box_id']) && $_POST['markUpdate'] == 'true') {
    if(is_numeric($_POST['uid']) && is_numeric($_POST['box_id'])) {
        ajaxUpdate($_POST['mark'], $_POST['comment'], $_POST['uid'], $_POST['box_id']);
    }
    elseif(is_array($_POST['uid']) && is_array($_POST['mark'])) {
        for($i = 0; $i < count($_POST['uid']); ++$i) {
            ajaxUpdate($_POST['mark'][$i], $_POST['comment'][$i], $_POST['uid'][$i], $_POST['box_id']);
        }
    }
}

// inline upload of files
elseif(isset($_POST['uid']) && isset($_POST['box_id']) && $_POST['markUpdate'] == 'false') {
    if(is_array($_POST['uid'])) {
        $allowedFileHandler = new AllowedFile;
        $subbox = $subHandler->getByID($_POST['box_id']);
        $marksheet = Marksheet_Factory::build($subbox['mark_type_slug']);

        foreach ($_POST['uid'] as $k => $id) {
            $uploadIndex = "file-return-$id";

            if(isset($_FILES[$uploadIndex])) {
                if($_FILES[$uploadIndex]['name']) {
                    $returnHandler->securityCheck($_POST['box_id'], 0);
                    $allowedFileHandler->handleError($allowedFileHandler->fileSecurityCheck($_FILES[$uploadIndex]));

                    $mark = $marksheet->normaliseMark($_POST['mark'][$k], $subbox);

                    $return = array(
                        'box_id'    => $_POST['box_id'],
                        'owner'     => $_POST['uid'][$k],
                        'comment'   => $_POST['comment'][$k],
                        'mark'      => $mark['mark'],
                        'norm_mark' => $mark['norm_mark']
                    );
                    $returnHandler->create($return, $_FILES[$uploadIndex]);
                }
            }
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

    $result = $returnHandler->getByUser($_POST['uid'], $_POST['box_id'], 1);

    // if there is a previous submission, update it
    if(is_array($result)) {
        $returnTime = new Alaress_Date_Format($result[0]['returned'], 'y-m-d h:i:s');
        $windowAgo = new Alaress_Date_Format(time() - update_window, 'U');

        // is this submission within the window?
        if($returnTime < $windowAgo) {
            // outside window: create new
            $returnHandler->create($return);

            // TODO <refactor>
            $db = Database::getInstance();
            $stmt = $db->prepare("
                INSERT INTO rubric_mark (`student_id`,`submission_box_id`, `criterion_id`, `mark`)
                VALUES (:sid, :sbid, :cid, :mark)
                ON DUPLICATE KEY UPDATE mark = :mark
            ");

            foreach($_POST['rubric'] as $cID => $mark) {
                $stmt->execute(array(
                    'sid'  => $_POST['uid'],
                    'sbid' => $_POST['box_id'],
                    'cid'  => $cID,
                    'mark' => $mark
                ));
            }
            // TODO </refactor>
        }
        else {
            // inside window: update existing
            $return['id'] = $result[0]['id'];
            $returnHandler->update($return);

            // TODO <refactor>
            $db = Database::getInstance();
            $stmt = $db->prepare("
                SELECT MAX(tstamp) FROM rubric_mark
                WHERE student_id = :sid
                AND submission_box_id = :sbid
                AND valid = 1"
            );

            $stmt->execute(array(
                'sid'   => $_POST['uid'],
                'sbid'  => $_POST['box_id'],
            ));

            $stamp = $stmt->fetchColumn();

            // if a valid timestamp is found, update, otherwise, create a new one
            if($stamp) {
                $stmt = $db->prepare("
                UPDATE rubric_mark
                SET `mark` = :mark
                WHERE student_id = :sid
                AND submission_box_id = :sbid
                AND criterion_id = :cid
                AND tstamp = :stamp
                AND valid = 1
            ");

                foreach($_POST['rubric'] as $cID => $mark) {
                    $stmt->execute(array(
                        'mark'  => $mark,
                        'sid'   => $_POST['uid'],
                        'sbid'  => $_POST['box_id'],
                        'cid'   => $cID,
                        'stamp' => $stamp
                    ));
                }
            }
            else {
                $stmt = $db->prepare("
                INSERT INTO rubric_mark (`student_id`,`submission_box_id`, `criterion_id`, `mark`)
                VALUES (:sid, :sbid, :cid, :mark)
                ON DUPLICATE KEY UPDATE mark = :mark
                ");

                foreach($_POST['rubric'] as $cID => $mark) {
                    $stmt->execute(array(
                        'sid'  => $_POST['uid'],
                        'sbid' => $_POST['box_id'],
                        'cid'  => $cID,
                        'mark' => $mark
                    ));
                }
            }

            // TODO </refactor>
        }
    }
    else {
        // no previous submission
        $returnHandler->create($return);
    }

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
 * @param $mark
 * @param $comment
 * @param $studentID
 * @param $boxID
 */
function ajaxUpdate($givenMark, $comment, $studentID, $boxID) {
    $returnHandler = new SubmissionReturn;
    $subHandler = new Submission();

    $returnHandler->securityCheck($boxID, $studentID);

    // get the most recent return, or null if it doesn't exist.
    $result = $returnHandler->getByUser($studentID, $boxID, 1);

    // get the submission box
    $subbox = $subHandler->getByID($boxID);

    // normalise the new mark given, ready to be inserted
    $marksheet = Marksheet_Factory::build($subbox['mark_type_slug']);
    $mark = $marksheet->normaliseMark($givenMark, $subbox);

    // set up common values for the return
    $return = array(
        'box_id'    => $boxID,
        'owner'     => $studentID,
        'mark'      => $mark['mark'],
        'norm_mark' => $mark['norm_mark'],
        'comment'   => $comment,
    );

    print_r($return);

    $success = false;
    // previous submission exists
    if (is_array($result)) {

        $returnTime = new Alaress_Date_Format($result[0]['returned'], 'y-m-d');
        $windowAgo = new Alaress_Date_Format(time() - update_window, 'U');

        // is this submission within the window?
        if($returnTime->getUnixTimestamp() < $windowAgo->getUnixTimestamp()) {
            // outside window: create new
            $returnHandler->create($return);
        } else {
            // inside window: update exising
            $return['id'] = $result[0]['id'];
            $success = $returnHandler->update($return);
        }

    } else {
        // no previous submission
        $success = $returnHandler->create($return);
    }
    var_dump($success);
    /*
        INSERT INTO `submission_return` (`box_id`,`owner`,`mark`,`norm_mark`,`comment`,`filename`,`returned`) VALUES ('7','17','47','47','comment here!!!!','',NOW());
        UPDATE `student_submission` SET `marked`='1' WHERE 1=1 AND `box_id`= '7' AND `owner`= '17';
     */
}

?>
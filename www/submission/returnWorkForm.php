<?php
require_once(dirname(__FILE__) . '/../../include/Bootstrap.php');

$page = new SitePage;
$returnHandler = new SubmissionReturn;
$subHandler = new Submission();

// TODO: refactor this to somwhere else, not sure where it should live though
const update_window = 21600; // 60 * 60 * 6 seconds = 6 hours

header('Content-Type: text/plain');
print_r($_POST);
print_r($_FILES);

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
// test that at least one of file, comment or mark is given
elseif (
    (
        ( isset($_POST['comment']) && $_POST['comment'] )
        || ( isset($_POST['mark']) && $_POST['mark'] || $_POST['mark'] == 0 )
        || ( isset($_FILES['file']['name']) && $_FILES['file']['name'] )
    )
    && ( isset($_POST['uid']) && $_POST['uid'] )
    && ( isset($_POST['box_id']) && $_POST['box_id'] ) )
{
    $returnHandler->securityCheck($_POST['box_id'], $_POST['uid']);

    if($_FILES['file']['name']) {
        $allowedFileHandler = new AllowedFile;
        $allowedFileHandler->handleError($allowedFileHandler->fileSecurityCheck($_FILES['file']));
    }

    $subbox = $subHandler->getByID($_POST['box_id']);
    $marksheet = Marksheet_Factory::build($subbox['mark_type_slug']);
    $mark = $marksheet->normaliseMark($_POST['mark'], $subbox);

    $return = array(
        'box_id'    => $_POST['box_id'],
        'owner'     => $_POST['uid'],
        'comment'   => $_POST['comment'],
        'mark'      => $mark['mark'],
        'norm_mark' => $mark['norm_mark']
    );

    // if there is a file attached, create a new return regardless of time window

    if($_FILES['file']['name']) {
        $returnHandler->create($return, $_FILES['file']);

    } else {
        // no file attached: do time window logic
        $result = $returnHandler->getByUser($_POST['uid'], $_POST['box_id'], 1);

        // if there is a previous submission, update it
        if(is_array($result)) {
            $returnTime = new Alaress_Date_Format($result[0]['returned'], 'y-m-d h:i:s');
            $windowAgo = new Alaress_Date_Format(time() - update_window, 'U');

            // is this submission within the window?
            if($returnTime < $windowAgo) {
                // outside window: create new
                $returnHandler->create($return);

            } else {
                // inside window: update exising
                $return['id'] = $result[0]['id'];
                $returnHandler->update($return);
            }

        } else {
            // no previous submission
            $returnHandler->create($return);
        }
    }

    // in all possible scenarios, *something* happened above, so redirect the user
    $page->backToTheFuture(2);
}


/* Exceeded PHP max file size */
elseif (
    $_SERVER['REQUEST_METHOD'] == 'POST'
    && $_SERVER['CONTENT_LENGTH'] > 0
    && empty($_POST) && empty($_FILES)
)
{
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
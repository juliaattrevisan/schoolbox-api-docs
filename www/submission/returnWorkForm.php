<?php
require_once(dirname(__FILE__) . '/../../include/Bootstrap.php');

$page = new SitePage;
$returnHandler = new SubmissionReturn;
$subHandler = new Submission();

// TODO: refactor this to somwhere else, not sure where it should live though
const update_window = 21600; // 60 * 60 * 6 seconds = 6 hours


// Ajax from mark sheet
if(
    is_numeric($_POST['uid'])
    && is_numeric($_POST['box_id'])
    && $_POST['markUpdate'] == 'true')
{
    if ($_POST['mark']) {
        $returnHandler->securityCheck($_POST['box_id'], $_POST['uid']);

        // get the most recent return, or null if it doesn't exist.
        $result = $returnHandler->getByUser($_POST['uid'], $_POST['box_id'], 1);

        // get the submission box
        $subbox = $subHandler->getByID($_POST['box_id']);

        // normalise the new mark given, ready to be inserted
        $marksheet = Marksheet_Factory::build($subbox['mark_type_slug']);
        $mark = $marksheet->normaliseMark($_POST['mark'], $subbox);

        // set up common values for the return
        $return = array(
            'box_id'    => $_POST['box_id'],
            'owner'     => $_POST['uid'],
            'mark'      => $mark['mark'],
            'norm_mark' => $mark['norm_mark'],
            'comment'   => $_POST['comment'],
        );

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
                $returnHandler->update($return);
            }

        } else {
            // no previous submission
            $returnHandler->create($return);
        }
    }
}


// posting from returnWork
// test that at least one of file, comment or mark is given
else if (
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

?>
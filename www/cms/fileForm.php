<?php
require_once( dirname(__FILE__) . '/../../include/Bootstrap.php' );

/* Force session usage due to flash uploader
 * http://www.uploadify.com/forums/discussion/43/using-sessions-tricking-basic-authentication/p1
 */
if ( isset( $_POST[session_name()] ) ) {
    session_id($_POST[session_name()]);
}

if ( isset( $_GET[session_name()] ) ) {
    session_id($_GET[session_name()]);
}

$page = new SitePage();
$fileHandler = new File();
$groupHandler = new Group();

/* Upload single file to blackhole storage */
if ( is_string($_POST['description']) && ( !isset($_POST['category']) ) ) {

}

/* Upload single file */
elseif ( is_string($_POST['description']) && ( is_numeric($_POST['category']) || is_array($_POST['category']) ) ) {
    $file = array(
        'description' => $_POST['description'], 'comment' => $_POST['comment'], 'hidden' => $_POST['hidden'],
    );

    if ( is_numeric($_POST['fileID']) ) {

        /* Modify existing file */

        /* Double check permission on file
           */
        $currentfile = $fileHandler->getById($_POST['fileID']);

        if ( $groupHandler->getPermission($currentfile['cid']) < WRITE_ACCESS ) {
            error(6);
        }

        $file['id'] = $_POST['fileID'];

        if ( is_numeric($_POST['myfile']) ) {
            /* Make this appear as a file upload */
            $myFileHandler = new MyFile();

            if ( !$privFile = $myFileHandler->getByID($_POST['myfile']) ) {
                error(41);
            }

            $_FILES['file'] = array(
                'name' => $privFile['realname'], 'type' => $privFile['mime_type'],
                'tmp_name' => Cfg::get('myfile_dir') . '/' . $privFile['id'] . '.dat',
            );
        }

        if ( $_FILES['file']['name'] ) {
            /* Submitted file with form, run security checks */
            $allowedFileHandler = new AllowedFile();

            if ( is_numeric($_POST['myfile']) ) {
                $allowedFileHandler->handleError($allowedFileHandler->isAllowed($_FILES['file']['name']));
            }
            else {
                $allowedFileHandler->handleError($allowedFileHandler->fileSecurityCheck($_FILES['file']));
            }

            $fileHandler->update($file, $_FILES['file']);
        }
        else {
            $fileHandler->update($file);
        }

        $page->backToTheFuture(2);
    }
    else {
        /* Add file */
        $file['owner'] = $_SESSION['id'];
        $allowedFileHandler = new AllowedFile;

        if ( is_numeric($_POST['myfile']) ) {
            /* Make this appear as a file upload */
            $myFileHandler = new MyFile();

            if ( !$privFile = $myFileHandler->getByID($_POST['myfile']) ) {
                error(41);
            }

            $_FILES['file'] = array(
                'name' => $privFile['realname'], 'type' => $privFile['mime_type'],
                'tmp_name' => Cfg::get('myfile_dir') . '/' . $privFile['id'] . '.dat',
            );
        }

        if ( $_FILES['file']['name'] ) {
            /* Submitted file with form, run security checks */
            if ( is_numeric($_POST['myfile']) ) {
                $allowedFileHandler->handleError($allowedFileHandler->isAllowed($_FILES['file']['name']));
            }
            else {
                $allowedFileHandler->handleError($allowedFileHandler->fileSecurityCheck($_FILES['file']));
            }

            /* Add to multiple locations */
            if ( is_array($_POST['category']) ) {
                foreach ( $_POST['category'] as $cat ) {
                    if ( $groupHandler->getPermission($cat) < WRITE_ACCESS ) {
                        error(6);
                    }

                    $file['category'] = $cat;
                    $fileHandler->create($file, $_FILES['file']);
                }

                // redirect to first category
                SitePage::localRedir('/resources/' . $_POST['category'][0]);
            }
            else {
                if ( $groupHandler->getPermission($_POST['category']) < WRITE_ACCESS ) {
                    error(6);
                }

                $file['category'] = $_POST['category'];
                $fileID = $fileHandler->create($file, $_FILES['file']);
            }

        }


        /* For some reason this is necessary for upload indication to reach
                  * Completed status.
                  */
        if ( $_POST['uploadifydie'] == true ) {
            exit( true );
        }


        if ( isset( $_POST['return'] ) ) {
            SitePage::localRedir($_POST['return'] . '&hash=' . $hash);
        }
        else {
            $page->backToTheFuture(2);
        }
    }
}

/* Upload multiple files */
elseif ( is_array($_POST['description']) && is_numeric($_POST['category']) ) {
    if ( $groupHandler->getPermission($_POST['category']) < WRITE_ACCESS ) {
        error(6);
    }

    for ( $x = 0; $_POST['description'][$x]; $x++ ) {
        $file = array(
            'description' => $_POST['description'][$x], 'hidden' => $_POST['hidden'], 'category' => $_POST['category'],
            'owner' => $_SESSION['id'],
        );

        //grab file out of silly array
        foreach ( $_FILES['file'] as $key => $value ) {
            $fileData[$key] = $value[$x];
        }

        $fileID = $fileHandler->create($file, $fileData);
    }

    SitePage::localRedir('/resources/' . $_POST['category']);
    //$page->backToTheFuture(2);
}

/* Move file from one category to another */
elseif ( is_numeric($_POST['move']) && is_numeric($_POST['categoryID']) ) {
    $result = "success";

    if ( !$file = $fileHandler->getByID($_POST['move']) ) {
        $result = "File not found";
    }

    if ( $groupHandler->getPermission($file['cid']) < WRITE_ACCESS ) {
        $result = "Insufficient permssions";
    }

    if ( $groupHandler->getPermission($_POST['categoryID']) < WRITE_ACCESS ) {
        $result = "Insufficient permssions";
    }

    $fileHandler->update(array( 'id' => $_POST['move'], 'category' => $_POST['categoryID'] ));

    header("Content-type: application/json");
    print json_encode(array( 'result' => $result ));
}

/* Delete a single file */
elseif ( is_numeric($_GET['delete']) ) {
    if ( !$file = $fileHandler->getByID($_GET['delete']) ) {
        error(2);
    }

    if ( $groupHandler->getPermission($file['cid']) < WRITE_ACCESS ) {
        error(6);
    }

    $fileHandler->delete($_GET['delete']);

    if ( $_GET['redirect'] == '' ) {
        $page->backToTheFuture(1);
    }
    else {
        SitePage::localRedir($_GET['redirect']);
    }
}

/* Delete a list of files */
elseif ( is_numeric($_GET['deleteList']) ) {
    $fileList = explode(',', rtrim($_GET['fileList'], ','));

    if ( is_array($fileList) ) {
        foreach ( $fileList as $fileID ) {
            if ( is_numeric($fileID) ) {
                if ( !$file = $fileHandler->getByID($fileID) ) {
                    error(2);
                }

                if ( $groupHandler->getPermission($file['cid']) < WRITE_ACCESS ) {
                    error(6);
                }

                $fileHandler->delete($fileID);
            }
        }
    }

    $linkHandler = new Link;

    $linkList = explode(',', rtrim($_GET['linkList'], ','));
    if ( is_array($linkList) ) {
        foreach ( $linkList as $linkID ) {
            if ( is_numeric($linkID) ) {
                if ( !$link = $linkHandler->getByID($linkID) ) {
                    error(2);
                }

                if ( $groupHandler->getPermission($link['category']) < WRITE_ACCESS ) {
                    error(6);
                }

                $linkHandler->delete($linkID);
            }
        }
    }

    $page->backToTheFuture(1);
}

/* Exceeded PHP max file size */
elseif ( $_SERVER['REQUEST_METHOD'] == 'POST' && $_SERVER['CONTENT_LENGTH'] > 0 && empty($_POST) && empty($_FILES) ) {
    error(10);
}

/* Unknown action */
else {
    error(2);
}
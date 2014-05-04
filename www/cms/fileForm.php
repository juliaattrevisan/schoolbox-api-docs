<?php
require_once( dirname(__FILE__) . '/../../include/Bootstrap.php' );

use \Schoolbox\Resource\FileMapper;
use \Schoolbox\Resource\File;

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

    if ( is_numeric($_POST['fileID']) ) {

        /* Modify existing file */

        /* Double check permission on file
           */

        $currentFile = FileMapper::getInstance()->findById($_POST['fileID']);
        /** @var File $currentFile */


        if ( $groupHandler->getPermission($currentFile->getFolder()) < WRITE_ACCESS ) {
            error(6);
        }


        $currentFile->setDescription($_POST['description']);
        $currentFile->setComment($_POST['comment']);
        $currentFile->setHidden($_POST['hidden']?true:false);
        $currentFile->setModified(new DateTime('now'));



        if ( is_numeric($_POST['myfile']) ) {
            /* Make this appear as a file upload */
            $myFileHandler = new MyFile();

            if ( !$privFile = $myFileHandler->getByID($_POST['myfile']) ) {
                error(41);
            }

            $currentFile->setTempFileLocation(Cfg::get('myfile_dir') . '/' . $privFile['id'] . '.dat');
            $currentFile->setFilename($privFile['realname']);
            $currentFile->setFilesize($privFile['filesize']);

        }
        else if ( $_FILES['file']['name'] && is_uploaded_file($_FILES['file']['tmp_name'])) {
            /* Submitted file with form, run security checks */

            $currentFile->setTempFileLocation($_FILES['file']['tmp_name']);
            $currentFile->setFilename($_FILES['file']['name']);
            $currentFile->setFilesize(filesize($_FILES['file']['tmp_name']));

        }

        try {
            FileMapper::getInstance()->save($currentFile);
            $page->backToTheFuture(2);
        }
        catch (Exception $e) {
            // Output errors
            if($e->getCode() == \Schoolbox\File\Exception::CODE_MIMETYPE_NOT_FOUND) {
                error(11, "Your file, '{$file->getFilename()}'");
            }
            else if($e->getCode() == \Schoolbox\File\Exception::CODE_FILE_TOO_LARGE ) {
                error(10, 'The size of the uploaded file exceeds the file size restrictions of '.Alaress_Byte_Format::bytesToString($file->getMimetype()->getMaxFileSize()).'.');
            }
            else {
                error(9, $e->getMessage());
            }
        }
    }
    else {
        $file = FileMapper::getInstance()->createModel();
        /** @var File $file */

        $file->setOwner($_SESSION['id']);
        $file->setDescription($_POST['description']);
        $file->setComment($_POST['comment']);
        $file->setHidden($_POST['hidden']?true:false);


        if ( is_numeric($_POST['myfile']) ) {
            /* Make this appear as a file upload */
            $myFileHandler = new MyFile();

            if ( !$privFile = $myFileHandler->getByID($_POST['myfile']) ) {
                error(41);
            }

            $file->setTempFileLocation(Cfg::get('myfile_dir') . '/' . $privFile['id'] . '.dat');
            $file->setFilename($privFile['realname']);
            $file->setFilesize($privFile['filesize']);
        }
        elseif ( $_FILES['file']['name'] ) {
            $file->setTempFileLocation($_FILES['file']['tmp_name']);
            $file->setFilename($_FILES['file']['name']);
            $file->setFilesize(filesize($_FILES['file']['tmp_name']));
        }

        /* Add to multiple locations */
        if ( is_array($_POST['category']) ) {

            $fileCollection = FileMapper::getInstance()->createCollection();
            /** @var Schoolbox\Collection $fileCollection */

            foreach ( $_POST['category'] as $cat ) {

                if ( $groupHandler->getPermission($cat) < WRITE_ACCESS ) {
                    error(6);
                }

                $fileDest = clone $file;
                $fileDest->setFolder($cat);
                $fileCollection->add($fileDest);
            }

            $file = $fileCollection;
        }
        else {
            /* Added to a single folder */
            if ( $groupHandler->getPermission($_POST['category']) < WRITE_ACCESS ) {
                error(6);
            }

            $file->setFolder($_POST['category']);
        }

        try {
            FileMapper::getInstance()->save($file);

            /* For some reason this is necessary for upload indication to reach completed status */
            if ( $_POST['uploadifydie'] == true ) {
                exit( true );
            }

            if ( isset( $_POST['return'] ) ) {
                SitePage::localRedir($_POST['return']);
            }
            else {
                $page->backToTheFuture(2);
            }

        }
        catch (Exception $e) {
            // Output errors
            if($e->getCode() == \Schoolbox\File\Exception::CODE_MIMETYPE_NOT_FOUND) {
                error(11, "Your file, '{$file->getFilename()}'");
            }
            else if($e->getCode() == \Schoolbox\File\Exception::CODE_FILE_TOO_LARGE ) {
                error(10, 'The size of the uploaded file exceeds the file size restrictions of '.Alaress_Byte_Format::bytesToString($file->getMimetype()->getMaxFileSize()).'.');
            }
            else {
                error(9, $e->getMessage());
            }
        }
    }
}
/* Move file from one category to another */
elseif ( is_numeric($_POST['move']) && is_numeric($_POST['categoryID']) ) {

    try {
        $file = FileMapper::getInstance()->findById($_POST['move']);

        if ( $groupHandler->getPermission($file->getFolder()) < WRITE_ACCESS ) {
            $result = "Insufficient permssions";
        }
        else if ( $groupHandler->getPermission($_POST['categoryID']) < WRITE_ACCESS ) {
            $result = "Insufficient permssions";
        }
        else {
            $file->setFolder($_POST['categoryID']);
            $file->setModified(new DateTime('now'));

            FileMapper::getInstance()->save($file);
            $result = "success";
        }
    }
    catch (Exception $e) {
            $result = "File not found";
    }

    header("Content-type: application/json");
    print json_encode(array( 'result' => $result ));
}

/* Delete a single file */
elseif ( is_numeric($_GET['delete']) ) {
    try{
        $file = FileMapper::getInstance()->findById($_GET['delete']);

        if ( $groupHandler->getPermission($file->getFolder()) < WRITE_ACCESS ) {
            error(6);
        }

        FileMapper::getInstance()->delete($file);
    }
    catch (Exception $e) {
        error(2);
    }

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

                try{
                    $file = FileMapper::getInstance()->findById($fileID);

                    if ( $groupHandler->getPermission($file->getFolder()) < WRITE_ACCESS ) {
                        error(6);
                    }

                    FileMapper::getInstance()->delete($file);
                }
                catch (Exception $e) {
                    error(2);
                }
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
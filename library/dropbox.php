<?php
/************************************************************
 *                                                           *
 *         Copyright (c) 2002-2003 Lektec Consulting         *
 *                                                           *
 ************************************************************/

/* Class defines interface between file database and software.         *
 * The following actions can be performed; delete, add, update */

define('DROPBOX_COMPONENT', 25);

class Dropbox {
    function Dropbox() {
        //always gonna need the db so lets create one..
	    $this->db = DeprecatedDatabase::getInstance();
        $this->groupHandler = new Group;
    }

    function checkWriteAccess($category) {
        //anyone with read can put something in the dropbox
        if (($permission = $this->groupHandler->getPermission($category)) < READ_ACCESS) {
            error(6);
        }

        //check dropbox exists on page
        if (!$this->checkDropBoxExists($category)) {
            error(6);
        }

        return $permission;
    }

    function checkDropBoxExists($cid) {

        $query = "SELECT ci.id FROM component_instance ci JOIN component_definition cd ON ci.`component_definition_id`=cd.`id` WHERE class = 'Component_Homepage_DropBoxController' AND ci.module = 'homepage' AND identifier = $cid";
        if ($this->db->getOne($query)) {
            return true;
        } else {
            return false;
        }
    }

    function addFile($file_name, $file_type, $file_loc, $description, $category) {
        //check allowed perms to add to specified category before proceeding
        $this->checkWriteAccess($category);

        if (is_readable($file_loc)) {
            $filesize = @filesize($file_loc);
        }

        $file = array(
            'description' => $description,
            'category' => $category,
            'owner' => $_SESSION['id'],
            'realname' => $file_name,
            'submitted' => 'NOW()',
            'mime_type' => $file_type,
            'file_size' => $filesize,
        );

        $this->db->dbInsertRow('dropbox', $file);

        //get file ID from INSERT
        $fileId = $this->db->dbInsertId();

        if ($file_loc) {
            //save file using file ID as name
            $newFileName = $fileId . ".dat";
            copy($file_loc, Cfg::get('dropbox_dir') . "/" . $newFileName);
        }

        return $fileId;

    }

    function deleteFile($id) {
        //check sanity and valid rights
        //check file exists. and user has nescessary rights
        $fileInfo = $this->getFile($id);
        if ($fileInfo['category']) {
            if ($this->checkWriteAccess($fileInfo['category']) < WRITE_ACCESS) {
                error(6);
            }
        } else {
            error(41);
        }

        //ok all checks completed

        //now delete file by updating it as deleted
        $this->db->dbDeleteRow('dropbox', 'id', $id);
        @unlink(Cfg::get('dropbox_dir') . "/" . $id . '.dat');

        return $fileInfo['category'];
    }

    function getFile($id) {
        $query = "SELECT category, description FROM dropbox WHERE id = '$id'";
        return $this->db->getRow($query);
    }

    function getFileList($cid) {
        $query = "SELECT d.id, d.description, u.id as uid, title, firstname, lastname,
						submitted as subdate, iconFile
					FROM dropbox d 
						LEFT JOIN user u ON owner = u.id 
						LEFT JOIN allowedFileTypes af ON d.mime_type = af.mime 
					WHERE category = '$cid'";
        return $this->db->getAll($query);
    }

    function outputFile($id, $save) {
        //sends file
        $query = "SELECT realname,category, mime_type FROM dropbox WHERE id = '$id'";
        $this->db->query($query);

        if ($this->db->countRows() < 1) {
            error(5);
        }

        $result = $this->db->getNextArray();

        if ($this->checkWriteAccess($result['category']) < 2) {
            error(5);
        }

        if ($save) {
            $attach = "attachment;";
        } else {
            $attach = "";
        }

        //all checks completed.. continue with file send
        $filename = Cfg::get('dropbox_dir') . "/" . $id . ".dat";

        //send headers
        header("Content-Type: {$result['mime_type']}");
        header("Content-Disposition: $attach filename=\"" . $result['realname'] . "\"");
        header("Content-Length: " . filesize($filename));
        readfile($filename);
        exit;
    }

    function homepageClean($cid) {

        $query = "SELECT id FROM dropbox WHERE category = '$cid'";
        $result = $this->db->query($query);

        while ($row = $this->db->getNextArray($result)) {
            $this->deleteFile($row['id']);
        }

    }
}


?>

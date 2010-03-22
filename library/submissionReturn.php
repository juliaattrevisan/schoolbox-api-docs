<?php

require_once '../include/model.php';

class SubmissionReturn extends Model {

    function SubmissionReturn () {

        $this->_table = "submission_return";
        $this->_index = "id";

        parent::Model();
    }

    function securityCheck($box_id, $sid)
    {
        //check if person returning work is actual owner of the submission box

        $query = "SELECT owner FROM submission_box WHERE id = '$box_id' AND owner = '".$_SESSION['id']."'";
        if(!is_array($this->db->getRow()))
            error(25);


        //check work exist and it isnt already marked
        $query = "SELECT marked FROM student_submission WHERE box_id = '$box_id' AND owner = '$sid' AND marked = '0'";
        if(!is_array($this->db->getRow()))
            error(26);

    }

    /* just call submissionStudent directly to update box
    function workMarked($box_id, $sid)
    {


            $query = "UPDATE student_submission SET marked = '1' WHERE box_id = '$box_id' AND owner = '$sid'";
            $this->db->query($query);
    } */

     /* Just call create
    function returnWork($sid, $box_id, $comment, $mark)
    {
            //insert comment and mark
            $query = "INSERT INTO submission_return (box_id, comment, mark, owner, returned, deleted)
                            VALUES ('$box_id', '$comment', '$mark', '$sid', NOW(), '0' )";
            $this->db->query($query);
    }*/

    function create($return, $file = FALSE) {
        $return['filename'] = $file['realname'];

        $fileID = parent::create($return);

        if(is_array($file)) {
            $newFileName = $fileId . ".dat";
            move_uploaded_file($file, RETURNS_directory . "/" . $newFileName);
        }
    }

}
?>
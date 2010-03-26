<?php

require_once $_SERVER['DOCUMENT_ROOT'] . '/../include/model.php';

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

    function create($return, $file = FALSE) {
        $return['filename'] = $file['realname'];
        $return['returned'] = 'NOW()';

        $fileID = parent::create($return);

        if(is_array($file)) {

            $newFileName = $fileId . ".dat";
            move_uploaded_file($file, RETURNS_directory . "/" . $newFileName);
        }

        //Update student submission to be marked
        $this->db->update('student_submission', array('box_id','owner'), array('box_id'=>$return['box_id'], 'owner'=>$return['owner'], 'marked'=>'1'));

    }

}
?>
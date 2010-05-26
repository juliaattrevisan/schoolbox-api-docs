<?php

require_once $_SERVER['DOCUMENT_ROOT'] . '/../include/model.php';

class SubmissionReturn extends Model {

    function SubmissionReturn () {

        $this->_table = "submission_return";
        $this->_index = "id";

        parent::Model();
    }

	function getByUser($user_id, $box_id = 0) {
		$query = "SELECT r.*, c.name categoryName, s.description, s.fid FROM ( `".$this->_table."` r, submission_box s, category c )
				  WHERE r.deleted = 0 AND s.deleted = 0 AND r.box_id = s.id AND s.deleted = 0 AND s.category = c.id AND r.owner = $user_id";
	
		if($box_id)
				$query .= " AND r.box_id = $box_id";

		$query .= " ORDER BY returned DESC";
		return $this->db->getAll($query);
	}

    function securityCheck($box_id, $sid)
    {
        //check if person returning work is actual owner of the submission box

        $query = "SELECT owner FROM submission_box WHERE id = '$box_id' AND owner = '".$_SESSION['id']."'";
        if(!is_array($this->db->getRow($query)))
            error(25);


        /* Removed cause you can mark work with out having a student submission and you can also update a mark
        //check work exist
        /*
        $query = "SELECT marked FROM student_submission WHERE box_id = '$box_id' AND owner = '$sid' AND marked = 0";
        if(!is_array($this->db->getRow($query)))
            error(26);
        */
    }

    function create($return, $file = FALSE) {
        $return['filename'] = $file['name'];
        $return['returned'] = 'NOW()';

        $fileID = parent::create($return);

        if(is_array($file)) {

            $newFileName = $fileID . ".dat";
            move_uploaded_file($file['tmp_name'], RETURNS_directory . "/" . $newFileName);
        }

        //Update student submission to be marked
        $this->db->update('student_submission', array('box_id','owner'), array('box_id'=>$return['box_id'], 'owner'=>$return['owner'], 'marked'=>'1'));

    }

}
?>
<?php
require_once $_SERVER['DOCUMENT_ROOT'] . '/../include/model.php';
require_once dirname(__FILE__) . '/group.php';

class SubmissionStudent extends Model {

    function SubmissionStudent () {

        $this->_table = "student_submission";
        $this->_index = "id";

        parent::Model();
    }

	function getByID($id) {
		$query = "SELECT s.*, u.lastname FROM `".$this->_table."` s, user u 
				  WHERE s.owner = u.id AND s.".$this->_index." = $id";	
		return $this->db->getRow($query);
	}

    function getBySynergyID($subBoxID, $synergyID) {
        $query = "SELECT student_submission.id, user.title, user.firstname, user.lastname, user.id AS uid, username, local_email, alt_email,
        DATE_FORMAT(submitted, '%a, %D of %b %l:%i%p') AS date, late, marked, user.synergy_id as synergy_id
        FROM student_submission, user
        WHERE synergy_id = '$synergyID' AND owner = user.id AND box_id = '$subBoxID'";
        return $this->db->getRow($query);
    }

    function getByUserID($subBoxID, $userID) {
        $query = "SELECT student_submission.id, user.title, user.firstname, user.lastname, user.id AS uid, username, local_email, alt_email,
        DATE_FORMAT(submitted, '%a, %D of %b %l:%i%p') AS date, late, marked, user.synergy_id as synergy_id
        FROM student_submission, user
        WHERE owner = '$userID' AND owner = user.id AND box_id = '$subBoxID'";
        return $this->db->getRow($query);
    }

    function getByBoxID($subBoxID) {
         $query = "SELECT student_submission.id, user.title, user.firstname, user.lastname, user.id AS uid, username, local_email, alt_email,
                DATE_FORMAT(submitted, '%a, %D of %b %l:%i%p') AS date, late, marked, user.synergy_id as synergy_id
                FROM student_submission, user
                WHERE owner = user.id AND box_id = '$subBoxID' ORDER BY lastname, firstname";
         return $this->db->getAll($query);
    }

    function securityCheck($box_id) {
        //check if already submitted
        $query = "SELECT s.id FROM student_submission s
				  WHERE s.box_id = '$box_id' AND s.owner = '".$_SESSION['id']."'";
        if(is_array($this->db->getRow($query)))
            error(27);

        //check if submitted assignment is late
        $query = "SELECT UNIX_TIMESTAMP(date) AS overdue, UNIX_TIMESTAMP(open_date) AS open, code, category.id FROM submission_box, category
		WHERE submission_box.id = '$box_id' AND submission_box.category = category.id";
        $result = $this->db->getRow($query);
        if(!is_array($result))
            error(17);

        //check if they are involved in subject (dont allow anyone to submit to box) use involved in
        $groupHandler = new Group;
        if(!$groupHandler->involvedInSubject($result['id']) && !$groupHandler->involvedInGroup($_SESSION['id'], $result['id']))
            error(24);

        $now = date("U");

        //check if its early
        if($result['open'] > $now)
            error(36);

        //check if it is late
        if($result['overdue'] < $now)
            return true;
        else
            return false;
    }

    function create($boxID, $file) {
        $late = $this->securityCheck($boxID);

        $studentSub = array ('owner'    => $_SESSION['id'],
                'realname'  => $file['name'],
                'submitted' => 'NOW()',
                'box_id'    => $boxID,
                'late'      => $late,
        );

		
        $this->db->insert($this->_table, $studentSub);
		$fileId = $this->db->insertID();
		
        //save file using file ID as name
        $newFileName = $fileId . ".dat";
		
        move_uploaded_file($file['tmp_name'], SUBMISSION_directory . "/" . $newFileName);

        return $fileId;

    }

    function delete($id, $clean = true) {
        //delete a student submission to a box so they can resubmit

        if($clean) {
            //delete from database
            parent::delete($id);
            $filename = SUBMISSION_directory . "/" . $id . ".dat";
            @unlink($filename);
        }
        else {
            parent::update(array('deleted' => 'true', 'id' => $id));
        }
    }
}
?>

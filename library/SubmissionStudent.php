<?php

class SubmissionStudent extends DeprecatedModel {

    function SubmissionStudent () {

        $this->_table = "student_submission";
        $this->_index = "id";

        parent::DeprecatedModel();
    }

	function getByID($id) {
		$query = "SELECT s.*, u.lastname, u.firstname FROM `".$this->_table."` s, user u
				  WHERE s.owner = u.id AND s.".$this->_index." = $id";	
		return $this->db->getRow($query);
	}

    // 3 CONVENIENCE WRAPPER METHODS FOR RETRIEVING WITH CERTAIN CONDITIONS

    function getBySynergyID($subBoxID, $synergyID) {
        return $this->getByCondition($subBoxID, array('synergy_id' => $synergyID, 'draft' => false));
    }

    function getByUserID($subBoxID, $userID) {
        return $this->getByCondition($subBoxID, array('`owner`' => $userID, 'draft' => false));
    }

    function getByBoxID($subBoxID) {
        return $this->getByCondition($subBoxID, array());
    }

    protected function getByCondition($subBoxID, array $conditions) {

        $where = array();
        if(count($conditions)) {
            foreach($conditions as $k => $v) {
                $where[] = "$k = '$v'";
            }
            $conditional = implode(' AND ', $where);
        }
        else {
            $conditional = '1=1';
        }

        $query = "SELECT student_submission.id, user.title, user.firstname, user.lastname, user.id AS uid, username,
                         local_email, alt_email, DATE_FORMAT(submitted, '%a, %D of %b %l:%i%p') AS date,
                         DATE_FORMAT(submitted, '%Y-%m-%d %H:%i') as datestamp, realname, late, marked,
                         (student_submission.body IS NOT NULL) AS is_online_submission,
                         user.synergy_id as synergy_id, pd.score, pd.status plag_status, pd.plagscan_id plag_doc_id
                  FROM (student_submission, user)
                  LEFT JOIN plagscan_document pd ON student_submission.id = pd.file_id
                  WHERE $conditional
                  AND owner = user.id
                  AND box_id = '$subBoxID'
                  ORDER BY lastname, firstname, datestamp DESC ";
        return $this->db->getAll($query);

    }
/*
    function getBySynergyID($subBoxID, $synergyID) {
        $query = "SELECT student_submission.id, user.title, user.firstname, user.lastname, user.id AS uid, username,
                         local_email, alt_email, DATE_FORMAT(submitted, '%a, %D of %b %l:%i%p') AS date,
                         DATE_FORMAT(submitted, '%Y-%m-%d %H:%i') as datestamp, late, marked, user.synergy_id as synergy_id,
                         pd.score
                  FROM (student_submission, user)
                  LEFT JOIN plagscan_document pd ON student_submission.id = pd.file_id
                  WHERE synergy_id = '$synergyID'
                  AND owner = user.id
                  AND box_id = '$subBoxID'
                  ORDER BY lastname, firstname, datestamp DESC ";
        return $this->db->getAll($query);
    }

    function getByUserID($subBoxID, $userID) {
        $query = "SELECT student_submission.id, user.title, user.firstname, user.lastname, user.id AS uid, username,
                         local_email, alt_email, DATE_FORMAT(submitted, '%a, %D of %b %l:%i%p') AS date,
                         DATE_FORMAT(submitted, '%Y-%m-%d %H:%i') as datestamp, late, marked, user.synergy_id as synergy_id,
                         pd.score
                  FROM (student_submission, user)
                  LEFT JOIN plagscan_document pd ON student_submission.id = pd.file_id
                  WHERE owner = '$userID'
                  AND owner = user.id
                  AND box_id = '$subBoxID'
                  ORDER BY lastname, firstname, datestamp DESC ";
        return $this->db->getAll($query);
    }

    function getByBoxID($subBoxID) {
         $query = "SELECT student_submission.id, user.title, user.firstname, user.lastname, user.id AS uid, username,
                          local_email, alt_email, DATE_FORMAT(submitted, '%a, %D of %b %l:%i%p') AS date,
                          DATE_FORMAT(submitted, '%Y-%m-%d %H:%i') as datestamp, late, marked, user.synergy_id as synergy_id,
                          pd.score
                   FROM (student_submission, user)
                   LEFT JOIN plagscan_document pd ON student_submission.id = pd.file_id
                   WHERE owner = user.id
                   AND box_id = '$subBoxID'
                   ORDER BY lastname, firstname";
         return $this->db->getAll($query);
    }
*/
	function checkSubmitted($box_id, $userID) {
		$query = "SELECT s.id FROM student_submission s
				  WHERE s.box_id = '$box_id' AND s.owner = '".$userID."'";
		return is_array($this->db->getRow($query));
	}

    function getStatus ($user_id, $submission_box_id) {
        $query = "SELECT sb.open_date, sb.date, sb.late_date, sb.created, ss.submitted, sr.returned, sr.mark
                  FROM submission_box sb
                  LEFT JOIN submission_return sr ON sb.id = sr.box_id AND sr.owner = $user_id
                  LEFT JOIN student_submission ss ON sb.id = ss.box_id AND ss.owner = $user_id
                  WHERE sb.id = $submission_box_id
                  AND sb.deleted = 0
                  AND sb.task_id IS NULL";

		$submission = $this->db->getRow($query);

        /* Convert date fields and set status */
        $date_fields = array('open_date', 'date', 'created', 'late_date', 'submitted', 'returned');

        foreach ($date_fields as $field_name) {
            if (!empty($submission[$field_name])) {
                $submission[$field_name] = new DateTime($submission[$field_name]);
            }
        }

        if (!empty($submission['returned']) && empty($submission['submitted'])) {
            $submission['status'] = 1;
            $submission['relevant_date'] = $submission['date'];
        } elseif (!empty($submission['returned'])) {
            $submission['status'] = 3;
            $submission['relevant_date'] = $submission['returned'];
        } elseif (!empty($submission['submitted'])) {
            $submission['status'] = 2;
            $submission['relevant_date'] = $submission['submitted'];
        } else {
            $submission['status'] = 1;
            $submission['relevant_date'] = $submission['date'];
        }

        return $submission;
    }

    function securityCheck($box_id) {
        //check if already submitted
        if($this->checkSubmitted($box_id, $_SESSION['id'])) {

            $subHandler = new Submission();
            $subbox = $subHandler->getByID($box_id);

            if(!$subbox['multi_submission'])
                error(27);
        }

        //check if submitted assignment is late
        $query = "SELECT
                      UNIX_TIMESTAMP(date) AS overdue, UNIX_TIMESTAMP(open_date) AS open, code, category.id
                  FROM
                      submission_box, category
		          WHERE
		              submission_box.id = '$box_id'
		              AND submission_box.category = category.id";
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

        $studentSub = array (
            'owner'     => $_SESSION['id'],
            'realname'  => $file['name'],
            'submitted' => 'NOW()',
            'box_id'    => $boxID,
            'late'      => $late,
        );
		
        $this->db->insert($this->_table, $studentSub);
		$fileId = $this->db->insertID();

        //save file using file ID as name
        $newFileName = $fileId . ".dat";
		
        move_uploaded_file($file['tmp_name'], Cfg::get('submission_dir') . "/" . $newFileName);

        return $fileId;
    }

    function delete($id, $clean = true) {
        //delete a student submission to a box so they can resubmit

        if($clean) {
            //delete from database
            parent::delete($id);
            $filename = Cfg::get('submission_dir') . "/" . $id . ".dat";
            @unlink($filename);
        }
        else {
            parent::update(array('deleted' => 'true', 'id' => $id));
        }
    }
}
?>

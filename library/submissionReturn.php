<?php

class SubmissionReturn extends DeprecatedModel {

    function SubmissionReturn () {

        $this->_table = "submission_return";
        $this->_index = "id";

        parent::DeprecatedModel();
    }

	function getOneByUser($user_id, $box_id = 0) {
		$query = "SELECT r.*, c.name categoryName, s.name, s.description, s.fid FROM ( `".$this->_table."` r, submission_box s, category c )
				  WHERE r.deleted = 0 AND r.box_id = s.id AND s.category = c.id AND r.owner = '$user_id'";

		if($box_id)
				$query .= " AND r.box_id = $box_id";

		$query .= " ORDER BY returned DESC";
		return $this->db->getRow($query);
	}

	function getByUser($user_id, $box_id = 0, $limit = false) {
		$query = "SELECT r.*, c.name categoryName, s.name, s.description, s.fid, s.max_mark, m.slug
		          FROM ( `".$this->_table."` r, submission_box s, category c )
		          LEFT JOIN submission_mark_type m ON s.mark_type = m.mark_type_id
				  WHERE r.deleted = 0
				  AND r.box_id = s.id
				  AND s.category = c.id
				  AND r.owner = '$user_id'";
	
		if ($box_id) {
			$query .= " AND r.box_id = $box_id";
		}

		$query .= " ORDER BY returned DESC";

		if (is_numeric($limit)) {
			$query .= " LIMIT $limit";
		}

		$result = $this->db->getAll($query);

        // don't try to do marking normalisation if nothing is submitted
        if($result)
            // append normalised mark information onto each of the results
            foreach($result as &$r) {
                $mark = Marksheet_Factory::build($r['slug']);

                // legacy support
                if($r['slug'] == 'free') {
                    $r['visible_mark'] = $r['mark'];
                } else {
                    // for anything other than legacy
                    $r['visible_mark'] = $mark->convertRawMark($r['norm_mark'], $r['max_mark'], true, false);
                }
            }
        return $result;
    }

	/**
	 * Gets all returned work
	 *
	 * @param <type> $user_id
	 * @param <type> $box_id
	 * @param <type> $limit
	 * @return <type>
	 */
	function getAllByUser($user_id, $box_id = 0, $limit = false) {
		$query = "	SELECT
					r.*,
					c.name categoryName,
					s.name, s.description, s.fid
					FROM ( `".$this->_table."` r, submission_box s, category c)
					WHERE r.deleted = 0
					AND r.box_id = s.id
					AND s.category = c.id
					AND r.owner = '$user_id'";

		if ($box_id) {
			$query .= " AND r.box_id = $box_id";
		}

		$query .= " ORDER BY returned DESC";

		if (is_numeric($limit)) {
			$query .= " LIMIT $limit";
		}

		return $this->db->getAll($query);
	}

    function getByUserAndTime($user_id, $timestamp) {
        $query = "SELECT sr.*, sb.max_mark, smt.slug
                  FROM submission_return sr
                  LEFT JOIN submission_box sb ON sr.box_id = sb.id
                  LEFT JOIN submission_mark_type smt ON sb.mark_type = smt.mark_type_id
                  WHERE sr.owner = '$user_id'
                  AND returned = '$timestamp'";
        return $this->db->getRow($query);
    }

    function getLetterGradeByUser($user_id, $box_id = 0) {
        $query = "SELECT g.grade_id, r.comment FROM submission_return r, submission_grade g
                  WHERE r.owner = " . $this->db->smartQuote($user_id) . "
                  AND r.box_id = " . $this->db->smartQuote($box_id) . "
                  AND  g.lower_bound <= r.norm_mark
                  ORDER BY returned DESC, lower_bound DESC
                  LIMIT 1";

        return $this->db->getRow($query);
    }

    function getLetterGradeByValue($mark) {
        $query = "SELECT * FROM submission_grade
                  WHERE lower_bound <= $mark
                  ORDER BY lower_bound DESC
                  LIMIT 0, 1";

        return $this->db->getRow($query);
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
            move_uploaded_file($file['tmp_name'], Cfg::get('returns_dir') . "/" . $newFileName);
        }

        //Update student submission to be marked
        $this->db->update('student_submission', array('box_id','owner'), array('box_id'=>$return['box_id'], 'owner'=>$return['owner'], 'marked'=>'1'));

    }

}
?>
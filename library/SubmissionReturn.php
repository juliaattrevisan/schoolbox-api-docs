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
        $query = "
            SELECT g.*, r.mark, r.comment FROM submission_return r, submission_grade g
            WHERE r.owner = " . $this->db->smartQuote($user_id) . "
            AND r.box_id = " . $this->db->smartQuote($box_id) . "
            AND (g.lower_bound <= r.norm_mark OR r.norm_mark IS NULL)
            ORDER BY returned DESC, lower_bound DESC
            LIMIT 1
        ";

        $result = $this->db->getRow($query);

        // confirm this isn't an error, and if it is, overwrite the values
        $dem = DueWork_ErrorMapper::getInstance();
        $errs = $dem->listDisplayValues();

        if(in_array($result['mark'], $errs)) {
            $result['grade_id'] = $result['mark'];
            $result['caption'] = $result['mark'];
        }
        unset($result['mark']);

        return $result;

    }

    /**
     * @param float $mark
     * @return array|bool
     */
    function getLetterGradeByValue($mark) {
        $query = "SELECT * FROM submission_grade
                  WHERE lower_bound <= $mark
                  ORDER BY lower_bound DESC
                  LIMIT 0, 1";

        return $this->db->getRow($query);
    }

    function getStudentMarksForCategory($catID, $studentID, $ownerID = 0, $year = 0, $semester = 0) {
        $catID = $this->db->smartQuote($catID);
        $studentID = $this->db->smartQuote($studentID);

        $dateCondA = '';
        $dateCondB = '';
        $ownCondA = '';
        $ownCondB = '';

        if ($year) {
            $dateCondA .= " AND DATE_FORMAT(b.date, '%Y') = $year";
            $dateCondB .= " AND DATE_FORMAT(a.start, '%Y') = $year";
        }
        if ($semester) {
            $dateCondA .= " AND DATE_FORMAT(b.date, '%m') " . DueWork_All::getSemesterLogic($semester);
            $dateCondB .= " AND DATE_FORMAT(a.start, '%m') " . DueWork_All::getSemesterLogic($semester);
        }
        if($ownerID) {
            $ownCondA .= " AND b.owner = $ownerID";
            $ownCondB .= " AND a.user_id = $ownerID";
        }

        // get marks from due work
        $sql = "
            SELECT r.id, r.box_id, r.norm_mark
            FROM submission_return r
            LEFT JOIN submission_box b ON r.box_id = b.id
            WHERE b.category = $catID
            AND r.owner = $studentID
            AND b.deleted = 0
            $dateCondA
            $ownCondA
            ORDER BY b.id ASC, r.returned DESC
        ";

        $this->db->query($sql);
        $marks = array();
        while($row = $this->db->getNextArray()) {
            // based on the nature of the query, the first of each different box_id is the most recent mark
            if(array_key_exists($row['box_id'], $marks)) {
                continue;
            }
            $marks[$row['box_id']] = $row['norm_mark'];
        }

        // get marks from online assess
        $sql = "
            SELECT assess_attempt.attempt_id, assess_id, SUM(mark) AS mark, SUM(marks) AS max_mark
            FROM assess_attempt
            LEFT JOIN assess_response ON assess_attempt.attempt_id = assess_response.attempt_id
            LEFT JOIN assess_question ON assess_response.question_id = assess_question.question_id
            WHERE assess_attempt.attempt_id IN (
              SELECT aa.attempt_id
              FROM assess_attempt aa
              LEFT JOIN assess a
                ON aa.assess_id = a.assess_id
              WHERE a.category_id = $catID
                AND aa.user_id = $studentID
                AND aa.deleted_at IS NULL
                $dateCondB
                $ownCondB
              ORDER BY aa.start DESC
            )
            AND assess_question.deleted_at IS NULL
            GROUP BY assess_attempt.assess_id, assess_attempt.attempt_id
            ORDER BY assess_attempt.start DESC
        ";

        $this->db->query($sql);
        $attempts = array();
        while($row = $this->db->getNextArray()) {
            if(!isset($attempts[$row['assess_id']]) && $row['max_mark'] != 0) {
                $attempts[$row['assess_id']] = ($row['mark'] / $row['max_mark']) * 100;
            }
        }

        $merged = array_merge($marks, $attempts);

        // clear out all null values
        $filtered = array();
        foreach($merged as $line) {
            if($line !== null) {
                $filtered[] = $line;
            }
        }

        if(count($filtered)) {
            $avg = array_sum($filtered) / count($filtered);
            $filtered['avg'] = sprintf('%0.2f', $avg);
        }
        else {
            $filtered['avg'] = -1;
        }
        return $filtered;
    }

    /**
     * Converts a number to a letter-grade, and then shortens the string
     *  - 'A+' remains 'A+'
     *  - 'Credit' becomes 'CR'
     *  - 'High Distinction' becomes 'HD'
     *
     * @param float $percentage
     * @return string
     */
    function percent2letter($percentage) {
        $letterArray = $this->getLetterGradeByValue($percentage);
        return $this->shortenGrade($letterArray['caption']);
    }

    /**
     * This function is intended for narrow spaces, so do some string manipulation.
     * If you don't need this, just use self::getLetterGradeByValue()
     *
     * @param $letter
     * @return string
     */
    function shortenGrade($letter) {
        if(strlen($letter) < 3) {
            return $letter;
        }
        // no spaces, take first two letters
        if(strpos($letter, ' ') === false) {
            $rtn = substr($letter, 0, 2);
        }
        // contains slash: is x / y, so leave as is
        elseif(strpos($letter, '/') !== false) {
            $rtn = $letter;
        }
        // spaces: take the first letter from the first 2 words
        else {
            $words = explode(' ', $letter);
            $rtn = '';
            for($i = 0; $i < 2; ++$i) {
                $rtn .= $words[$i][0];
            }
        }
        return strtoupper($rtn);
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

        if (is_array($file)) {
            $this->moveFile($fileID, $file);
        }

        //Update student submission to be marked
        $this->db->update('student_submission', array('box_id','owner'), array('box_id'=>$return['box_id'], 'owner'=>$return['owner'], 'marked'=>'1'));

        return $fileID ? true : false;

    }

    public function update($return, $file = false) {
        if (is_array($file)) {
            $return['filename'] = $file['name'];
        }
        if ($result = parent::update($return)) {
            if (is_array($file)) {
                $this->moveFile($return['id'], $file);
            }
        }
        return $result;
    }

    public function moveFile($file_id, $file) {
        move_uploaded_file($file['tmp_name'], Cfg::get('returns_dir') . "/" . $file_id . ".dat");
    }

}
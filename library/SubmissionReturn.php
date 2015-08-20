<?php

class SubmissionReturn extends DeprecatedModel
{

    function SubmissionReturn()
    {

        $this->_table = "submission_return";
        $this->_index = "id";

        parent::DeprecatedModel();
    }

    function getByBoxID($box_id)
    {
        $query = "SELECT r.*,user.title, user.firstname, user.lastname, user.id AS uid, username, user.synergy_id,
                         user.group_id,
                         local_email, alt_email
                         FROM ( `" . $this->_table . "` r,  user)
                  WHERE r.deleted = 0 AND user.id = r.owner AND box_id = " . $this->db->smartQuote($box_id) . "
                  ORDER BY lastname, firstname DESC ";
        return $this->db->getAll($query);
    }

    /**
     * Gets list of al students who have had work returned for this submission
     * box ID.
     *
     * @param  int $boxId
     *
     * @return array
     */
    public function getStudentListByBoxId($boxId)
    {
        $studentList = array();
        $allReturns  = $this->getByBoxID($boxId);
        if (is_array($allReturns)) {
            foreach ($allReturns as $return) {
                $studentList[] = array(
                    'title'      => $return['title'],
                    'firstname'  => $return['firstname'],
                    'lastname'   => $return['lastname'],
                    'uid'        => $return['uid'],
                    'synergy_id' => $return['synergy_id'],
                    'group_id'   => $return['group_id'],
                );
            }
        }
        return $studentList;
    }

    function getOneByUser($user_id, $box_id = 0)
    {
        $query = "SELECT
                    r.*,
                    c.name categoryName,
                    s.name,
                    s.description,
                    s.fid
                  FROM ( `" . $this->_table . "` r, submission_box s, category c )
                  WHERE r.deleted = 0
                  AND r.box_id = s.id
                  AND s.category = c.id
                  AND s.deleted IS FALSE
                  AND r.owner = '$user_id'";
        if ($box_id) {
            $query .= " AND r.box_id = $box_id";
        }
        $query .= " ORDER BY returned DESC";
        return $this->db->getRow($query);
    }

    function getByUser($user_id, $box_id = 0, $limit = false)
    {
        $query = "SELECT
                    r.*,
                    c.name categoryName,
                    s.name,
                    s.description,
                    s.fid,
                    s.max_mark,
                    m.slug
                  FROM ( `" . $this->_table . "` r, submission_box s, category c )
                  LEFT JOIN submission_mark_type m ON s.mark_type = m.mark_type_id
                  WHERE r.deleted = 0
                  AND r.box_id = s.id
                  AND s.category = c.id
                  AND s.deleted IS FALSE
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
        if ($result) {
            // append normalised mark information onto each of the results
            foreach ($result as &$r) {
                if (!$box_id) {
                    $mark = Marksheet_Factory::build($r['slug'], new \Schoolbox\Assessment\Component\SubmissionBox());
                }
                else {
                    $mapper = new \Schoolbox\Assessment\Component\SubmissionBoxMapper();
                    $box = $mapper->findById($box_id);

                    $mark = Marksheet_Factory::build($r['slug'], $box);
                }
            }
            // legacy support
            if ($r['slug'] == 'free') {
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
     * @param  <type> $user_id
     * @param  <type> $box_id
     * @param  <type> $limit
     *
     * @return <type>
     */
    function getAllByUser($user_id, $box_id = 0, $limit = false)
    {
        $query = "  SELECT
                    r.*,
                    c.name categoryName,
                    s.name, s.description, s.fid
                    FROM ( `" . $this->_table . "` r, submission_box s, category c)
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

    function getByUserAndTime($user_id, $timestamp)
    {
        $query = "SELECT sr.*, sb.max_mark, smt.slug
                  FROM submission_return sr
                  LEFT JOIN submission_box sb ON sr.box_id = sb.id
                  LEFT JOIN submission_mark_type smt ON sb.mark_type = smt.mark_type_id
                  WHERE sr.owner = '$user_id'
                  AND returned = '$timestamp'";
        return $this->db->getRow($query);
    }

    function getLetterGradeByUser($user_id, $box_id = 0)
    {
        // join on submission_errors and both grades table.
        // Return whichever one is most recent.
        $errorResult = $this->db->getRow(
            "
                        SELECT e.*, r.returned, r.mark, r.comment
                        FROM submission_return r
                        INNER JOIN submission_errors e ON r.mark = e.display_as
                        WHERE r.owner = " . $this->db->smartQuote($user_id) . "
            AND r.box_id = " . $this->db->smartQuote($box_id) . "
            ORDER BY returned DESC
            LIMIT 1
        "
        );
        $gradeResult = $this->db->getRow("
            SELECT
              g.*,
              r.returned,
              r.mark,
              r.comment
            FROM submission_grade g
            INNER JOIN submission_return r ON g.lower_bound <= r.norm_mark
            INNER JOIN submission_box b ON r.box_id = b.id
            WHERE r.owner = " . $this->db->smartQuote($user_id) . "
            AND r.box_id = " . $this->db->smartQuote($box_id) . "
            AND g.submission_grade_group_id = b.submission_grade_group_id
            ORDER BY returned DESC, lower_bound DESC
            LIMIT 1
        ");

        // Do both error and grade result exist? Use most recent of the two.
        if (!empty($errorResult) && !empty($gradeResult)) {
            if ($gradeResult['returned'] > $errorResult['returned']) {
                $errorResult = false;
            } else {
                $gradeResult = false;
            }
        }

        if (empty($errorResult)) {
            $result = $gradeResult;
        } else {
            $result             = $errorResult;
            $result['grade_id'] = $result['caption'] = $result['mark'];
        }
        unset($result['mark']);

        return $result;

    }

    /**
     * @param float $mark
     * @param int $gradeGroupId The grade group to fetch from.
     *                          defaults to config default_grade_group.
     *
     * @return array|bool
     */
    function getLetterGradeByValue($mark, $gradeGroupId = null)
    {
        if (!isset($gradeGroupId)) {
            $gradeGroupId = Config::get('default_grade_group');
        }
        $query = "
            SELECT * FROM submission_grade
            WHERE lower_bound <= '{$this->db->quote($mark)}'
            AND submission_grade_group_id = '{$this->db->quote($gradeGroupId)}'
            ORDER BY lower_bound DESC
            LIMIT 0, 1
        ";
        return $this->db->getRow($query);
    }

    function getStudentMarksForCategory($catID, $studentID, $ownerID = 0, $year = 0, $semester = 0)
    {
        $catID     = $this->db->smartQuote($catID);
        $studentID = $this->db->smartQuote($studentID);

        $dateCondA = '';
        $dateCondB = '';
        $ownCondA  = '';
        $ownCondB  = '';

        if ($year) {
            $dateCondA .= " AND DATE_FORMAT(b.date, '%Y') = $year";
            $dateCondB .= " AND DATE_FORMAT(a.start, '%Y') = $year";
        }
        if ($semester) {
            $dateCondA .= " AND DATE_FORMAT(b.date, '%m') " . DueWork_All::getSemesterLogic($semester);
            $dateCondB .= " AND DATE_FORMAT(a.start, '%m') " . DueWork_All::getSemesterLogic($semester);
        }
        if ($ownerID) {
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
            AND b.task_id IS NULL

            $dateCondA
            $ownCondA
            ORDER BY b.id ASC, r.returned DESC
        ";

        $this->db->query($sql);
        $marks = array();
        while ($row = $this->db->getNextArray()) {
            // based on the nature of the query, the first of each different box_id is the most recent mark
            if (array_key_exists($row['box_id'], $marks)) {
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
        while ($row = $this->db->getNextArray()) {
            if (!isset($attempts[$row['assess_id']]) && $row['max_mark'] != 0) {
                $attempts[$row['assess_id']] = ($row['mark'] / $row['max_mark']) * 100;
            }
        }

        $merged = array_merge($marks, $attempts);

        // clear out all null values
        $filtered = array();
        foreach ($merged as $line) {
            if ($line !== null) {
                $filtered[] = $line;
            }
        }

        if (count($filtered)) {
            $avg             = array_sum($filtered) / count($filtered);
            $filtered['avg'] = sprintf('%0.2f', $avg);
        } else {
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
     * @param float  $percentage
     * @param array  $gradeGroupIds The grade groups to turn to a letter grade.
     *                              Defaults to config default_grade_group
     * @param string $separator
     *
     * @return string
     */
    function percent2letter($percentage, $gradeGroupIds = array(), $separator = ' / ')
    {
        $letterArrays = array();
        if (empty($gradeGroupIds)) {
            $letterArrays[] = $this->getLetterGradeByValue($percentage);
        }
        foreach ($gradeGroupIds as $gradeGroupId) {
            $letterArrays[] = $this->getLetterGradeByValue($percentage, $gradeGroupId);
        }

        $results = array();
        foreach ($letterArrays as $letterArray) {
            $results[] = $this->shortenGrade($letterArray['caption']);
        }
        return implode($separator, $results);
    }

    /**
     * This function is intended for narrow spaces, so do some string manipulation.
     * If you don't need this, just use self::getLetterGradeByValue()
     *
     * @param $letter
     *
     * @return string
     */
    function shortenGrade($letter)
    {
        if (strlen($letter) < 3) {
            return $letter;
        }
        // no spaces, take first two letters
        if (strpos($letter, ' ') === false) {
            $rtn = substr($letter, 0, 2);
        } // contains slash: is x / y, so leave as is
        elseif (strpos($letter, '/') !== false) {
            $rtn = $letter;
        } // spaces: take the first letter from the first 2 words
        else {
            $words = explode(' ', $letter);
            $rtn   = '';
            for ($i = 0; $i < 2; ++$i) {
                $rtn .= $words[$i][0];
            }
        }
        return strtoupper($rtn);
    }

    function securityCheck($box_id)
    {
        $groupHandler = new Group();

        /* All staff can update marks now for more relaxed security */
        $query = "SELECT category FROM submission_box WHERE id = '$box_id'";
        $cid   = $this->db->getOne($query);

        if ($cid) {
            if ($groupHandler->getPermission($cid) >= WRITE_ACCESS) {
                return true;
            } else {
                error(25);
            }
        } else {
            //Submission box does not exist
            error(25);
        }
    }

    function create($return, $file = false)
    {
        $return['filename'] = $file['name'];
        $return['returned'] = 'NOW()';

        $fileID = parent::create($return);

        if (is_array($file)) {
            $this->moveFile($fileID, $file);
        }

        //Update student submission to be marked
        $this->db->update(
            'student_submission',
            array('box_id', 'owner'),
            array('box_id' => $return['box_id'], 'owner' => $return['owner'], 'marked' => '1')
        );

        return $fileID ? true : false;

    }

    public function update($return, $file = false)
    {
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

    public function moveFile($file_id, $file)
    {
        move_uploaded_file($file['tmp_name'], Cfg::get('returns_dir') . "/" . $file_id . ".dat");
    }
}

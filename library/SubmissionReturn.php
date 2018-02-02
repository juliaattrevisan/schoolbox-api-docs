<?php

/**
 * Class SubmissionReturn
 * @deprecated
 */
class SubmissionReturn extends DeprecatedModel
{

    /**
     * SubmissionReturn constructor.
     * @deprecated
     */
    function __construct()
    {

        $this->_table = "submission_return";
        $this->_index = "id";

        parent::__construct();
    }

    /**
     * @deprecated
     *
     * @param $box_id
     * @return array|bool
     */
    function getByBoxID($box_id)
    {
        $query = "SELECT DISTINCT   user.title, 
                                    user.firstname, 
                                    user.lastname, 
                                    user.id AS uid, 
                                    user.synergy_id, 
                                    user.group_id 
                  FROM              `" . $this->_table . "` r
                  JOIN              user ON user.id = r.owner
                  WHERE             r.deleted_at IS NULL AND
                                    r.published_at IS NOT NULL AND
                                    box_id = " . $this->db->smartQuote($box_id) . " AND 
                                    user.deleted_at IS NULL
                  ORDER BY          lastname, 
                                    firstname DESC ";

        return $this->db->getAll($query);
    }

    /**
     * Gets list of all students who have had work returned for this submission box ID.
     * @deprecated
     *
     * @param  int $boxId
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


    /**
     * @deprecated
     *
     * @param      $user_id
     * @param int  $box_id
     * @param bool $limit
     * @return array|bool
     * @throws \Schoolbox\Mapper\Exception
     */
    function getByUser($user_id, $box_id = 0, $limit = false)
    {
        $query = "SELECT
                    r.*,
                    c.name categoryName,
                    s.name,
                    s.description,
                    -1 AS fid,
                    s.max_mark,
                    m.slug
                  FROM ( `" . $this->_table . "` r, submission_box s, folder c )
                  INNER JOIN assessment a ON s.assessment_id = a.id
                  LEFT JOIN submission_mark_type m ON s.mark_type = m.mark_type_id
                  WHERE r.deleted_at IS NULL
                  AND r.box_id = s.id
                  AND s.category = c.id
                  AND a.deleted_at IS NULL
                  AND r.owner = '$user_id'";
        if ($box_id) {
            $query .= " AND r.box_id = $box_id";
        }
        $query .= " ORDER BY published_at DESC";
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
     * @deprecated
     *
     * @param $user_id
     * @param $timestamp
     * @return array|bool|null
     */
    function getByUserAndTime($user_id, $timestamp)
    {
        $query = "SELECT sr.*, sb.max_mark, smt.slug
                  FROM submission_return sr
                  LEFT JOIN submission_box sb ON sr.box_id = sb.id
                  LEFT JOIN submission_mark_type smt ON sb.mark_type = smt.mark_type_id
                  WHERE sr.owner = '$user_id'
                  AND published_at = '$timestamp'";
        return $this->db->getRow($query);
    }


    /**
     * @deprecated
     *
     * @param     $user_id
     * @param int $box_id
     * @return array|bool|null
     */
    function getLetterGradeByUser($user_id, $box_id = 0)
    {
        // join on submission_errors and both grades table.
        // Return whichever one is most recent.
        $errorResult = $this->db->getRow(
            "
                        SELECT e.*, r.published_at as returned, r.mark, r.comment
                        FROM submission_return r
                        INNER JOIN submission_errors e ON r.mark = e.display_as
                        WHERE r.owner = " . $this->db->smartQuote($user_id) . "
            AND r.box_id = " . $this->db->smartQuote($box_id) . "
            ORDER BY r.published_at DESC
            LIMIT 1
        "
        );
        $gradeResult = $this->db->getRow("
            SELECT
              g.*,
              r.published_at as returned,
              r.mark,
              r.comment
            FROM submission_grade g
            INNER JOIN submission_return r ON g.lower_bound <= r.norm_mark
            INNER JOIN submission_box b ON r.box_id = b.id
            WHERE r.owner = " . $this->db->smartQuote($user_id) . "
            AND r.box_id = " . $this->db->smartQuote($box_id) . "
            AND g.submission_grade_group_id = b.submission_grade_group_id
            ORDER BY r.published_at DESC, lower_bound DESC
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
     * @deprecated
     *
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

    /**
     * Converts a number to a letter-grade, and then shortens the string
     *  - 'A+' remains 'A+'
     *  - 'Credit' becomes 'CR'
     *  - 'High Distinction' becomes 'HD'
     * @deprecated
     *
     * @param float  $percentage
     * @param array  $gradeGroupIds The grade groups to turn to a letter grade.
     *                              Defaults to config default_grade_group
     * @param string $separator
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
     * @deprecated
     *
     * @param $letter
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

    /**
     * @deprecated
     * @param $box_id
     * @return bool
     */
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
                return false;
            }
        }

        return false;
    }
}

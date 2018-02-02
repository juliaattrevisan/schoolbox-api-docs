<?php

/* Due Work: Recent work. Should be a multiple of 3 because there are three
* types: Due, Awaiting and Marked.
*/
use Schoolbox\Collection;

define('UPCOMING_WORK_TYPE', 1);
define('AWAITING_WORK_TYPE', 2);
define('RETURNED_WORK_TYPE', 3);
define('OVERDUE_WORK_TYPE', 4);
define('RECENT_WORK_LIMIT', 12);

/**
 * Class Submission
 * @deprecated use Schoolbox\Assessment\SubmissionBoxMapper
 */
class Submission extends DeprecatedModel implements \Schoolbox\IHomepageCleanable
{

    /**
     * Submission constructor.
     * @deprecated
     */
    function __construct()
    {

        $this->_table = "submission_box";
        $this->_index = "id";

        parent::__construct();
    }

    /**
     * @deprecated
     *
     * @param $boxID
     * @return array|bool
     */
    function getByID($boxID)
    {
        return $this->getByField('s.id', $boxID);
    }

    /**
     * @deprecated
     *
     * @param string $field
     * @param string|array $value
     * @return array|bool
     */
    function getByField($field, $value)
    {

        // "securitae"
        $field = $this->db->quote($field);

        if (is_array($value) && !empty($value)) {
            foreach ($value as &$v) {
                $v = "'" . $this->db->quote($v) . "'";
            }
            unset($v);
            $conditional = 'IN (' . implode(',', $value) . ')';
        } else {
            $conditional = "= '" . $this->db->quote($value) . "'";
        }

        // general extraction query for submission boxes
        $query = "
            SELECT
              s.*, GROUP_CONCAT(cc.code) `code`, c.name AS categoryName, c.id AS cid, '' AS comment, ft.name typeName,
              smt.slug mark_type_slug
            FROM submission_box s
            JOIN folder c ON s.category = c.id
            LEFT JOIN folder_code cc ON cc.folder_id = c.id AND cc.deleted_at IS NULL
            LEFT JOIN lu_work_type ft ON s.type = ft.id
            LEFT JOIN submission_mark_type smt ON s.mark_type = smt.mark_type_id
            WHERE $field $conditional
            AND s.task_id IS NULL
            GROUP BY s.id, c.id
        ";

        $this->db->query($query);

        $boxes = array();

        while ($row = $this->db->getNextArray()) {
            // if we haven't seen this box yet, create it
            if (!isset($boxes[$row['id']])) {
                $boxes[$row['id']] = $row;
            }
        }

        $output = array_values($boxes);

        // return just the one (dodgy, but it will break too much not to do this)
        if (count($boxes) == 1) {
            return $output[0];
        } // return everything
        elseif (count($boxes) > 1) {
            return $output;
        } // return failure
        else {
            return false;
        }

    }


    /**
     * @deprecated
     * @param $box_id
     * @return mixed
     */
    function getBoxCount($box_id)
    {
        $query = "
            SELECT COUNT(DISTINCT(owner))
            FROM student_submission
            WHERE box_id = '$box_id' AND draft IS FALSE
        ";
        $count['total'] = $this->db->getOne($query);
        $query = "
            SELECT COUNT(DISTINCT(owner))
            FROM submission_return
            WHERE box_id = '$box_id'
        ";
        $count['marked'] = $this->db->getOne($query);
        return $count;
    }

    /**
     * @deprecated
     * @param $id
     * @return array|bool|null
     */
    function getLetterGradeByID($id)
    {
        $query = "SELECT * FROM submission_grade WHERE grade_id = '$id'";
        return $this->db->getRow($query);
    }

    /**
     * @deprecated
     * @param bool $includeDisabled
     * @return array|bool
     */
    function getMarkTypes($includeDisabled = false)
    {
        $query = "SELECT *
                  FROM submission_mark_type";

        if (!$includeDisabled) {
            $query .= " WHERE enabled = '1'";
        }

        return $this->db->getAll($query);
    }

    /**
     * @deprecated
     * @param $slug
     * @return array|bool|null
     */
    function getMarkTypeBySlug($slug)
    {
        $query = "SELECT *
                  FROM submission_mark_type
                  WHERE slug = '$slug'";

        return $this->db->getRow($query);
    }

    /**
     * @deprecated
     * @param     $page
     * @param int $box_id
     */
    function logPageView($page, $box_id = 0)
    {
        //Page 1=due work page, 2 = returned work page (deprecated), 3 = submission page


        /* TODO: rewrite this code in simpler method probably use mysql insert/update */
        $testquery = "SELECT datetime FROM submission_log WHERE page = '$page'
            AND user = '" . $_SESSION['id'] . "' AND sid = '$box_id'";

        $this->db->query($testquery);

        if ($row = $this->db->getNextArray()) {
            $query = "UPDATE submission_log SET datetime = NOW() WHERE page =
                    '$page' AND user = '" . $_SESSION['id'] . "' AND sid = '$box_id'";
        } else {
            $query = "INSERT INTO submission_log (datetime, user, page, sid) VALUES
                            (NOW(), '" . $_SESSION['id'] . "', '$page', '$box_id')";
        }

        $this->db->query($query);
    }

    /**
     * @deprecated
     * @param      $id
     * @param bool $clean
     * @return bool
     */
    function delete($id, $clean = false)
    {
        return $this->update(array('id' => $id, 'deleted' => 1));
    }

    /**
     * @deprecated
     * @param $cid
     * @return bool
     */
    function homepageClean($cid)
    {
        return $this->db->update($this->_table, 'category', array('category' => $cid, 'deleted' => 1));
    }

    /**
     * This function is supposed to determine a list of categories the user WAS involved in based on due work submission
     * @deprecated
     *
     * @param int                 $userID
     * @param Alaress_Date_Format $from
     * @param Alaress_Date_Format $to
     * @return array
     */
    function submittedToCategoryList($userID, Alaress_Date_Format $from, Alaress_Date_Format $to)
    {
        $quotedId = $this->db->smartQuote($userID);
        $parts    = ["(ss.`owner` = $quotedId OR sr.`owner` = $quotedId)"];
        if ($from->isNull() && $to->isNull()) {
            // noop: allow all dates
        } elseif (!$from->isNull() && $to->isNull()) {
            // only from is set
            $fromDate = $this->db->smartQuote($from->getDatabaseDate());
            $parts[]  = "(DATE(ss.submitted) >= $fromDate OR DATE(sr.returned) >= $fromDate)";
        } elseif ($from->isNull() && !$to->isNull()) {
            // only to is set
            $toDate  = $this->db->smartQuote($to->getDatabaseDate());
            $parts[] = "(DATE(ss.submitted) <= $toDate OR DATE(sr.returned) <= $toDate)";
        } else {
            // neither are null
            $fromDate = $this->db->smartQuote($from->getDatabaseDate());
            $toDate   = $this->db->smartQuote($to->getDatabaseDate());
            $parts[]  = "(DATE(ss.submitted) BETWEEN $fromDate AND $toDate OR DATE(sr.returned) BETWEEN $fromDate AND $toDate)";
        }
        $wherePart = implode(' AND ', $parts);
        $sql       = "SELECT folder.name, folder.id, GROUP_CONCAT(cc.code) AS `code`
                FROM folder
                LEFT JOIN folder_code cc ON folder.id = cc.folder_id AND cc.deleted_at IS NULL
                WHERE folder.id IN (
                    SELECT DISTINCT sb.category
                    FROM submission_box sb
                    LEFT JOIN student_submission ss ON ss.`box_id` = sb.`id`
                    LEFT JOIN submission_return sr ON sb.id = sr.`box_id`
                    WHERE $wherePart
                )
                GROUP BY folder.id
                ORDER BY folder.name";

        return $this->db->getAll($sql) ?: [];

    }

    function cmpDates($a, $b)
    {
        if ($a['date'] < $b['date']) {
            return -1;
        } elseif ($a['date'] > $b['date']) {
            return 1;
        }
        return 0;
    }
}

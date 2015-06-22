<?php

/* Due Work: Recent work. Should be a multiple of 3 because there are three
* types: Due, Awaiting and Marked.
*/
use Schoolbox\Collection;
use Schoolbox\Role;

define('UPCOMING_WORK_TYPE', 1);
define('AWAITING_WORK_TYPE', 2);
define('RETURNED_WORK_TYPE', 3);
define('OVERDUE_WORK_TYPE', 4);
define('RECENT_WORK_LIMIT', 12);

class Submission extends DeprecatedModel
{

    function Submission()
    {

        $this->_table = "submission_box";
        $this->_index = "id";

        parent::DeprecatedModel();
    }

    /**
     * Counts the number of submission boxes this subject owns.
     *
     * @param  <type> $categoryID
     *
     * @return <type>
     */
    function countSubmissionBoxes($categoryID)
    {
        $query = "SELECT COUNT(category) FROM " . $this->_table . " WHERE category = '$categoryID'";

        return $this->db->getOne($query);
    }

    /**
     * Gets all the user's submissions for this subject.
     *
     * @param  <type> $categoryID
     *
     * @return <type>
     */
    function getSubmissionsForSubject($categoryID)
    {
        $query = "SELECT
                      submission_box.category, submission_box.name, submission_box.description,
                      student_submission.owner, realname
                  FROM submission_box
                  LEFT JOIN student_submission
                  ON submission_box.id = student_submission.box_id
                  WHERE student_submission.owner = '{$_SESSION['id']}'
                  AND category = '$categoryID'
                  ORDER BY category";

        return $this->db->getAll($query);
    }

    /**
     * @param $boxID
     *
     * @return array|bool
     */
    function getByID($boxID)
    {
        return $this->getByField('s.id', $boxID);
    }

    /**
     * @param $boxID
     *
     * @return array|bool
     */
    function getByName($boxName)
    {
        return $this->getByField('s.name', $boxName);
    }

    /**
     * @param string $field
     * @param string|array $value
     *
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
              s.*, GROUP_CONCAT(cc.code) `code`, c.name AS categoryName, c.id AS cid, file.comment, ft.name typeName,
              smt.slug mark_type_slug
            FROM submission_box s
            JOIN category c ON s.category = c.id
            LEFT JOIN categoryCode cc ON cc.categoryID = c.id AND cc.deleted_at IS NULL
            LEFT JOIN file ON s.fid = file.id
            LEFT JOIN lu_submission_type ft ON s.type = ft.id
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
     * Collate a list of events to appear on the My Work screen. Amended to
     * include online assessments as well.
     *
     * @param  <type> $date
     * @param  <type> $userID
     * @param  <type> $categoryList
     *
     * @return <type>
     */
    function getByDate($date, $userID, $categoryList)
    {
        $query = "(SELECT
                    FALSE as is_assessment,
                    s.id, s.category, s.type, s.owner, s.open_date, s.date, s.late_date, s.fid, s.accept_submission,
                    s.deleted, s.name, s.description, s.created, s.mark_type, s.max_mark,
                    t.realname,
                    c.name categoryName,
                    GROUP_CONCAT(cc.code) categoryCode
                    FROM ( submission_box s, category c )
                    LEFT JOIN student_submission t ON t.box_id = s.id
                    AND t.owner = $userID
                    LEFT JOIN categoryCode cc ON cc.categoryID = c.id AND cc.deleted_at IS NULL
                    WHERE s.deleted = 0
                    AND s.category = c.id
                    AND DATE(s.date) = DATE(FROM_UNIXTIME({$date}))
                    AND c.id IN (" . implode(',', $categoryList) . ")
                    AND s.task_id IS NULL
                    GROUP BY s.id
                    )
                    UNION
                    (SELECT
                        TRUE as is_assessment,
                        assess.assess_id as id,
                        true AS accept_submission,
                        assess.category_id as category,
                        11 as type,
                        assess.user_id as `owner`,
                        assess.start as open_date,
                        assess.finish as `date`,
                        assess.finish as late_date,
                        -1 as fid,
                        assess.deleted_at as deleted,
                        assess.title as name,
                        assess.description as description,
                        assess.created_at as created,
                        '' AS mark_type,
                        '' AS max_mark,
                        '' as realname,
                        category.name as categoryName,
                        categoryCode.code as categoryCode
                    FROM assess
                    LEFT JOIN category ON category.id = assess.category_id
                    LEFT JOIN categoryCode ON categoryCode.categoryID = assess.category_id
                        AND categoryCode.deleted_at IS NULL
                    WHERE assess.deleted_at IS NULL
                    AND assess.availability = 'assess'
                    AND assess.task_id IS NULL
                    AND DATE(assess.finish) = DATE(FROM_UNIXTIME({$date}))
                    AND category.id IN (" . implode(',', $categoryList) . ")
                    GROUP BY id
                    )
                    ORDER BY date";

        $result = $this->db->getAll($query);

        return $result;
    }

    function getUpcoming($userID, $categoryList)
    {
        $query = "SELECT s.*, UNIX_TIMESTAMP(s.date) date, ft.name as type, file.realname, file.id file_id, a.inline, c.name categoryName
                  FROM ( submission_box s, category c )
                  LEFT JOIN student_submission t ON t.box_id = s.id AND t.owner = '$userID'
                  LEFT JOIN file ON s.fid = file.id
                  LEFT JOIN allowedFileTypes a ON file.mime_type = a.mime
                  LEFT JOIN lu_submission_type ft ON s.type = ft.id
                  WHERE s.category = c.id AND s.deleted = 0 AND s.late_date > NOW() AND s.owner != '$userID' AND t.id IS NULL
                  AND c.id IN (" . implode(',', $categoryList) . ")
                  AND s.task_id IS NULL
                  ORDER BY s.date";

        return $this->db->getAll($query);
    }

    /**
     * Gets all the due/submitted/returned work via Submission Boxes.
     *
     * @param $userID
     * @param $categoryIDList
     *
     * @return array|bool
     */
    public function getAllDueWork($userID, $categoryIDList, $year = 0, $semester = 0)
    {

        if (!$categoryIDList) {
            return array();
        }

        if (!$year) {
            $year = date('Y');
        }
        $semesterTest = DueWork_All::getSemesterLogic($semester);

        $outerQuery = "
            SELECT
            b.id AS bid,
            s.id AS sid,
            r.id AS rid,
            UNIX_TIMESTAMP(b.late_date) AS lateDate,
            UNIX_TIMESTAMP(b.date) AS dueDate,
            c.name AS categoryName,
            b.name,
            b.description,
            b.created, s.submitted, r.returned
            FROM submission_box b
            LEFT JOIN category c ON c.id = b.category
            LEFT JOIN student_submission s ON s.box_id = b.id AND (s.owner = '$userID' OR s.owner IS NULL)
            LEFT JOIN submission_return r ON r.box_id = b.id AND (r.owner = '$userID' OR r.owner IS NULL)
            WHERE b.deleted = 0
            AND b.task_id IS NULL
            AND b.category IN (" . implode(',', $categoryIDList) . ")
            AND DATE_FORMAT(b.date, '%Y') = $year
            AND DATE_FORMAT(b.date, '%m') $semesterTest
            GROUP BY b.id
            ORDER BY categoryName, b.created DESC, s.submitted DESC, r.returned DESC
        ";

        $outerResult = $this->db->query($outerQuery);

        /* Instead of unconditionally nesting an unindexable subquery in the above, which may or may not
         * actually return anything, only perform the inner query if it's contextually appropriate (ie. the row
         * in question actually has a returnID).
         */
        $collection = array();

        // use the derived returnIDs to additionally retrieve marks, comments, etc
        while ($row = $this->db->getNextArray($outerResult)) {
            if ($row['rid']) {
                $innerQuery   = "
                    SELECT mark, comment
                    FROM submission_return
                    WHERE box_id = '{$row['bid']}'
                    AND owner = '$userID'
                    ORDER BY returned DESC
                    LIMIT 1
                ";
                $collection[] = array_merge($row, $this->db->getRow($innerQuery));
            } else {
                $collection[] = $row;
            }
        }
        return $collection;
    }

    /**
     * The most recent activity regarding work submissions and returns.
     *
     * @param  <type> $categoryIDList
     * @param  <type> $limit
     *
     * @return <type>
     */
    function getRecentWork($userID, $categoryIDList, $type)
    {
        $query = "SELECT
            b.id AS bid,
            s.id AS sid,
            r.id AS rid,
            f.id AS fid,";

        if ($type == UPCOMING_WORK_TYPE) {
            $query .= ' UNIX_TIMESTAMP(b.date) AS date, ';
            $orderBy = 'b.date';
        } else {
            if ($type == AWAITING_WORK_TYPE) {
                $query .= ' UNIX_TIMESTAMP(s.submitted) AS date, ';
                $orderBy = 's.submitted';
            } else {
                if ($type == RETURNED_WORK_TYPE) {
                    $query .= ' UNIX_TIMESTAMP(r.returned) AS date, ';
                    $orderBy = 'r.returned';
                }
            }
        }

        $query .= "
            c.name AS categoryName,
            c.id as cid,
            b.name, b.description, b.accept_submission,
            b.open_date, b.date, b.late_date, b.created, s.submitted, r.returned,
            r.comment,
            r.mark
            FROM submission_box b
            LEFT JOIN category c ON c.id = b.category
            LEFT JOIN student_submission s ON s.box_id = b.id AND (s.owner = '$userID' OR s.owner IS NULL)
            LEFT JOIN submission_return r ON r.box_id = b.id  AND (r.owner = '$userID' OR r.owner IS NULL)
            LEFT JOIN file f ON b.fid = f.id
            WHERE b.deleted = 0
            AND b.task_id IS NULL
            AND b.category IN (" . implode(',', $categoryIDList) . ")
            AND $orderBy IS NOT NULL";

        /* For items that have been submitted but not returned
         */
        if ($type == UPCOMING_WORK_TYPE) {
            $query .= " AND s.submitted IS NULL
                        AND r.returned IS NULL
                        ORDER BY $orderBy DESC";
        } else {
            if ($type == AWAITING_WORK_TYPE) {
                $query .= " AND s.submitted IS NOT NULL
                        AND r.returned IS NULL
                        ORDER BY $orderBy DESC";
            } else {
                if ($type == RETURNED_WORK_TYPE) {
                    $query .= " AND r.returned IS NOT NULL
                        GROUP BY rid
                        ORDER BY $orderBy DESC";
                }
            }
        }

        $result = $this->db->getAll($query);

        if (!empty($result)) {
            /* Convert date fields and set status */
            $date_fields = array('open_date', 'date', 'created', 'late_date', 'submitted', 'returned');

            foreach ($result as &$work) {
                foreach ($date_fields as $field_name) {
                    if (!empty($work[$field_name])) {
                        $work[$field_name] = new DateTime($work[$field_name]);
                    }
                }

                if ($type == UPCOMING_WORK_TYPE) {
                    $work['relevant_date'] = $work['date'];
                } else {
                    if ($type == AWAITING_WORK_TYPE) {
                        $work['relevant_date'] = $work['submitted'];
                    } else {
                        if ($type == RETURNED_WORK_TYPE) {
                            $work['relevant_date'] = $work['returned'];
                        }
                    }
                }

                $work['status'] = $type;
            }
        }

        return $result;
    }

    /**
     * Gets all the due/submitted/returned work via Submission Boxes.
     * Unions Submissions and Returns, orders by the date they occurred
     *
     * @param  <type> $categoryIDList
     * @param  <type> $limit
     *
     * @return <type>
     */
    function getSubmissionHistory($userID, $boxID)
    {
        $query = "(SELECT
            b.id AS bid,
            s.id AS sid,
            s.realname AS sfname,
            s.owner as owner,
            NULL AS rid,
            f.id AS fid,
            UNIX_TIMESTAMP(s.submitted) AS date,
            NULL AS comment,
            NULL AS mark,
            NULL AS norm_mark,
            NULL AS filename
            FROM submission_box b
            LEFT JOIN student_submission s ON s.box_id = b.id
            LEFT JOIN file f ON b.fid = f.id
            WHERE b.deleted = 0
            AND b.task_id IS NULL
            AND s.owner = '$userID'
            AND b.id = '$boxID'
            AND s.submitted IS NOT NULL)
        UNION (SELECT
            b.id AS bid,
            NULL AS sid,
            NULL AS sfname,
            b.owner as owner,
            r.id AS rid,
            f.id AS fid,
            UNIX_TIMESTAMP(r.returned) AS date,
            r.comment,
            r.mark,
            r.norm_mark,
            r.filename
            FROM submission_box b
            LEFT JOIN submission_return r ON r.box_id = b.id
            LEFT JOIN file f ON b.fid = f.id
            WHERE b.deleted = 0
            AND b.task_id IS NULL
            AND r.owner = '$userID'
            AND b.id = '$boxID'
            AND r.returned IS NOT NULL)
        ORDER BY date DESC";

        return $this->db->getAll($query);
    }

    function getAllForCategory($categoryID, $config = false, $distinct = false, $userID = -1)
    {
        if ($userID == -1) {
            $userID = $_SESSION['id'];
        }
        $query = "SELECT sb.id, sb.category, ft.name as typeName, sb.owner, sb.task_id,
                              UNIX_TIMESTAMP(sb.open_date) AS open,
                              UNIX_TIMESTAMP(sb.date) AS date,
                              UNIX_TIMESTAMP(sb.late_date) AS late,
                              f.id fileID, f.realname, sb.name, sb.description, sb.created, ss.id studentWork,
                              af.inline, ss.submitted, sr.returned, sb.accept_submission, sb.multi_submission
                              FROM submission_box sb
                              LEFT JOIN  file f ON sb.fid = f.id AND f.deleted = '0'
                              LEFT JOIN allowedFileTypes af ON f.mime_type = mime
                              LEFT JOIN student_submission ss ON ss.box_id = sb.id AND (ss.owner = '$userID' OR ss.owner IS NULL)
                              LEFT JOIN submission_return sr ON sr.box_id = sb.id AND (sr.owner = '$userID' OR sr.owner IS NULL)
                              LEFT JOIN lu_submission_type ft ON sb.type = ft.id
                              WHERE
                              sb.category = '" . $categoryID . "'
                              AND sb.deleted = '0'
                              AND sb.task_id IS NULL";
        if ($distinct) {
            $query .= " GROUP BY id";
        }
        $query .= " ORDER BY sb.date DESC";

        if ($config['size'] > 0) {
            $query .= " LIMIT 0, " . $config['size'];
        }

        return $this->db->getAll($query);
    }

    function getAllForUserCategory($categoryID, $userID = 0, $year = 0, $semester = 0)
    {
        $dateCondA  = '';
        $dateCondB  = '';
        $ownerCondA = '';
        $ownerCondB = '';

        if ($year) {
            $dateCondA .= " AND DATE_FORMAT(sb.date, '%Y') = $year";
            $dateCondB .= " AND DATE_FORMAT(assess.finish, '%Y') = $year";
        }
        if ($semester) {
            $dateCondA .= " AND DATE_FORMAT(sb.date, '%m') " . DueWork_All::getSemesterLogic($semester);
            $dateCondB .= " AND DATE_FORMAT(assess.finish, '%m') " . DueWork_All::getSemesterLogic($semester);
        }
        if ($userID) {
            $ownerCondA = "AND sb.owner = '$userID'";
            $ownerCondB = "AND assess.user_id = '$userID'";
        }

        $q[] = "
            SELECT
                sb.id,
                sb.category,
                ft.id AS type_id,
                ft.name AS typeName,
                sb.owner,
                UNIX_TIMESTAMP(sb.open_date) AS open,
                UNIX_TIMESTAMP(sb.date) AS date,
                UNIX_TIMESTAMP(sb.late_date) AS late,
                sb.name,
                sb.description,
                sb.created,
                smt.slug,
                sb.accept_submission,
                sb.mark_visibility
            FROM submission_box sb
            LEFT JOIN lu_submission_type ft ON sb.type = ft.id
            LEFT JOIN submission_mark_type smt ON sb.mark_type = smt.mark_type_id
            WHERE sb.category = '$categoryID'
            $ownerCondA
            AND sb.deleted = '0'
            AND sb.task_id IS NULL
            $dateCondA
        ";

        $q[] = "
            SELECT
                assess.assess_id AS id,
                assess.category_id AS category,
                11 AS type_id,
                'Quiz' AS typeName,
                assess.user_id AS owner,
                UNIX_TIMESTAMP(assess.start) AS open,
                UNIX_TIMESTAMP(assess.finish) AS date,
                '' AS late,
                assess.title AS name,
                assess.description AS description,
                assess.created_at AS created,
                assess.availability,
                assess.mark_visibility
            FROM assess
            WHERE category_id = '$categoryID'
            AND assess.availability = 'assess'
            AND assess.deleted_at IS NULL
            $ownerCondB
            AND assess.task_id IS NULL
            $dateCondB
        ";

        $r = array();
        foreach ($q as $k => $query) {
            $r[$k] = $this->db->getAll($query);
            if (!is_array($r[$k])) {
                $r[$k] = array();
            }
        }

        $result = call_user_func_array('array_merge', $r);
        usort($result, array($this, 'cmpDates'));

        // keep the interface consistent and return false if there's nothing here
        return count($result) ? $result : false;
    }


    function getHomework($categoryID, $config = false, $type = 3)
    {

        $query = "SELECT sb.id, sb.category, ft.name as typeName, sb.owner,
                              UNIX_TIMESTAMP(sb.open_date) AS open,
                              UNIX_TIMESTAMP(sb.date) AS date,
                              UNIX_TIMESTAMP(sb.late_date) AS late,
                              f.id fileID, f.realname, sb.name, sb.description, sb.created, ss.id studentWork, af.inline
                              FROM submission_box sb
                              LEFT JOIN  file f ON sb.fid = f.id AND f.deleted = '0'
                              LEFT JOIN allowedFileTypes af ON f.mime_type = mime
                              LEFT JOIN student_submission ss ON ss.box_id = sb.id AND ss.owner = '" . $_SESSION['id'] . "'
                              LEFT JOIN lu_submission_type ft ON sb.type = ft.id
                              WHERE sb.category = '" . $categoryID . "' AND sb.deleted = '0' AND sb.type = '" . $type . "'
                              AND sb.task_id IS NULL
                              ORDER BY sb.date DESC";

        if ($config['size'] > 0) {
            $query .= " LIMIT 0, " . $config['size'];
        }

        return $this->db->getAll($query);
    }

    /**
     * Simplified this query so we can use it just to find out what categories a user has submission boxes in
     *
     * @param  $userID
     *
     * @return array|bool
     */
    public function getAllForUser($userID, $year = 0, $semester = 0)
    {
        $dateConditionA = '';
        $dateConditionB = '';

        if ($year) {
            $dateConditionA .= " AND DATE_FORMAT(submission_box.date, '%Y') = $year";
            $dateConditionB .= " AND DATE_FORMAT(assess.start, '%Y') = $year";
        }
        if ($semester) {
            $dateConditionA .= " AND DATE_FORMAT(submission_box.date, '%m') " . DueWork_All::getSemesterLogic(
                    $semester
                );
            $dateConditionB .= " AND DATE_FORMAT(assess.start, '%m') " . DueWork_All::getSemesterLogic($semester);
        }

        $query = "
            (
                SELECT
                    submission_box.id,
                    submission_box.name,
                    submission_box.description,
                    submission_box.accept_submission,
                    submission_box.category catid,
                    submission_box.mark_visibility
                FROM submission_box
                JOIN category ON submission_box.category = category.id AND category.deleted_at IS NULL
                WHERE submission_box.owner = '$userID'
                AND submission_box.deleted = '0'
                AND submission_box.task_id IS NULL
                $dateConditionA
            )
            UNION
            (
                SELECT
                    assess.assess_id,
                    assess.title,
                    assess.description,
                    true AS accept_submission,
                    assess.category_id AS catid,
                    assess.mark_visibility
                FROM assess
                JOIN category ON assess.category_id = category.id AND  category.deleted_at IS NULL
                WHERE assess.deleted_at IS NULL
                AND assess.task_id IS NULL
                AND assess.availability = 'assess'
                AND assess.user_id = '$userID'
                $dateConditionB
            )
            ORDER BY catid DESC";

        return $this->db->getAll($query);
    }

    /**
     * This function is similar to getAllForUsers, but returns aggregated results.
     * Written for welcome component Most Recent Submissions
     *
     * @param $userID
     *
     * @return array|bool
     */
    public function getTotalSubmissionsByOwner($userID)
    {

        /* Six month display threshold for Most Recent Submission
         */
        $threshold = 6;

        $query = "(SELECT *, COUNT(id) AS `count` FROM (
                        SELECT  sb.id,
                            sb.name,
                            c.name AS category_name,
                            c.id as cid,
                            DATE_FORMAT(sb.date, '%a, %D %b %l:%i%p') AS `date`,
                            DATE_FORMAT(submitted, '%Y-%m-%d %H:%i') AS datestamp,
                            '' AS availability
                        FROM student_submission ss
                        LEFT JOIN submission_box sb ON ss.box_id = sb.id
                        LEFT JOIN category c ON c.id = sb.category
                        LEFT JOIN categoryCode co ON co.categoryID = c.id AND co.deleted_at IS NULL
                        WHERE sb.owner = '$userID'
                        AND sb.deleted = '0'
                        AND (DATE_SUB(NOW(), INTERVAL {$threshold} MONTH) <= submitted)
                        ORDER BY datestamp DESC
                        ) submissions
                        GROUP BY id
                        ORDER BY submitted DESC
                    )
                    UNION
                    (
                        SELECT *, COUNT(id) AS `count` FROM (
                        SELECT	a.assess_id AS id,
                            title AS `name`,
                            c.name AS category_name,
                            c.id as cid,
                            DATE_FORMAT(a.finish, '%a, %D %b %l:%i%p') AS `date`,
                            DATE_FORMAT(aa.finish, '%Y-%m-%d %H:%i') AS datestamp,
                            a.availability
                        FROM assess_attempt aa
                        LEFT JOIN assess a ON a.assess_id = aa.assess_id
                        LEFT JOIN category c ON c.id = a.category_id
                        LEFT JOIN categoryCode co ON co.categoryID = c.id AND co.deleted_at IS NULL
                        WHERE a.availability = 'assess'
                        AND aa.finish != '0000-00-00 00:00'
                        AND a.user_id = '$userID'
                        AND a.deleted_at IS NULL
                        AND (DATE_SUB(NOW(), INTERVAL {$threshold} MONTH) <= aa.finish)
                        ) attempts
                        GROUP BY id
                        ORDER BY aa.finish DESC
                    )
                    ORDER BY datestamp DESC";

        return $this->db->getAll($query);
    }

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
            WHERE box_id = '$box_id' AND is_confirmation_of_submission IS FALSE
        ";
        $count['marked'] = $this->db->getOne($query);
        return $count;
    }

    function getTypes()
    {
        $query = "SELECT * FROM lu_submission_type";
        return $this->db->getAll($query);
    }

    /**
     * Get the custom letter grades from the database
     *
     * @return array
     */
    function getLetterGrades()
    {
        // in addition to the db, give 'none' option.
        $default = array(
            array(
                'grade_id'    => '-1',
                'lower_bound' => '-1',
                'raw_grade'   => '-1',
                'caption'     => '(none)'
            )
        );
        $query   = "SELECT * FROM submission_grade";

        return array_merge($default, $this->db->getAll($query));
    }

    function getLetterGradeByID($id)
    {
        $query = "SELECT * FROM submission_grade WHERE grade_id = '$id'";
        return $this->db->getRow($query);
    }


    function getMarkTypes($includeDisabled = false)
    {
        $query = "SELECT *
                  FROM submission_mark_type";

        if (!$includeDisabled) {
            $query .= " WHERE enabled = '1'";
        }


        return $this->db->getAll($query);
    }

    function getMarkTypeByID($id)
    {
        $query = "SELECT *
                  FROM submission_mark_type
                  WHERE mark_type_id = '$id'";

        return $this->db->getRow($query);
    }

    function getMarkTypeBySlug($slug)
    {
        $query = "SELECT *
                  FROM submission_mark_type
                  WHERE slug = '$slug'";

        return $this->db->getRow($query);
    }

    function getSubmissionBoxMarkType($box_id)
    {
        $query = "SELECT slug
                  FROM submission_box s
                  LEFT JOIN submission_mark_type mt ON s.mark_type = mt.mark_type_id
                  WHERE s.id = $box_id";
        return $this->db->getRow($query);
    }

    function getSubmissionBoxMarkTypeByName($box_name)
    {
        $query = "SELECT slug
                  FROM submission_box s
                  LEFT JOIN submission_mark_type mt ON s.mark_type = mt.mark_type_id
                  WHERE s.name = $box_name";
        return $this->db->getRow($query);
    }


    /**
     * Get all the types except the id's mentioned in $vals. Used to exclude
     * Online Assessments from selection on createSubmission/
     *
     * @param  <type> $vals
     *
     * @return <type>
     */
    function getTypesExcept($vals)
    {
        $query = "SELECT * FROM lu_submission_type";

        if (is_array($vals) && count($vals)) {
            $query .= " WHERE id NOT IN (" . implode(',', $vals) . ")";
        }

        return $this->db->getAll($query);
    }

    function getActivityLog($userID, $limit = 5, $categoryList)
    {
        $implodedCategoryList = implode(',', $categoryList);

        $query = " ( SELECT open_date activity_date, s.type, f.name typeName,s.id box_id, s.name, s.description, s.category, c.name categoryName, s.fid, 'new' FROM submission_box s, category c, lu_submission_type f
                      WHERE s.category = c.id AND f.id = s.type AND open_date < NOW() AND c.id IN ("
            . implode(',', $categoryList)
            . ") )
                    UNION
                    ( SELECT `date`, u.type, l.name typeName, u.id box_id, u.name, u.description, u.category, e.name categoryName, u.fid, 'overdue' FROM ( submission_box u, category e, lu_submission_type l )
                      LEFT JOIN student_submission d ON d.box_id = u.id AND d.owner = $userID
                      WHERE d.id IS NOT NULL AND u.category = e.id AND l.id = u.type AND `date` < NOW() AND e.id IN ("
            . implode(',', $categoryList) . ") )
                    UNION
                    ( SELECT `late_date`, t.type, i.name typeName, t.id box_id, t.name, t.description, t.category, a.name categoryName, t.fid, 'closed' FROM submission_box t, category a, lu_submission_type i
                      WHERE t.category = a.id AND i.id = t.type AND `late_date` < NOW() AND a.id IN ("
            . implode(',', $categoryList) . ") )
                    UNION
                    ( SELECT g.`submitted` activity_date, m.type, g.realname typeName, m.id box_id, m.name, m.description, m.category, n.name categoryName, m.fid, 'submitted'  FROM  student_submission g, submission_box m, category n
                      WHERE g.box_id = m.id AND m.category = n.id AND g.owner = $userID )
                    UNION
                   ( SELECT v.`returned` activity_date, w.type, v.mark typeName, w.id box_id, w.name, w.description, w.category, x.name categoryName, v.id fid, 'returned'  FROM  submission_return v, submission_box w, category x
                      WHERE v.box_id = w.id AND w.category = x.id AND v.owner = $userID )
                    UNION
                   (SELECT start AS activity_date,
                        assess.type,
                        lu_submission_type.name AS typeName,
                        assess.assess_id AS box_id,
                        assess.title AS name,
                        assess.description AS description,
                        assess.category_id,
                        category.name AS categoryName,
                        '' AS fid,
                        'new'
                     FROM assess
                     LEFT JOIN lu_submission_type ON lu_submission_type.id = assess.type
                     LEFT JOIN category ON category.id = assess.category_id
                     WHERE start < NOW()
                     AND assess.category_id IN ($implodedCategoryList)
                    )
                    UNION
                   (SELECT finish AS activity_date,
                        assess.type,
                        lu_submission_type.name AS typeName,
                        assess.assess_id AS box_id,
                        assess.title AS name,
                        assess.description AS description,
                        assess.category_id,
                        category.name AS categoryName,
                        '' AS fid,
                        'closed'
                     FROM assess
                     LEFT JOIN lu_submission_type ON lu_submission_type.id = assess.type
                     LEFT JOIN category ON category.id = assess.category_id
                     WHERE finish < NOW()
                     AND assess.category_id IN ($implodedCategoryList))
                    ORDER BY activity_date DESC LIMIT $limit";

        return $this->db->getAll($query);
    }

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

    function getAssignments($categoryID, $config = false)
    {
        return $this->getHomework($categoryID, $config, 2);
    }

    function delete($id, $clean = false)
    {
        return $this->update(array('id' => $id, 'deleted' => 1));
    }

    function homepageClean($cid)
    {
        return $this->db->update($this->_table, 'category', array('category' => $cid, 'deleted' => 1));
    }

    function getBoxOwner($box_id)
    {
        $query = "SELECT owner
                 FROM submission_box
                 WHERE id = $box_id";

        return $this->db->getRow($query);
    }

    /**
     * Get the name of a lu_submission_type by id. Used in submissions reporting
     *
     * @param $typeId
     *
     * @return array|bool
     */
    public function getType($typeId)
    {
        $query = "
            SELECT name
            FROM lu_submission_type
            WHERE id = '$typeId'
        ";

        return $this->db->getRow($query);
    }

    public function getAllYearsByUser($userID = 0)
    {
        if (!$userID) {
            $userID = $_SESSION['id'];
        }

        $sql = "SELECT DISTINCT DATE_FORMAT(`submitted`, '%Y') `year`
                FROM `student_submission`
                WHERE `owner` = $userID
                ORDER BY `submitted` DESC";

        $years  = array();
        $result = $this->db->query($sql);

        // rather than user getAll(), we want this as an ordinal array, not associative, so iterate manually
        while ($y = mysqli_fetch_assoc($result)) {
            $years[] = $y['year'];
        }

        return $years;
    }

    /**
     * This function is supposed to determine a list of categories the user WAS involved in based on due work submission
     *
     * @param     $userID
     * @param     $year
     * @param     $semester
     * @param     $includeSubjectList
     * @param int $accessType
     */
    function submittedToCategoryList($userID, $year, $semester)
    {
        $semesterTest = DueWork_All::getSemesterLogic($semester);

        $sql = "SELECT category.name, category.id, GROUP_CONCAT(cc.code) AS `code`
                FROM category
                LEFT JOIN categoryCode cc ON category.id = categoryID AND cc.deleted_at IS NULL
                WHERE category.id IN (
                    SELECT DISTINCT sb.category
                    FROM submission_box sb
                    LEFT JOIN student_submission ss ON ss.`box_id` = sb.`id`
                    LEFT JOIN submission_return sr ON sb.id = sr.`box_id`
                    WHERE (ss.`owner` = $userID OR sr.`owner` = $userID)
                    AND ((
                        DATE_FORMAT(ss.submitted, '%Y') = $year AND DATE_FORMAT(ss.submitted, '%m') $semesterTest
                    ) OR (
                        DATE_FORMAT(sr.returned, '%Y') = $year AND DATE_FORMAT(sr.returned, '%m') $semesterTest
                    ))
                )
                GROUP BY category.id
                ORDER BY category.name";

        return $this->db->getAll($sql);

    }

    /**
     * Retreieve all the upcoming or expired-but-relevant due work for a student
     *
     * @param $studentID
     *
     * @return array
     */
    function getAllUpcomingWorkAndAssessments($studentID)
    {
        $groupHandler = new Group();

        // start by getting the membership for the student in question
        $involvement = $groupHandler->involvedInCategoryList($studentID);
        $catIDs      = array();

        if (is_array($involvement) && count($involvement)) {
            foreach ($involvement as $cat) {
                $catIDs[] = $cat['id'];
            }
        }

        return $this->getUpcomingWorkAndAssessmentsByCategory($studentID, $catIDs);
    }


    /**
     * Somehow a merge and sort is ~200-300% faster than a Union of these two queries...
     *
     * @param $cats
     *
     * @return array
     */
    function getUpcomingWorkAndAssessmentsByCategory($studentID, $cats)
    {
        if (is_array($cats) && count($cats)) {
            $conditional = 'IN (' . implode(',', $cats) . ')';
        } elseif (is_numeric($cats)) {
            $conditional = '= ' . (int)$cats;
        } else {
            $conditional = '= 0';
        }

        $this_year = date('Y');
        $q0        = "
            SELECT
              '0' kind, sb.id, sb.name `title`, ft.name `type`,
              UNIX_TIMESTAMP(sb.publish_date) AS `publish_date`,
              UNIX_TIMESTAMP(sb.open_date) AS `open_date`,
              UNIX_TIMESTAMP(sb.date) AS `date`,
              UNIX_TIMESTAMP(sb.late_date) AS late_date,
              c.`name` subj_name, c.id subj_id, sb.accept_submission submittable, sb.multi_submission multi_submit, COUNT(ss.id) submit_count
            FROM submission_box sb
            LEFT JOIN lu_submission_type ft ON ft.id = sb.type
            LEFT JOIN category c ON c.`id` = sb.`category`
            LEFT JOIN student_submission ss ON sb.id = ss.box_id AND ss.owner = '$studentID' AND ss.draft IS FALSE
            LEFT JOIN submission_return sr ON sb.id = sr.box_id AND sr.owner = '$studentID'
            WHERE c.id $conditional AND sb.deleted IS FALSE
            AND YEAR(sb.date) = $this_year
            AND sb.deleted IS FALSE
            AND sb.task_id IS NULL
            AND sr.id IS NULL
            GROUP BY sb.id
            ORDER BY sb.date ASC
            ";

        $q1 = "
            SELECT
              '1' kind, a.assess_id id, a.title, ft.name `type`,
              UNIX_TIMESTAMP(a.start) AS `publish_date`,
              UNIX_TIMESTAMP(a.start) AS `open_date`,
              UNIX_TIMESTAMP(a.finish) AS `date`,
              UNIX_TIMESTAMP(a.finish) AS `late_date`,
              c.`name` subj_name, c.id subj_id, a.max_attempts, COUNT(aa.attempt_id) attempt_count
            FROM assess a
            LEFT JOIN lu_submission_type ft ON ft.id = a.`type`
            LEFT JOIN category c ON c.`id` = a.`category_id`
            LEFT JOIN assess_attempt aa ON a.`assess_id` = aa.`assess_id` AND aa.user_id = '$studentID'
            WHERE c.id $conditional
            AND a.availability = 'assess'
            AND a.deleted_at IS NULL
            AND a.task_id IS NULL
            AND YEAR(a.finish) = $this_year
            GROUP BY a.assess_id
            ORDER BY a.finish ASC
        ";

        /* execute queries and retrieve */
        $r0 = $this->db->getAll($q0);
        $r1 = $this->db->getAll($q1);

        /* merge, and sort by date */
        if (!is_array($r0)) {
            $r0 = array();
        }
        if (!is_array($r1)) {
            $r1 = array();
        }
        $r = array_merge($r0, $r1);
        usort($r, array($this, 'cmpDates'));

        /* iterate and output */
        return $r;

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

    /**
     * Retrieves all students that are able to submit to this submission box, or
     * who have submitted to it already.
     *
     * @param \Schoolbox\Assessment\Component\SubmissionBox $box
     *
     * @return \Schoolbox\Collection|\Schoolbox\User[]
     */
    public function getAllParticipantsForSubmission(\Schoolbox\Assessment\Component\SubmissionBox $box)
    {
        $studentList = array();

        if ($box->getCategory()->getCodeString() && Cfg::get('external_enable')) {
            $synHandler = new External_Class();

            $studentListClassCode = $synHandler->getStudents($box->getCategory()->getCodeString());

            if (is_array($studentListClassCode)) {
                $studentList = array_merge($studentList, $studentListClassCode);
            }
        }

        // list all students from group membership
        $groupHandler         = new Group();
        $studentListFromGroup = $groupHandler->getMemberList($box->getCategory()->getId());
        if (is_array($studentListFromGroup)) {
            $studentList = array_merge($studentList, $studentListFromGroup);
        }

        // list all students who have already submitted
        $studentSubHandler          = new SubmissionStudent();
        $studentListFromSubmissions = $studentSubHandler->getStudentListByBoxId($box->getId());
        if (is_array($studentListFromSubmissions)) {
            $studentList = array_merge($studentList, $studentListFromSubmissions);
        }

        // list all students who have been marked
        $studentRetHandler      = new SubmissionReturn();
        $studentListFromReturns = $studentRetHandler->getStudentListByBoxId($box->getId());
        if (is_array($studentListFromReturns)) {
            $studentList = array_merge($studentList, $studentListFromReturns);
        }

        // Sort all students by last name, then first name
        usort(
            $studentList, function ($a, $b) {
                $last = strcmp($a['lastname'], $b['lastname']);
                if ($last == 0) {
                    return strcmp($a['firstname'], $b['firstname']);
                }
                return $last;
            }
        );

        /* Convert those to standardised user objects
         */
        $userMapper = new \Schoolbox\UserMapper();

        // let's dedupe this list, shall we?
        $seen = [];

        /* External sources won't provide a uid value, so find a user by
         * using the external id instead
         */
        foreach ($studentList as $currentStudent) {
            try {
                if (isset($currentStudent['uid'])) {

                    $userId = $currentStudent['uid'];

                    if (empty($seen[$userId])) {
                        $user = $userMapper->findById($userId);
                        $seen[$userId] = $user;
                    }

                } else {
                    // we don't have a user ID, so we can't check for duplicate before running this query :(
                    $user = $userMapper->findOneByColumn('synergy_id', $currentStudent['synergy_id']);

                    $userId = $user->getId();

                    if (empty($seen[$userId])) {
                        $seen[$userId] = $user;
                    }
                }
            } catch (Schoolbox\Mapper\Exception $e) {
                // noop - ignore unfound users
            }
        }

        return new Collection(array_values($seen));
    }
}

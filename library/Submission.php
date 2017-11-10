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
     *
     * @param int                 $categoryID
     * @param int                 $userID
     * @param Alaress_Date_Format $from
     * @param Alaress_Date_Format $to
     * @return bool|mixed
     */
    function getAllForUserCategory($categoryID, $userID = 0, Alaress_Date_Format $from = null, Alaress_Date_Format $to = null)
    {
        $dateCondA  = '';
        $dateCondB  = '';
        $ownerCondA = '';
        $ownerCondB = '';

        if (isset($from) && !$from->isNull()) {
            $date       = $this->db->smartQuote($from->getDatabaseDate());
            $dateCondA .= ' AND DATE(sb.date)       >= '.$date;
            $dateCondB .= ' AND DATE(assess.finish) >= '.$date;
        }
        if (isset($to) && !$to->isNull()) {
            $date       = $this->db->smartQuote($to->getDatabaseDate());
            $dateCondA .= ' AND DATE(sb.date)       <= '.$date;
            $dateCondB .= ' AND DATE(assess.finish) <= '.$date;
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
            LEFT JOIN lu_work_type ft ON sb.type = ft.id
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

    /**
     * Simplified this query so we can use it just to find out what categories a user has submission boxes in
     * @deprecated
     *
     * @param int                 $userID
     * @param Alaress_Date_Format $from
     * @param Alaress_Date_Format $to
     * @return array|bool
     */
    public function getAllForUser($userID, Alaress_Date_Format $from = null, Alaress_Date_Format $to = null)
    {
        $dateConditionA = '';
        $dateConditionB = '';

        if (isset($from) && !$from->isNull()) {
            $date            = $this->db->smartQuote($from->getDatabaseDate());
            $dateConditionA .= ' AND DATE(submission_box.date) >= '.$date;
            $dateConditionB .= ' AND DATE(assess.start)        >= '.$date;
        }
        if (isset($to) && !$to->isNull()) {
            $date            = $this->db->smartQuote($to->getDatabaseDate());
            $dateConditionA .= ' AND DATE(submission_box.date) <= '.$date;
            $dateConditionB .= ' AND DATE(assess.start)        <= '.$date;
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
                JOIN folder ON submission_box.category = folder.id AND folder.deleted_at IS NULL
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
                JOIN folder ON assess.category_id = folder.id AND  folder.deleted_at IS NULL
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
     * @deprecated
     *
     * @param $userID
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
                        LEFT JOIN folder c ON c.id = sb.category
                        LEFT JOIN folder_code co ON co.folder_id = c.id AND co.deleted_at IS NULL
                        WHERE sb.owner = '$userID'
                        AND sb.deleted = '0'
                        AND (DATE_SUB(NOW(), INTERVAL {$threshold} MONTH) <= submitted)
                        ORDER BY datestamp DESC
                        ) submissions
                        GROUP BY id
                        ORDER BY datestamp DESC
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
                        LEFT JOIN folder c ON c.id = a.category_id
                        LEFT JOIN folder_code co ON co.folder_id = c.id AND co.deleted_at IS NULL
                        WHERE a.availability = 'assess'
                        AND aa.finish != '0000-00-00 00:00'
                        AND a.user_id = '$userID'
                        AND a.deleted_at IS NULL
                        AND (DATE_SUB(NOW(), INTERVAL {$threshold} MONTH) <= aa.finish)
                        ) attempts
                        GROUP BY id
                        ORDER BY datestamp DESC
                    )
                    ORDER BY datestamp DESC";

        return $this->db->getAll($query);
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

    /**
     * Retreieve all the upcoming or expired-but-relevant due work for a student
     * @deprecated
     *
     * @param $studentID
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
     * @deprecated
     *
     * @param $cats
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
            LEFT JOIN lu_work_type ft ON ft.id = sb.type
            LEFT JOIN folder c ON c.`id` = sb.`category`
            LEFT JOIN student_submission ss ON sb.id = ss.box_id AND ss.owner = '$studentID' AND ss.submitted_at IS NOT NULL
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
            LEFT JOIN lu_work_type ft ON ft.id = a.`type`
            LEFT JOIN folder c ON c.`id` = a.`category_id`
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
     * @deprecated
     *
     * @param \Schoolbox\Assessment\Component\SubmissionBox $box
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
                    $user = $userMapper->findByExternalId($currentStudent['synergy_id']);

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

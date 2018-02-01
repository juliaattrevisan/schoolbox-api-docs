<?php
/************************************************************
 *                                                           *
 *         Copyright (c) 2013 Alaress Pty Ltd                *
 *                                                           *
 ************************************************************/

//display what has change since the last login for the user

class Activity {
    function __construct() {
        //setup db
	    $this->db = DeprecatedDatabase::getInstance();
    }

    function submissionDetails($id) {
        //check for any new work
        $count['duework'] = $this->newDueWork($id);
        $count['retwork'] = $this->newReturnedWork($id);
        $count['onlineAssessments'] = $this->newOnlineAssessments($id);
        $count['reviewedOnlineAssessments'] = $this->newReviewedOnlineAssessments($id);

        //check for new submissions in category submission boxes
        $subboxes = $this->newSubmission($id);

        $count['subboxes'] = $subboxes;
        return $count;
    }

    function set_var($varName, $varContents) {
        $this->t_body->set_var($varName, $varContents);
    }


    function lastCatVisit($id) {
        //get last visit to category
        $query = "SELECT datetime FROM homepage_log WHERE cid = '$id' AND user = '" . $_SESSION['id'] . "'";
        $result = $this->db->getRow($query);
        if ($result['datetime']) {
            return $result['datetime'];
        } else {
            return "0000-00-00";
        }
    }


    function lastDueWorkVisit() {
        //get the last visit by a student to due work
        $query = "SELECT datetime FROM submission_log WHERE page = '1' AND user = '" . $_SESSION['id'] . "'";
        $result = $this->db->getRow($query);
        if ($result['datetime']) {
            return $result['datetime'];
        } else {
            return "0000-00-00";
        }
    }

    function lastReturnedWorkVisit() {
        //get last visit to returned work
        $query = "SELECT datetime FROM submission_log WHERE page = '2' AND user = '" . $_SESSION['id'] . "'";
        $result = $this->db->getRow($query);
        if ($result['datetime']) {
            return $result['datetime'];
        } else {
            return "0000-00-00";
        }
    }

    function newCategory($id) {

        $lastcatvisit = $this->lastCatVisit($id);

        //check if any new news has been submitted
        $query = "SELECT COUNT(*) AS newscount FROM subject_news WHERE deleted = 0 AND code = $id AND
			datetime_posted >= '$lastcatvisit' AND datetime_expires >= NOW()";
        $result = $this->db->getRow($query);

        $count['news'] = $result['newscount'];

        //check if any new blogs has been submitted
        $query = "SELECT COUNT(*) AS blogcount FROM blog WHERE deleted_at IS NULL AND folder_id = $id AND
			created_at >= '$lastcatvisit'";
        $result = $this->db->getRow($query);

        $count['blog'] = $result['blogcount'];

        //check if any new files have been submitted
        $query = "SELECT COUNT(*) AS filecount FROM file WHERE
			deleted_at IS NULL AND category = $id AND submitted >= '$lastcatvisit'";
        $result = $this->db->getRow($query);

        $count['files'] = $result['filecount'];

        //check if any new links have been submitted
        $query = "SELECT COUNT(*) AS linkcount FROM link WHERE category = $id
			AND submitted >= '$lastcatvisit'";
        $result = $this->db->getRow($query);

        $count['links'] = $result['linkcount'];

        //check if any new forum posts have been made
        $query = "SELECT COUNT(*) AS forumcount
			FROM forumThread INNER JOIN forumPost ON forumThread.threadID = forumPost.threadID
			WHERE forumThread.categoryID = $id AND forumThread.deleted != true AND forumPost.deleted != true AND forumPost.date >= '$lastcatvisit'";
        $result = $this->db->getRow($query);

        $count['forum'] = $result['forumcount'];

        //check if any new wiki revisions have been made
        $query = "SELECT COUNT(*) AS wikicount
			FROM wiki INNER JOIN wikiRevision ON wiki.wikiID = wikiRevision.wikiID
			WHERE wiki.categoryID = $id AND wiki.deleted != true AND wikiRevision.created >= '$lastcatvisit'";
        $result = $this->db->getRow($query);

        $count['wiki'] = $result['wikicount'];

        return $count;
    }

    /**
     * Get a count of how many new online assessments have been created after
     * the last time the user visited the homepage.
     *
     * @param <type> $category_id
     * @return <type>
     */
    function newOnlineAssessments($category_id) {
        $lastvisit = $this->lastDueWorkVisit();

        $query = "SELECT COUNT(assess_id) AS onlineAssessments
                  FROM assess
                  WHERE category_id = '$category_id'
                  AND created_at > '$lastvisit'
                  AND user_id != '" . $_SESSION['id'] . "'
                  AND deleted_at IS NULL";

        $result = $this->db->getRow($query);

        return $result['onlineAssessments'];
    }

    /**
     * Find out which assessments have been reviewed since you last checked.
     *
     * @param <type> $category_id
     * @return <type>
     */
    function newReviewedOnlineAssessments($category_id) {
        $lastvisit = $this->lastDueWorkVisit();

        $query = "SELECT COUNT(attempt_id) AS reviewedOnlineAssessments
                  FROM assess
                  LEFT JOIN assess_attempt ON assess.assess_id = assess_attempt.assess_id
                  WHERE assess.category_id = '$category_id'
                  AND assess_attempt.reviewed_at IS NOT NULL
                  AND assess_attempt.reviewed_at > '$lastvisit'
                  AND assess_attempt.user_id = '" . $_SESSION['id'] . "'";

        $result = $this->db->getRow($query);

        return $result['reviewedOnlineAssessments'];
    }

    function newDueWork($id) {
        $lastvisit = $this->lastDueWorkVisit();

        //check for due work (* currently does not check if work has been returned *)
        $query = "SELECT COUNT(id) AS duework FROM submission_box WHERE category = '$id' AND created > '$lastvisit' AND owner != '" . $_SESSION['id'] . "' AND deleted = 0 AND task_id IS NULL";
        $result = $this->db->getRow($query);

        return $result['duework'];
    }

    function newReturnedWork($id) {
        $lastvisit = $this->lastReturnedWorkVisit();

        //check for returned work
        $query = "SELECT COUNT(submission_return.id) AS retwork FROM submission_return, submission_box WHERE
			submission_return.box_id = submission_box.id AND category = '$id' 
			AND submission_return.owner = '" . $_SESSION['id'] . "' AND returned > '$lastvisit' AND task_id IS NULL";
        $result = $this->db->getRow($query);

        return $result['retwork'];
    }

    function countReturnedWork() {
        //check for returned work
        $query = "SELECT COUNT(submission_return.id) AS retwork FROM submission_return WHERE
				owner = '" . $_SESSION['id'] . "' AND deleted = '0'";

        $result = $this->db->getRow($query);

        return $result['retwork'];
    }


    function newSubmission($category) {
        $query = "
            SELECT
                submission_box.name AS description, submission_box.id, count(*) AS `count`
            FROM
                submission_box
            LEFT JOIN submission_log ON
                submission_log.page = '3'
                AND submission_box.id = submission_log.sid
                AND submission_log.user = {$this->db->smartQuote($_SESSION['id'])}
            INNER JOIN student_submission ON
                submission_box.id = student_submission.box_id
            WHERE
                submission_box.owner = {$this->db->smartQuote($_SESSION['id'])}
                AND submission_box.deleted = '0'
                AND submission_box.category = {$this->db->smartQuote($category)}
                AND (submission_log.datetime IS NULL OR student_submission.submitted > submission_log.datetime)
                AND submission_box.task_id IS NULL
            GROUP BY
                submission_box.name, submission_box.id
        ";
        $result = array();
        $rh = $this->db->query($query);
        while ($subbox = $this->db->getNextArray($rh)) {
            $result[$subbox['id']] = $subbox;
        }
        return $result;
    }

}

<?php
/**
 * AssessAttempt class. Assessment Attempts are managed through this class.
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   2010 Alaress Pty Ltd
 * @link        http://www.alaress.com.au
 * @version     SVN: $Id$
 */

abstract class EvaluationAttempt extends DeprecatedModel {
    public $_table = 'assess_attempt';
    public $_index = 'attempt_id';

    /**
     * Mark an attempt as having been reviewed.
     *
     * @param <type> $attempt_id
     */
    public function reviewed($attempt_id) {
        $data = array(
            'attempt_id' => $attempt_id,
            'reviewed_at' => 'NOW()'
        );

        parent::update($data);
    }

    /**
     * The number of attempts that have been reviewed for an assess
     *
     * @param <type> $assess_id
     * @return <type>
     */
    public function numReviewed($assess_id) {
        $query = "SELECT COUNT(*)
                  FROM {$this->_table}
                  WHERE assess_id = '$assess_id'
                  AND reviewed_at IS NOT NULL";

        return $this->db->getOne($query);
    }

    /**
     * The number of attempts made for an assess. Optionally group them by
     * user id to cater for multiple attempts.
     *
     * @param <type> $assess_id
     * @return <type>
     */
    public function numAttempted($assess_id, $groupById = false) {
        $query = "SELECT COUNT(*)
                  FROM {$this->_table}
                  WHERE assess_id = '$assess_id'";

        if ($groupById) {
            $query .= " GROUP BY user_id";
        }

        return $this->db->getOne($query);
    }

    /**
     * Creates an attempt and marks the start time
     *
     * @param integer $user_id
     * @return mixed
     */
    public function start($user_id, $assess_id) {
        if (!is_numeric($user_id) || !is_numeric($assess_id)) {
            return false;
        }

        $attempt_id = parent::create(array(
            'assess_id' => $assess_id,
            'user_id' => $user_id,
            'start' => date('Y-m-d H:i:s')
        ));

        if (is_numeric($attempt_id)) {
            return $attempt_id;
        }
        else {
            return false;
        }
    }

    /**
     * Marks an attempt as being finished
     *
     * @param integer $attempt_id
     * @return mixed
     */
    public function finish($attempt_id) {
        if(!is_numeric($attempt_id)) {
            return false;
        }

        $attempt_id = $this->update(array(
            'assess_id' => $attempt_id,
            'finish' => date('Y-m-d H:i:s')
        ));

        if (is_numeric($attempt_id)) {
            return $attempt_id;
        }
        else {
            return false;
        }
    }

    /**
     * Check if the current user has already completed this assessment.
     *
     * @param <type> $assess_id
     */
    public function isComplete($assess_id) {
        $query = "SELECT COUNT(*) AS completed
                  FROM {$this->_table}
                  WHERE assess_id = '$assess_id'
                  AND user_id = '$_SESSION[id]'
                  AND finish != '0000-00-00 00:00:00'
				  AND deleted_at IS NULL";

        return $this->db->getOne($query);
    }

	/**
	 * Get a list of attempts that have been made, along with their authors
	 *
	 * @param  $assess_id
	 * @return array|bool
	 */
    public function getList($assess_id, $user_id = false) {
        $query = "SELECT
                      assess_attempt.attempt_id,
                      assess_attempt.user_id, firstname, lastname,
                      assess_attempt.start, assess_attempt.finish,
                      (UNIX_TIMESTAMP(assess_attempt.finish) - UNIX_TIMESTAMP(assess_attempt.start)) AS timeTaken,
                      SUM(assess_response.mark) AS totalMarks,
                      assess_attempt.reviewed_at
                  FROM assess_attempt
                  LEFT JOIN `user` ON assess_attempt.user_id = user.id
                  LEFT JOIN assess ON assess_attempt.assess_id = assess.assess_id
                  LEFT JOIN assess_response ON assess_attempt.attempt_id = assess_response.attempt_id
                  WHERE assess.assess_id = '$assess_id'
				  AND assess_attempt.deleted_at IS NULL ";

	    if (is_numeric($user_id)) {
            $query .= "AND assess_attempt.user_id = '$user_id' ";
        }

        $query .= "GROUP BY assess_attempt.attempt_id";

        return $this->db->getAll($query);
    }

	/**
	 * Get a list of attempts that have been made for a particular user
	 *
	 * @param  $assess_id
	 * @return array|bool
	 */
    public function getListForUser($assess_id, $user_id) {
        $query = "SELECT
                      assess_attempt.attempt_id,
                      assess_attempt.user_id, firstname, lastname,
                      assess_attempt.start, assess_attempt.finish,
                      (UNIX_TIMESTAMP(assess_attempt.finish) - UNIX_TIMESTAMP(assess_attempt.start)) AS timeTaken,
                      SUM(assess_response.mark) AS totalMarks,
                      assess_attempt.reviewed_at
                  FROM assess_attempt
                  LEFT JOIN `user` ON assess_attempt.user_id = user.id
                  LEFT JOIN assess ON assess_attempt.assess_id = assess.assess_id
                  LEFT JOIN assess_response ON assess_attempt.attempt_id = assess_response.attempt_id
                  WHERE assess.assess_id = '$assess_id'
				  AND assess_attempt.deleted_at IS NULL
				  AND user.id = '$user_id'
                  GROUP BY assess_attempt.attempt_id";

        return $this->db->getAll($query);
    }

	/**
	 * Get a list of attempts that have been made, along with their authors
	 *
	 * @param  $assess_id
	 * @return array|bool
	 */
    public function getListByAssessAndAttempt($assess_id, $attempt_id) {
        $query = "SELECT
                      assess_attempt.attempt_id,
                      assess_attempt.user_id, firstname, lastname,
                      assess_attempt.start, assess_attempt.finish,
                      (UNIX_TIMESTAMP(assess_attempt.finish) - UNIX_TIMESTAMP(assess_attempt.start)) AS timeTaken,
                      SUM(assess_response.mark) AS totalMarks,
                      assess_attempt.reviewed_at
                  FROM assess_attempt
                  LEFT JOIN `user` ON assess_attempt.user_id = user.id
                  LEFT JOIN assess ON assess_attempt.assess_id = assess.assess_id
                  LEFT JOIN assess_response ON assess_attempt.attempt_id = assess_response.attempt_id
                  WHERE assess.assess_id = '$assess_id'
                  AND assess_attempt.attempt_id = '$attempt_id'
				  AND assess_attempt.deleted_at IS NULL
                  GROUP BY assess_attempt.attempt_id";

        return $this->db->getAll($query);
    }

	/**
	 * Get a list of attempts that have been made, along with their authors
	 *
	 * @param  $assess_id
	 * @param  $section_id
	 * @return array|bool
	 */
    public function getListByAssessAndSection($assess_id, $section_id) {
        $query = "SELECT
                      assess_attempt.attempt_id,
                      assess_attempt.user_id, firstname, lastname,
                      assess_attempt.start, assess_attempt.finish,
                      (UNIX_TIMESTAMP(assess_attempt.finish) - UNIX_TIMESTAMP(assess_attempt.start)) AS timeTaken,
                      SUM(assess_response.mark) AS totalMarks,
                      assess_attempt.reviewed_at
                  FROM assess_attempt
                  LEFT JOIN `user` ON assess_attempt.user_id = user.id
                  LEFT JOIN assess ON assess_attempt.assess_id = assess.assess_id
                  LEFT JOIN assess_response ON assess_attempt.attempt_id = assess_response.attempt_id
                  WHERE assess.assess_id = '$assess_id'
				  AND assess_response.section_id = '$section_id'
				  AND assess_attempt.deleted_at IS NULL
                  GROUP BY assess_attempt.attempt_id";

        return $this->db->getAll($query);
    }

    /**
     * Find the total marks available for an attempt (noting that an attempt
     * can have varying total available marks, depending on randomness of
     * questions which themselves have different available marks from other
     * candidates which could be selected)
     */
    public function getTotalMarks($assess_id, $attempt_id) {
        $query = "SELECT SUM(marks) AS totalMarks
                  FROM assess_attempt
                  LEFT JOIN assess_response ON assess_attempt.attempt_id = assess_response.attempt_id
                  LEFT JOIN assess_question ON assess_response.question_id = assess_question.question_id
                  WHERE assess_attempt.assess_id = '$assess_id'
                  AND assess_attempt.attempt_id = '$attempt_id'
                  AND assess_question.deleted_at IS NULL";

        return $this->db->getOne($query);
    }

    /**
     * Out of multiple attempts at the same assess, get a user's most recent one
     *
     * @param <type> $assess_id
     * @param <type> $user_id
     * @return <type>
     */
    public function getLatestForUser($assess_id, $user_id) {
        $query = "SELECT *
                  FROM {$this->_table}
                  WHERE assess_id = '$assess_id'
                  AND user_id = '$user_id'
				  AND deleted_at IS NULL
                  ORDER BY start DESC";

        return $this->db->getRow($query);
    }

	/**
	 * Get the marks given to an attempt
	 *
	 * @param  $attempt_id
	 * @return
	 */
    public function getMarksForAttempt($attempt_id) {
        $query = "SELECT
                      SUM(assess_response.mark) AS totalMarks
                  FROM assess_attempt
                  LEFT JOIN assess_response ON assess_attempt.attempt_id = assess_response.attempt_id
                  WHERE assess_attempt.attempt_id = '$attempt_id'
                  GROUP BY assess_attempt.attempt_id";

        return $this->db->getOne($query);
    }

    /**
     * Find out if the current user has already made an attempt on this test
     *
     * @param <type> $assess_id
     * @param <type> $user_id
     * @return <type>
     */
    public function userHasAttempted($assess_id, $user_id) {
        $query = "SELECT COUNT(*)
                  FROM {$this->_table}
                  WHERE assess_id = '$assess_id'
                  AND user_id = '$user_id'";

        return $this->db->getOne($query);
    }

    /**
     * Finds out if the specified user owns the assessment attempt.
     *
     * @param <type> $attempt_id
     * @param <type> $user_id
     * @return <type>
     */
    public function isOwner($attempt_id, $user_id = '') {
        if (!is_numeric($user_id)) {
            $uid = $_SESSION['id'];
        }
        else {
            $uid = $user_id;
        }

        $query = "SELECT COUNT(*)
                  FROM {$this->_table}
                  WHERE attempt_id = '$attempt_id'
                  AND user_id = '$uid'";

        return $this->db->getOne($query);
    }
}
?>

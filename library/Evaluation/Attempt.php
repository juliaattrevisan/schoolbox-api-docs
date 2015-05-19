<?php
/**
 * Evaluation_Attempt
 *
 * Assessment Attempts are managed through this class.
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   2010 Alaress Pty Ltd
 * @link        http://www.alaress.com.au
 * @version     SVN: $Id$
 */
abstract class Evaluation_Attempt extends DeprecatedModel
{
    public $_table = 'assess_attempt';
    public $_index = 'attempt_id';

    /**
     * Mark an attempt as having been reviewed.
     *
     * @param  $attempt_id
     * @return void
     */
    public function reviewed($attempt_id)
    {
        $data = array(
            'attempt_id' => $attempt_id,
            'reviewed_at' => 'NOW()'
        );

        parent::update($data);
    }

    /**
     * The number of attempts that have been reviewed for an assess
     *
     * @param $assess_id
     * @return mixed
     */
    public function numReviewed($assess_id)
    {
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
     * @param $assess_id
     * @param bool $groupById
     * @return mixed
     */
    public function numAttempted($assess_id, $groupById = false)
    {
        $query = "SELECT COUNT(*)
                  FROM {$this->_table}
                  WHERE assess_id = {$this->db->smartQuote($assess_id)}
                  AND deleted_at IS NULL";

        if ($groupById) {
            $query .= " GROUP BY user_id";
        }

        return $this->db->getOne($query);
    }

    /**
     * Creates an attempt and marks the start time
     *
     * @param  $user_id
     * @param  $assess_id
     * @return bool|int
     */
    public function start($user_id, $assess_id)
    {
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
        } else {
            return false;
        }
    }

    /**
     * Marks an attempt as being finished
     *
     * @param  $attempt_id
     * @return bool
     */
    public function finish($attempt_id)
    {
        if (!is_numeric($attempt_id)) {
            return false;
        }

        $attempt_id = $this->update(array(
            'assess_id' => $attempt_id,
            'finish' => date('Y-m-d H:i:s')
        ));

        if (is_numeric($attempt_id)) {
            return $attempt_id;
        } else {
            return false;
        }
    }

    /**
     * Check if the current user has already completed this assessment.
     *
     * @param $assess_id
     * @return mixed
     */
    public function isComplete($assess_id)
    {
        $query = "SELECT COUNT(*) AS completed
                  FROM {$this->_table}
                  WHERE assess_id = " . $this->db->smartQuote($assess_id) . "
                  AND user_id = '{$_SESSION['id']}'
                  AND finish != '0000-00-00 00:00:00'
				  AND deleted_at IS NULL";

        return $this->db->getOne($query);
    }

    /**
     * Get a list of attempts that have been made, along with their authors
     *
     * @param $assess_id
     *
     * @return array|bool
     */
    public function getList($assess_id)
    {
        $query = "SELECT
                      assess_attempt.attempt_id,
                      assess_attempt.user_id, synergy_id, firstname, lastname,
                      assess_attempt.start, assess_attempt.finish,
                      (UNIX_TIMESTAMP(assess_attempt.finish) - UNIX_TIMESTAMP(assess_attempt.start)) AS timeTaken,
                      SUM(assess_response.mark) AS totalMarks,
                      assess_attempt.reviewed_at
                  FROM assess_attempt
                  LEFT JOIN `user` ON assess_attempt.user_id = user.id
                  LEFT JOIN assess ON assess_attempt.assess_id = assess.assess_id
                  LEFT JOIN assess_response ON assess_attempt.attempt_id = assess_response.attempt_id
                  WHERE assess.assess_id = " . $this->db->smartQuote($assess_id) . "
				  AND assess_attempt.deleted_at IS NULL
				  GROUP BY assess_attempt.attempt_id";

        return $this->db->getAll($query);
    }

    /**
     * Get a list of attempts that have been made for a particular user
     *
     * @param  $assess_id
     * @param  $user_id
     * @return array|bool
     */
    public function getListForUser($assess_id, $user_id)
    {
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
                  WHERE assess.assess_id = " . $this->db->smartQuote($assess_id) . "
				  AND assess_attempt.deleted_at IS NULL
				  AND assess_attempt.user_id = '$user_id'
                  GROUP BY assess_attempt.attempt_id";

        return $this->db->getAll($query);
    }

    /**
     * Get a list of attempts that have been made, along with their authors
     *
     * @param  $assess_id
     * @param  $attempt_id
     * @return array|bool
     */
    public function getListByAssessAndAttempt($assess_id, $attempt_id)
    {
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
                  WHERE assess.assess_id = " . $this->db->smartQuote($assess_id) . "
                  AND assess_attempt.attempt_id = " . $this->db->smartQuote($attempt_id) . "
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
    public function getListByAssessAndSection($assess_id, $section_id)
    {
        $query = "SELECT
                      assess_attempt.attempt_id,
                      assess_attempt.user_id, user.synergy_id, firstname, lastname,
                      assess_attempt.start, assess_attempt.finish,
                      (UNIX_TIMESTAMP(assess_attempt.finish) - UNIX_TIMESTAMP(assess_attempt.start)) AS timeTaken,
                      SUM(assess_response.mark) AS totalMarks,
                      assess_attempt.reviewed_at
                  FROM assess_attempt
                  LEFT JOIN `user` ON assess_attempt.user_id = user.id
                  LEFT JOIN assess ON assess_attempt.assess_id = assess.assess_id
                  LEFT JOIN assess_response ON assess_attempt.attempt_id = assess_response.attempt_id
                  WHERE assess.assess_id = " . $this->db->smartQuote($assess_id) . "
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
     *
     * @param $assess_id
     * @param $attempt_id
     * @return mixed
     */
    public function getTotalMarks($assess_id, $attempt_id)
    {
        $query = "SELECT COALESCE(SUM(marks),0) AS totalMarks
                  FROM assess_attempt
                  LEFT JOIN assess_response ON assess_attempt.attempt_id = assess_response.attempt_id
                  LEFT JOIN assess_question ON assess_response.question_id = assess_question.question_id
                  WHERE assess_attempt.assess_id = " . $this->db->smartQuote($assess_id) . "
                  AND assess_attempt.attempt_id = " . $this->db->smartQuote($attempt_id) . "
                  AND assess_question.deleted_at IS NULL";

        return $this->db->getOne($query);
    }

    /**
     * Out of multiple attempts at the same assess, get a user's most recent one
     *
     * @param  $assess_id
     * @param  $user_id
     * @return array|bool
     */
    public function getLatestForUser($assess_id, $user_id)
    {
        $query = "SELECT *
                  FROM {$this->_table}
                  WHERE assess_id = " . $this->db->smartQuote($assess_id) . "
                  AND user_id = '$user_id'
				  AND deleted_at IS NULL
                  ORDER BY start DESC";

        return $this->db->getRow($query);
    }

    /**
     * Get the most recent attempt that you can resume
     *
     * @param $assess_id
     * @param $user_id
     * @return array|bool
     */
    public function getLatestResumableForUser($assess_id, $user_id)
    {
        $query = "
			SELECT {$this->_table}.*
			FROM {$this->_table}
			LEFT JOIN assess ON assess.assess_id = {$this->_table}.assess_id
			WHERE {$this->_table}.assess_id = " . $this->db->smartQuote($assess_id) . "
			AND {$this->_table}.user_id = '$user_id'
			AND {$this->_table}.deleted_at IS NULL
			AND {$this->_table}.finish IS NULL
			ORDER BY {$this->_table}.start DESC
		";

        return $this->db->getRow($query);
    }

    /**
     * Get the marks given to an attempt
     *
     * @param  $attempt_id
     * @return mixed
     */
    public function getMarksForAttempt($attempt_id)
    {
        $query = "SELECT
                      COALESCE(SUM(assess_response.mark),0) AS totalMarks
                  FROM assess_attempt
                  LEFT JOIN assess_response ON assess_attempt.attempt_id = assess_response.attempt_id
                  WHERE assess_attempt.attempt_id = " . $this->db->smartQuote($attempt_id) . "
                  GROUP BY assess_attempt.attempt_id";

		$result = $this->db->getOne($query);

		/* Null marks return 0.00 for formatting
		 */
		if ($result == null) {
			return number_format(0, 2);
		}

        return $result;
    }

    /**
     * Find out if the current user has already made an attempt on this test
     *
     * @param  $assess_id
     * @param  $user_id
     * @return mixed
     */
    public function userHasAttempted($assess_id, $user_id)
    {
        $query = "
			SELECT COUNT(*)
			FROM {$this->_table}
			WHERE assess_id = " . $this->db->smartQuote($assess_id) . "
			AND user_id = '$user_id'
			AND deleted_at IS NULL
		";

        return $this->db->getOne($query);
    }

    /**
     * Finds out if the specified user owns the assessment attempt.
     *
     * @param  $attempt_id
     * @param string $user_id
     * @return mixed
     */
    public function isOwner($attempt_id, $user_id = '')
    {
        if (!is_numeric($user_id)) {
            $uid = $_SESSION['id'];
        } else {
            $uid = $user_id;
        }

        $query = "SELECT COUNT(*)
                  FROM {$this->_table}
                  WHERE attempt_id = " . $this->db->smartQuote($attempt_id) . "
                  AND user_id = '$uid'";

        return $this->db->getOne($query);
    }

    /**
     * Get the user who did the attempt
     *
     * @param $attempt_id
     * @return mixed
     */
    public function getUser($attempt_id)
    {
        $query = "
			SELECT `user`.firstname, `user`.lastname
			FROM assess_attempt
			LEFT JOIN `user` ON assess_attempt.user_id = `user`.id
			WHERE assess_attempt.attempt_id = " . $this->db->smartQuote($attempt_id) . "
		";

        return $this->db->getRow($query);
    }

    /**
     * Gets list of al students who have had work returned for this assessment.
     *
     * @param  int $assessId
     * @return array
     */
    public function getStudentListByAssessId($assessId) {
        $studentList = array();
        $allAttempts = $this->getList($assessId);
        if (is_array($allAttempts)) {
            foreach ($allAttempts as $return) {
                $studentList[] = array(
                    'title'      => $return['title'],
                    'firstname'  => $return['firstname'],
                    'lastname'   => $return['lastname'],
                    'uid'        => $return['user_id'],
                    'synergy_id' => $return['synergy_id'],
                    'type'       => null,
                    'group_id'   => null,
                );
            }
        }
        return $studentList;
    }
}

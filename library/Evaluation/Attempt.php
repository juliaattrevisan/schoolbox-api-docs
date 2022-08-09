<?php

/**
 * Evaluation_Attempt
 *
 * Assessment Attempts are managed through this class.
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */
abstract class Evaluation_Attempt extends DeprecatedModel
{
    public $_table = 'assess_attempt';
    public $_index = 'attempt_id';

    /**
     * Mark an attempt as having been reviewed.
     *
     * @param int $attempt_id
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
     * Creates an attempt and marks the start time
     *
     * TODO: But is this even used?
     *
     * @param int $user_id
     * @param int $assess_id
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
     * Get a list of attempts that have been made, along with their authors
     *
     * @param int $assess_id
     * @return array|bool
     */
    public function getList($assess_id)
    {
        $query = "SELECT
                      assess_attempt.attempt_id,
                      assess_attempt.user_id, synergy_id, firstname, preferred_name, lastname, user.deleted_at as user_deleted_at,
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
                  AND assess_response.deleted_at IS NULL
                  GROUP BY assess_attempt.attempt_id";

        return $this->db->getAll($query);
    }


    /**
     * Get a list of attempts that have been made, along with their authors
     *
     * @param int $assess_id
     * @param int $attempt_id
     * @return array|bool
     */
    public function getListByAssessAndAttempt($assess_id, $attempt_id)
    {
        $query = "SELECT
                      assess_attempt.attempt_id,
                      assess_attempt.user_id, firstname, preferred_name, lastname,
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
                  AND assess_response.deleted_at IS NULL
                  GROUP BY assess_attempt.attempt_id";

        return $this->db->getAll($query);
    }

    /**
     * Get a list of attempts that have been made, along with their authors
     *
     * @param int $assess_id
     * @param int $section_id
     * @return array|bool
     */
    public function getListByAssessAndSection($assess_id, $section_id)
    {
        $query = "SELECT
                      assess_attempt.attempt_id,
                      assess_attempt.user_id, user.synergy_id, firstname, preferred_name, lastname,
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
                  AND assess_response.deleted_at IS NULL
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
     * @param int $assess_id
     * @param int $attempt_id
     * @return mixed
     */
    public function getTotalMarks($assess_id, $attempt_id)
    {
        // Get only marks that are actually in $assess_id.
        // (No idea how responses to questions in different assessments get
        //  into the same attempt!)
        $query = "
            SELECT COALESCE(SUM(aq.marks),0) AS totalMarks
            FROM assess_attempt aa
            LEFT JOIN assess_response ar  ON aa.attempt_id = ar.attempt_id
            LEFT JOIN assess_section  ars ON ar.section_id = ars.section_id
            LEFT JOIN assess_question aq  ON ar.question_id = aq.question_id
            LEFT JOIN assess_section  aqs ON aq.section_id = aqs.section_id
            WHERE aa.assess_id   = " . $this->db->smartQuote($assess_id) . "
            AND   aa.attempt_id  = " . $this->db->smartQuote($attempt_id) . "
            AND   ars.section_id = aqs.section_id
            AND   ars.assess_id  = aa.assess_id
            AND   aqs.assess_id  = aa.assess_id
            AND   aq.deleted_at  IS NULL
            AND   ar.deleted_at IS NULL
        ";

        return $this->db->getOne($query);
    }

    /**
     * Out of multiple attempts at the same assess, get a user's most recent one
     *
     * @param int $assess_id
     * @param int $user_id
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
     * @param int $assess_id
     * @param int $user_id
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
     * Ensure attempt is still valid and active for user
     *
     * @param int $assess_id
     * @param int $user_id
     * @param int $attempt_id
     * @return bool
     */
    public function checkAttemptIsValid($assess_id, $user_id, $attempt_id)
    {
        $query = "
            SELECT {$this->_table}.*
            FROM {$this->_table}
            WHERE {$this->_table}.assess_id = " . $this->db->smartQuote($assess_id) . "
            AND {$this->_table}.user_id = " . $this->db->smartQuote($user_id) . "
            AND {$this->_table}.attempt_id =  " . $this->db->smartQuote($attempt_id) . "
            AND {$this->_table}.deleted_at IS NULL
            AND {$this->_table}.finish IS NULL
        ";

        if ($this->db->getRow($query)) {
            return true;
        }

        return false;
    }


    /**
     * Get the marks given to an attempt
     *
     * @param int $attempt_id
     * @return string
     */
    public function getMarksForAttempt($attempt_id)
    {
        $query = "SELECT
                      COALESCE(SUM(assess_response.mark),0) AS totalMarks
                  FROM assess_attempt
                  LEFT JOIN assess_response ON assess_attempt.attempt_id = assess_response.attempt_id
                  WHERE assess_attempt.attempt_id = " . $this->db->smartQuote($attempt_id) . "
                  AND assess_response.deleted_at IS NULL
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
     * @param int $assess_id
     * @param int $user_id
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
     * @param int $attempt_id
     * @param int|string $user_id
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
     * @param int $attempt_id
     * @return mixed
     */
    public function getUser($attempt_id)
    {
        $query = "
            SELECT `user`.firstname, `user`.preferred_name, `user`.lastname
            FROM assess_attempt
            LEFT JOIN `user` ON assess_attempt.user_id = `user`.id
            WHERE assess_attempt.attempt_id = " . $this->db->smartQuote($attempt_id) . "
        ";

        return $this->db->getRow($query);
    }
}

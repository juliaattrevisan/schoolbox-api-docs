<?php

class SubmissionStudent extends DeprecatedModel
{

    /**
     * SubmissionStudent constructor.
     * @deprecated
     */
    function __construct()
    {

        $this->_table = "student_submission";
        $this->_index = "id";

        parent::__construct();
    }

    /**
     * @deprecated
     *
     * @param $id
     * @return array|bool|null
     */
    function getByID($id)
    {
        $query = "SELECT s.*, u.lastname, u.firstname FROM `" . $this->_table . "` s, user u
                  WHERE s.owner = u.id AND s." . $this->_index . " = $id";
        return $this->db->getRow($query);
    }

    /**
     * Gets list of al students who have submitted to this submission box ID.
     * @deprecated
     *
     * @param  int $boxId
     * @return array
     */
    public function getStudentListByBoxId($boxId)
    {
        $studentList    = array();
        $allSubmissions = $this->getByCondition($boxId, array());
        if (is_array($allSubmissions) && count($allSubmissions)) {
            foreach ($allSubmissions as $submission) {
                $studentList[] = array(
                    'title'      => $submission['title'],
                    'firstname'  => $submission['firstname'],
                    'lastname'   => $submission['lastname'],
                    'uid'        => $submission['uid'],
                    'synergy_id' => $submission['synergy_id'],
                    'type'       => $submission['type'],
                    'group_id'   => $submission['group_id'],
                );
            }
        }
        return $studentList;
    }

    /**
     * @deprecated
     *
     * @param       $subBoxID
     * @param array $conditions
     * @return array|bool
     */
    protected function getByCondition($subBoxID, array $conditions)
    {

        $where = array();
        if (count($conditions)) {
            foreach ($conditions as $k => $v) {
                $where[] = "$k = '$v'";
            }
            $conditional = implode(' AND ', $where);
        } else {
            $conditional = '1=1';
        }

        $query = "SELECT student_submission.id, user.title, user.firstname, user.lastname, user.id AS uid, username,
                         local_email, alt_email, DATE_FORMAT(submitted, '%a, %D of %b %l:%i%p') AS date,
                         DATE_FORMAT(submitted, '%Y-%m-%d %H:%i:%s') as datestamp, realname, late, marked,
                         (student_submission.realname = 'online-submission') AS is_online_submission,
                         user.synergy_id as synergy_id, pd.score, pd.status plag_status, pd.plagscan_id plag_doc_id,
                         pd.deleted plag_deleted
                  FROM (student_submission, user)
                  LEFT JOIN plagscan_document pd ON student_submission.id = pd.file_id
                  WHERE $conditional
                  AND owner = user.id
                  AND box_id = '$subBoxID'
                  AND user.deleted_at IS NULL
                  ORDER BY lastname, firstname, datestamp DESC ";
        return $this->db->getAll($query);

    }

    function getStatus($user_id, $submission_box_id)
    {
        $query = "SELECT sb.open_date, sb.date, sb.late_date, sb.created, ss.submitted, sr.returned, sr.mark
                  FROM submission_box sb
                  INNER JOIN assessment a ON sb.assessment_id = a.id
                  LEFT JOIN submission_return sr ON sb.id = sr.box_id AND sr.owner = $user_id
                  LEFT JOIN student_submission ss ON sb.id = ss.box_id AND ss.owner = $user_id AND ss.submitted_at IS NOT NULL
                  WHERE sb.id = $submission_box_id
                  AND a.deleted_at IS NULL
                  AND sb.task_id IS NULL";

        $submission = $this->db->getRow($query);

        /* Convert date fields and set status */
        $date_fields = array('open_date', 'date', 'created', 'late_date', 'submitted', 'returned');

        foreach ($date_fields as $field_name) {
            if (!empty($submission[$field_name])) {
                $submission[$field_name] = new DateTime($submission[$field_name]);
            }
        }

        if (!empty($submission['returned']) && empty($submission['submitted'])) {
            $submission['status']        = 1;
            $submission['relevant_date'] = $submission['date'];
        } elseif (!empty($submission['returned'])) {
            $submission['status']        = 3;
            $submission['relevant_date'] = $submission['returned'];
        } elseif (!empty($submission['submitted'])) {
            $submission['status']        = 2;
            $submission['relevant_date'] = $submission['submitted'];
        } else {
            $submission['status']        = 1;
            $submission['relevant_date'] = $submission['date'];
        }

        return $submission;
    }

}

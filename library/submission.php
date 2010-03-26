<?php

require_once $_SERVER['DOCUMENT_ROOT'] . '/../include/model.php';

class Submission extends Model {

    function Submission () {

        $this->_table = "submission_box";
        $this->_index = "id";

        parent::Model();
    }

    function getByID($boxID) {
        $query = "SELECT submission_box.*, category.code, category.name as categoryName
                  FROM submission_box JOIN category ON submission_box.category = category.id
                  WHERE submission_box.id = '$boxID'";
        return $this->db->getRow($query);
    }

    function getAllForCategory($categoryID, $config = false) {
            $query = "SELECT sb.id, sb.category, ft.name as typeName, sb.owner,
                              UNIX_TIMESTAMP(sb.open_date) AS open,
                              UNIX_TIMESTAMP(sb.date) AS date,
                              UNIX_TIMESTAMP(sb.late_date) AS late,
                              f.id fileID, f.realname, sb.description, sb.created, ss.id studentWork, af.inline
                              FROM submission_box sb
                              LEFT JOIN  file f ON sb.fid = f.id AND f.deleted = '0'
                              LEFT JOIN allowedFileTypes af ON f.mime_type = mime
                              LEFT JOIN student_submission ss ON ss.box_id = sb.id AND ss.owner = '" . $_SESSION['id'] . "'
                              LEFT JOIN file_type ft ON sb.type = ft.id

                              WHERE sb.category = '".$categoryID."' AND sb.deleted = '0'
                              ORDER BY sb.date DESC";

            if($config['size'] > 0)
                    $query .= " LIMIT 0, " . $config['size'];

            return $this->db->getAll($query);
    }

    function getHomework($categoryID, $config = false, $type = 3) {

            $query = "SELECT sb.id, sb.category, ft.name as typeName, sb.owner,
                              UNIX_TIMESTAMP(sb.open_date) AS open,
                              UNIX_TIMESTAMP(sb.date) AS date,
                              UNIX_TIMESTAMP(sb.late_date) AS late,
                              f.id fileID, f.realname, sb.description, sb.created, ss.id studentWork, af.inline
                              FROM submission_box sb
                              LEFT JOIN  file f ON sb.fid = f.id AND f.deleted = '0'
                              LEFT JOIN allowedFileTypes af ON f.mime_type = mime
                              LEFT JOIN student_submission ss ON ss.box_id = sb.id AND ss.owner = '" . $_SESSION['id'] . "'
                              LEFT JOIN file_type ft ON sb.type = ft.id
                              WHERE sb.category = '".$categoryID."' AND sb.deleted = '0' AND sb.type = '".$type."' AND sb.late_date > NOW()
                              ORDER BY sb.date DESC";

            if($config['size'] > 0)
                    $query .= " LIMIT 0, " . $config['size'];

            return $this->db->getAll($query);
    }

    function getAllForUser($userID) {

        $query = "SELECT submission_box.id, submission_box.description, category.id as catid, GROUP_CONCAT(co.code) code, category.name,
                        DATE_FORMAT(submission_box.date, '%a, %D %b %l:%i%p') AS date, DATE_FORMAT(submission_box.open_date, '%a, %D %b %l:%i%p') AS open_date,
                        submission_box.type, file_type.name as type
                    FROM (category, submission_box )
                    LEFT JOIN file_type ON submission_box.type = file_type.id
                    LEFT JOIN categoryCode co ON co.categoryID = category.id
                    WHERE category.id = submission_box.category
                            AND submission_box.owner = '".$userID."'
                            AND submission_box.deleted = '0'
                    GROUP BY submission_box.id, category.id
                    ORDER BY category.id, submission_box.date";

        return $this->db->getAll($query);
    }

    function getBoxCount($box_id) {
         $query = "SELECT COUNT(*) AS total, SUM(marked) AS marked FROM student_submission WHERE box_id = '".$box_id."'";
         return $this->db->getRow($query);
    }

    function getTypes(){
        $query = "SELECT * FROM file_type";
        return $this->db->getAll($query);
        
    }

    function logPageView($box_id)
    {
            /* TODO: rewrite this code in simpler method probably use mysql insert/update */
            $testquery = "SELECT datetime FROM submission_log WHERE page = '3'
            AND user = '".$_SESSION['id']."' AND sid = '$box_id'";

            $this->db->query($testquery);

            if($row = $this->db->getNextArray() )
            {
                    $query = "UPDATE submission_log SET datetime = NOW() WHERE page =
                    '3' AND user = '".$_SESSION['id']."' AND sid = '$box_id'";
            }
            else
            {
                    $query = "INSERT INTO submission_log (datetime, user, page, sid) VALUES
                            (NOW(), '".$_SESSION['id']."', '3', '$box_id')";
            }

            $this->db->query($query);
    }

    function getAssignments($categoryID, $config = false) {
            return $this->getHomework($categoryID, $config, 2);
    }

    function delete($id, $clean = false) {
            return $this->update(array('id'=>$id,'deleted'=>1));
    }

    function homepageClean($cid) {
            return $this->db->update($this->_table, 'category', array('category'=>$cid, 'deleted'=>1));
    }



}
?>
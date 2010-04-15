<?php

require_once $_SERVER['DOCUMENT_ROOT'] . '/../include/model.php';

class Submission extends Model {

    function Submission () {

        $this->_table = "submission_box";
        $this->_index = "id";

        parent::Model();
    }

    function getByID($boxID) {
        $query = "SELECT submission_box.*, GROUP_CONCAT(cc.code) code, category.name as categoryName, category.id as cid
                  FROM submission_box 
				  JOIN category ON submission_box.category = category.id
				  LEFT JOIN categoryCode cc ON cc.categoryID = category.id
                  WHERE submission_box.id = '$boxID'
				  GROUP BY submission_box.id, category.id";
        return $this->db->getRow($query);
    }

	function getByDate($date, $userID, $categoryList) {
		
		$query = "SELECT s.*, t.realname, c.name categoryName, GROUP_CONCAT(cc.code) categoryCode FROM ( submission_box s, category c )
				  LEFT JOIN student_submission t ON t.box_id = s.id AND t.owner = $userID
				  LEFT JOIN categoryCode cc ON cc.categoryID = c.id
				  WHERE s.deleted = 0 AND s.category = c.id AND DATE(s.date) = DATE(FROM_UNIXTIME({$date})) ";
		if (is_array($categoryList)) {
			$query .= "AND ( 1=2 ";
			foreach ($categoryList as $category) {
				$query .= "OR s.category = " . $category['id'] . " ";
			}
			$query .= ") ";
		}
		
	  	$query .= "GROUP BY s.id ORDER BY open_date";
		
		return $this->db->getAll($query);
	}
	
	function getUpcoming($userID, $categoryList) {
		$query = "SELECT s.*, UNIX_TIMESTAMP(s.date) date, ft.name as type, file.realname, file.id file_id, a.inline, c.name categoryName
				  FROM ( submission_box s, category c )
				  LEFT JOIN student_submission t ON t.box_id = s.id AND t.owner = '$userID'
				  LEFT JOIN file ON s.fid = file.id
				  LEFT JOIN allowedFileTypes a ON file.mime_type = a.mime
			 	  LEFT JOIN file_type ft ON s.type = ft.id
				  WHERE s.category = c.id AND s.deleted = 0 AND s.late_date > NOW() AND s.owner != '$userID' AND t.id IS NULL AND ( 1=2 ";
				  
		if (is_array($categoryList)) {
			foreach ($categoryList as $category) {
				$query .= "OR s.category = " . $category['id'] . " ";
			}
		}
		
	  	$query .= ") ORDER BY s.date";
		
		return $this->db->getAll($query);		
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
	
	function getActivityLog($userID, $limit = 5) {
		$query = " ( SELECT open_date activity_date, f.name typeName, s.description, s.category, c.name categoryName, s.fid, 'new' FROM submission_box s, category c, file_type f  
					  WHERE s.category = c.id AND f.id = s.type AND open_date < NOW() )
					UNION 
				    ( SELECT `date`, l.name typeName, u.description, u.category, e.name categoryName, u.fid, 'overdue' FROM ( submission_box u, category e, file_type l ) 
					  LEFT JOIN student_submission d ON d.box_id = u.id AND d.owner = $userID  
					  WHERE d.id IS NOT NULL AND u.category = e.id AND l.id = u.type AND `date` < NOW() )
					UNION 
					( SELECT `late_date`, i.name typeName, t.description, t.category, a.name categoryName, t.fid, 'closed' FROM submission_box t, category a, file_type i 
					  WHERE t.category = a.id AND i.id = t.type AND `late_date` < NOW() )
					UNION
					( SELECT g.`submitted` activity_date, g.realname typeName, m.description, m.category, n.name categoryName, m.fid, 'submitted'  FROM  student_submission g, submission_box m, category n
					  WHERE g.box_id = m.id AND m.category = n.id AND g.owner = $userID )
					UNION
				   ( SELECT v.`returned` activity_date, v.mark typeName, w.description, w.category, x.name categoryName, v.id fid, 'returned'  FROM  submission_return v, submission_box w, category x
					  WHERE v.box_id = w.id AND w.category = x.id AND v.owner = $userID )
					ORDER BY activity_date DESC LIMIT $limit";

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
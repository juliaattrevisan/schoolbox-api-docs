<?php

require_once '../include/model.php';

class Submission extends Model {

	function Submission () {
	
		$this->_table = "submission_box";
		$this->_index = "id";
		
		parent::Model();
	}
	
	function getAllForCategory($categoryID, $config = false) {
		$query = "SELECT sb.id, sb.category, ft.name as typeName, ss.owner,
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

	function getAssignments($categoryID, $config = false) {
		return $this->getHomework($categoryID, $config, 2);	
	}
	
}
?>
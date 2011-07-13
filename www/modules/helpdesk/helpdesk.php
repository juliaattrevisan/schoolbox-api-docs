<?php

class Helpdesk {

	function Helpdesk() {
		$this->db = new Database();
	}

	function openHelpdeskJobs($userid) {
			$query = "
		SELECT  j.jobID, 
				j.portalAssignedUserID,
				concat(assignedUser.firstname, ' ', assignedUser.lastname) as assignedUserName,
				p.description as problem,
				j.description,
				DATE_FORMAT(j.createdDate, '%e/%c/%Y') as createdDate,
				s.description as status
		FROM    ".HELPDESK_db.".tblJob j
		INNER JOIN
				".HELPDESK_db.".tblProblem p on j.problemID = p.problemID
		INNER JOIN
				".HELPDESK_db.".tblStatus s on j.statusID = s.statusID
		LEFT JOIN
				".HELPDESK_db.".tblEquipment e on j.equipmentID = e.equipmentID
		LEFT JOIN
				".SQL_db.".user assignedUser on assignedUser.id = j.portalAssignedUserID
		WHERE s.description != 'Closed' AND (j.portalAssociatedUserID = {$userid} OR e.portalUserID ={$userid} ) 
		ORDER BY j.createdDate DESC";

		$jobs = $this->db->getAll($query);

		return $jobs;
	}
}

?>
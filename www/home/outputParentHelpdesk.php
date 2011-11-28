<?php

if(HELPDESK_enable) {

	require_once('modules/helpdesk/helpdesk.php');
	$helpdesk = new Helpdesk;

	$userHandler = new DeprecatedUser();

	$this->setBlock($component['name'], "student_helpdesk_block", "student_helpdesk");
	$this->setBlock("student_helpdesk_block", "student_job_block", "student_job");
	if(is_array($_SESSION['children'])) {
		foreach($_SESSION['children'] as $child)
		{
			$query = "SELECT title, firstname, lastname, year FROM ".SQL_db.".user WHERE id = {$child['id']}";
			$user = $this->db->getRow($query);
			$this->setVar("STUDENT_ID", $child['id']);
			$this->setVar("STUDENT_NAME", $userHandler->formatName($user,'{firstname} {lastname}'));
			$this->setVar("STUDENT_YEAR", $user['year']);

			$jobs = $helpdesk->openHelpdeskJobs($child['id']);
			if(is_array($jobs)){
				foreach ($jobs as $job) {
					$this->setVar("PROBLEM", ucfirst($job['problem']));
					$this->setVar("CREATED", $job['createdDate']);
					$this->setVar("STATUS", $job['status']);
					$this->parse("student_job", "student_job_block", true);
				}
			}

			$this->parse("student_helpdesk", "student_helpdesk_block", true);
			$this->setVar("student_job", '');
		}
		$this->parse('POS'.$component['position'], $component['name']);
	}
	else
		$this->setVar('POS'.$component['position'], '');
}
else
	$this->setVar('POS'.$component['position'], '');
?>
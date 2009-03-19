<?php
if(HELPDESK_enable) {
	
	require_once('modules/helpdesk/helpdesk.php');
	
	$helpdesk = new Helpdesk;
	
	$this->setBlock($component['name'], "helpdesk_block", "helpdesk_jobs");
	
	$jobs = $helpdesk->openHelpdeskJobs($_SESSION['id']);
	if(is_array($jobs)){
		foreach ($jobs as $job) {
			$this->setVar("PROBLEM", ucfirst($job['problem']));
			$this->setVar("CREATED", $job['createdDate']);
			$this->setVar("STATUS", $job['status']);
			$this->setVar('ON_OFF', ++$j%2? '' : 'dark');
			$this->parse("helpdesk_jobs", "helpdesk_block", true);
		}
	
		$this->parse('POS'.$component['position'], $component['name']);
	}
	else
		$this->setVar('POS'.$component['position'], '');
}
else
	$this->setVar('POS'.$component['position'], '');

?>

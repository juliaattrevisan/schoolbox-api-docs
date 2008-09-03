<?php

require_once 'include/helpDeskPage.php';
require_once 'include/common.php';
require_once 'modules/moduleJobList.php';
require_once 'include/dbManager-mysql.php';



$page = new HelpDeskPage;
$page->_tpl->set_file( 'tplWhole', "statusNotebook.html");
$page->_tpl->set_block('tplWhole', 'ready', 'ready_block');
$page->_tpl->set_block('tplWhole', 'dealer', 'dealer_block');

$db = new dbManager;

$query = "SELECT b.description brand, m.description model, UNIX_TIMESTAMP(e.createdDate) date, 
		  CONCAT(equipUser.firstname, ' ', equipUser.lastname) as equipUserName,
		  CONCAT(jobUser.firstname, ' ', jobUser.lastname) as jobUserName
		  FROM tblJobEvent e
		  LEFT JOIN tblJob j ON e.jobID = j.jobID 
		  LEFT JOIN tblEquipment q ON q.equipmentID = j.equipmentID
		  LEFT JOIN ".DB_NAME_PORTAL.".user equipUser on equipUser.id = q.portalUserID 
		  LEFT JOIN ".DB_NAME_PORTAL.".user jobUser on jobUser.id = j.portalAssociatedUserID 
		  LEFT JOIN tblModel m ON m.modelID = q.modelID
		  LEFT JOIN tblBrand b ON b.brandID = m.brandID
		  WHERE j.latestEvent = 16 AND j.latestEvent = e.eventTypeID
		  GROUP BY j.jobID
		  ORDER BY e.createdDate DESC";
$jobList = $db->dbGetAllRows($query);

if (is_array($jobList)) {
	foreach ($jobList as $job) {
		$name = !empty($job['equipUserName']) ? $job['equipUserName'] : $job['jobUserName'];
		$name = !empty($name) ? $name : 'Unknown';
			
		$output = array('DATE'			=> date("d-M-Y", $job['date']),
						'USER'			=> $name,
						'BRAND'			=> $job['brand'],
						'MODEL'			=> $job['model'],
						);
		$page->_tpl->set_var($output);
		$page->_tpl->parse('ready_block', 'ready', true);
	}
} else
	$page->_tpl->set_var('ready_block', '');

// Dealer


$query = "SELECT b.description brand, m.description model, UNIX_TIMESTAMP(e.createdDate) date, 
		  CONCAT(equipUser.firstname, ' ', equipUser.lastname) as equipUserName,
		  CONCAT(jobUser.firstname, ' ', jobUser.lastname) as jobUserName
		  FROM tblJobEvent e
		  LEFT JOIN tblJob j ON e.jobID = j.jobID 
		  LEFT JOIN tblEquipment q ON q.equipmentID = j.equipmentID
		  LEFT JOIN ".DB_NAME_PORTAL.".user equipUser on equipUser.id = q.portalUserID 
		  LEFT JOIN ".DB_NAME_PORTAL.".user jobUser on jobUser.id = j.portalAssociatedUserID 
		  LEFT JOIN tblModel m ON m.modelID = q.modelID
		  LEFT JOIN tblBrand b ON b.brandID = m.brandID
		  WHERE j.latestEvent = 15 AND j.latestEvent = e.eventTypeID
		  GROUP BY j.jobID
		  ORDER BY e.createdDate DESC";

$jobList = $db->dbGetAllRows($query);

if (is_array($jobList)) {
	foreach ($jobList as $job) {
		$name = !empty($job['equipUserName']) ? $job['equipUserName'] : $job['jobUserName'];
		$name = !empty($name) ? $name : 'Unknown';
		
		$output = array('DATE'			=> date("d-M-Y", $job['date']),
						'USER'			=> $name,
						'BRAND'			=> $job['brand'],
						'MODEL'			=> $job['model'],
						);
		$page->_tpl->set_var($output);
		$page->_tpl->parse('dealer_block', 'dealer', true);
	}
} else
	$page->_tpl->set_var('dealer_block', '');

$page->printPage();

?>
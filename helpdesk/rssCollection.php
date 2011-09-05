<?php

require_once 'include/helpDeskPage.php';
require_once 'include/common.php';
require_once 'modules/moduleJobList.php';
require_once 'include/dbManager-mysql.php';

$page = new HelpDeskPage;
$page->_tpl->set_file( 'tplWhole', "rss.html");
$page->_tpl->set_block('tplWhole', 'rss_item', 'rss_item_block');


$output = array('TITLE'			=> 'Notebook Status - Ready for Collection',
				'DESCRIPTION'	=> 'User equipment ready to be collected.',
				'DATE'			=> date('r'),
				'LINK'			=> 'http://' . $_SERVER['HTTP_HOST'] . '/statusNotebook.php',
				);
$page->_tpl->set_var($output);

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
		  ORDER BY equipUser.lastname, jobUser.lastname, equipUser.firstname, jobUser.firstname DESC";
$jobList = $db->dbGetAllRows($query);

if (is_array($jobList)) {
	foreach ($jobList as $job) {
		$name = !empty($job['equipUserName']) ? $job['equipUserName'] : $job['jobUserName'];
		$name = !empty($name) ? $name : 'Unknown';

		$output = array('ITEM_TITLE'		=> Scrub::text($name),
						'ITEM_DESCRIPTION'	=> $job['brand'] . $job['model'],
						'ITEM_DATE'			=> date('r', $job['date']),
						'ITEM_LINK'			=> 'http://' . $_SERVER['HTTP_HOST'] . '/statusNotebook.php',
						);
		$page->_tpl->set_var($output);
		$page->_tpl->parse('rss_item_block', 'rss_item', true);
	}
} else
	$page->_tpl->set_var('rss_item_block', '');

header('Content-type: application/rss+xml');
$page->printPage();

?>
<?php

require_once('../include/include.php');

$db = new dbManager;
$query = "SELECT h.cid, h.config, l.url
		  FROM homepage_comp_location h
		  LEFT JOIN link l on l.category = h.cid
		  WHERE component = 15";

$catList = $db->dbGetAllRows($query);
foreach ($catList as $cat) {
	if (empty($cat['url'])) continue;
	$config  = unserialize($cat['config']);
	$config['url'] = $cat['url'];
	$config = serialize($config);
	$query = "UPDATE homepage_comp_location SET config = ".$db->dbSmartQuote($config)." WHERE cid = ".$cat['cid']." AND component = 15";
	
	$db->dbDoQuery($query);
	print $query . "<br />\n";
}


?>
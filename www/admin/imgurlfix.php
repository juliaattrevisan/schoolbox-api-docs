<?php

require_once('../include/include.php');

$db = new DeprecatedDatabase;
$query = "SELECT h.cid, h.config, l.url
		  FROM homepage_comp_location h
		  LEFT JOIN link l on l.category = h.cid AND l.description = 'Intranet Site'
		  WHERE component = 15";

$catList = $db->getAll($query);
foreach ($catList as $cat) {
	if (empty($cat['url'])) continue;
	$config  = unserialize($cat['config']);
	$config['url'] = $cat['url'];
	$config = serialize($config);
	$query = "UPDATE homepage_comp_location SET config = ".$db->dbSmartQuote($config)." WHERE cid = ".$cat['cid']." AND component = 15";
	
	$db->query($query);
	print $query . "<br />\n";
}
print '<br />done';

?>
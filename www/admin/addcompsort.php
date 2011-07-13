<?php
// Adds sort and sortdir to file and link configs

define('FILE_COMPONENT_ID', 3);
define('LINK_COMPONENT_ID', 2);


require_once '../include/include.php';

$db = new Database;

$query = "SELECT * FROM homepage_comp_location WHERE component = ". FILE_COMPONENT_ID ." OR component = " . LINK_COMPONENT_ID;
$componentList = $db->getAll($query);

if (is_array($componentList)) {
	foreach ($componentList as $component) {
		$config = unserialize($component['config']);
		$config['sort'] = 'Date';
		$config['sortdir'] = 'Desc';
		$config = serialize($config);
		
		$query = "UPDATE homepage_comp_location SET config = " . $db->dbSmartQuote($config) . " 
				  WHERE cid = " . $component['cid'] . "
				  AND component = " . $component['component'];
		
		print $query . "\n";
		$db->query($query);
	}
}

$config['size'] = 0;
$config['sort'] = 'Date';
$config['sortdir'] = 'Desc';
print serialize($config);

?>
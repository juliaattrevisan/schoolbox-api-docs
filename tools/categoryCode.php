<?php
/*
 * This script will copy all codes from the category table and insert them into the new categoryCode table
 **/

require_once dirname(__FILE__) . '/../www/conf/conf.php';
require_once dirname(__FILE__) . '/../include/db.php';
$db = new dbManager;

$query = "SELECT * FROM category WHERE code IS NOT NULL";
$result = $db->query($query);
while ($category = $db->getNextArray($result)) {
	print $category['name']." (". $category['code'].")\n";
	
	$data = array('categoryID'	=> $category['id'],
				  'code'		=> $category['code']);
	
	$db->insert('categoryCode', $data);
}

?>
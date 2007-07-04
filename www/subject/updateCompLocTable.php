<?php

require_once("../include/include.php");

$db = new dbManager(SQL_db, 1);

$query = "SELECT cid, template FROM homepage_config";
$result = $db->dbDoQuery($query);

while($homepage = $db->dbGetNextArray($result) ) {
	if($homepage['template'] == 1 || $homepage['template'] == 2) {
		$query = "UPDATE homepage_comp_location SET `column` = 2, position = position-5 WHERE cid = {$homepage['cid']} AND position > 5";
		$db->dbDoQuery($query);		
	}
}

?>
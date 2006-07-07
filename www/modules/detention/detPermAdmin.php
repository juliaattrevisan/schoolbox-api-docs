<?php
require_once("../../include/session.php");
require_once("../../include/include.php");
require_once("../../include/permission.php");
require_once("../../include/html-time.php");

$db = new dbManager("intranet");
$db->dbConnect();

echo "<p><strong>School Stewards:</strong><br>\n";
$sql = 	"SELECT * ".
		"FROM `userAccess` ".
		"WHERE `service` = 5 ".
		"AND `type` = 4";
$db->dbDoQuery($sql);
while ($row = $db->dbGetNextArray()) {

}
?>

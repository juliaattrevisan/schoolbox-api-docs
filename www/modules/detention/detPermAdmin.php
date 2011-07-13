<?php
require_once("../../include/session.php");
require_once("../../include/include.php");
require_once("../../include/permission.php");
require_once("../../include/html-time.php");

$db = new Database();
$db->dbConnect();

echo "<p><strong>School Stewards:</strong><br>\n";
$sql = 	"SELECT * ".
		"FROM `userAccess` ".
		"WHERE `service` = 5 ".
		"AND `type` = 4";
$db->query($sql);
while ($row = $db->getNextArray()) {

}
?>
bGetNextArray()) {

}
?>

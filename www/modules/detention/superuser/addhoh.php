<?php

include("../../../include/session.php");
include("../../../include/include.php");
include("../../../include/permission.php");

$db = new DeprecatedDatabase();

if ($_SESSION['su'] != 1)
	die("Superuser access only");

if (!empty($_POST['synergy_id'])) {
	$user = $db->getRow("SELECT id, firstname, lastname FROM user WHERE synergy_id={$_POST['synergy_id']}");
	if (!empty($user)) {
		$db->dbInsertArray("userAccess", array("uid"=>$user['id'], "service"=>5, "type"=>3));
		echo "<b>Head of House Added:</b> {$user['firstname']} {$user['lastname']}<br><br>";
	}
}

echo "<form method=\"post\">synergy_id: <input type=\"text\" name=\"synergy_id\"> ";
echo "<input type=\"submit\" value=\"add prefect\"></form>";

?>
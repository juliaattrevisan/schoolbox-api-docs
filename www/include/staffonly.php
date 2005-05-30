<?php
include($_SERVER['DOCUMENT_ROOT'].'/include/permission.php');
if(isStudent($_SESSION['gid']))
{
	error(19);
}
?>

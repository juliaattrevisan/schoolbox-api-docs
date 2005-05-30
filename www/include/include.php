<?php 
require_once($_SERVER['DOCUMENT_ROOT'].'/conf/conf.php');
require_once($_SERVER['DOCUMENT_ROOT'].'/include/dbManager-mysql.php');
require_once($_SERVER['DOCUMENT_ROOT'].'/include/template.php');
require_once($_SERVER['DOCUMENT_ROOT'].'/include/stdfuncs.php');

//include custom code for site
if(file_exists($_SERVER['DOCUMENT_ROOT'].'/include/'.SITE_location.'.php'))
	include_once($_SERVER['DOCUMENT_ROOT'].'/include/'.SITE_location.'.php');
?>

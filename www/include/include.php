<?php
require_once($_SERVER['DOCUMENT_ROOT'] . '/conf/conf.php');
require_once($_SERVER['DOCUMENT_ROOT'] . '/../include/Bootstrap.php');

//include custom code for site
if(file_exists($_SERVER['DOCUMENT_ROOT'].'/include/'.Cfg::get('site_location').'.php'))
	include_once($_SERVER['DOCUMENT_ROOT'].'/include/'.Cfg::get('site_location').'.php');
?>

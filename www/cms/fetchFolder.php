<?php

/************************************************************
*                                                           *
*         Copyright (c) 2002-2008 Lektec Consulting         *
*                                                           *
*							                                *
*  Script to put all resources from a folder into a zip     *
*  and send to client.                                      *
*         				                                    *
*							                                *
/************************************************************/

require_once(dirname(__FILE__) . '/../../include/Bootstrap.php');

require_once('../include/pclzip.lib.php');

$page = new SitePage();
$zip = new ZipFolder($_GET['folder']);
$zip->sendZip();

?>
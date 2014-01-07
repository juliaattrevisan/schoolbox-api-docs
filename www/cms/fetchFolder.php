<?php

/************************************************************
*                                                           *
*         Copyright (c) 2013 Alaress Pty Ltd                *
*                                                           *
*							                                *
*  Script to put all resources from a folder into a zip     *
*  and send to client.                                      *
*         				                                    *
*							                                *
/************************************************************/

require_once(dirname(__FILE__) . '/../../include/Bootstrap.php');

$page = new SitePage();
$zip = new ZipFolder($_GET['folder']);
$zip->sendZip();

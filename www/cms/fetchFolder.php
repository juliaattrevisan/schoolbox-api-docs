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

use Schoolbox\Application\WebRequest\ShutdownHandler;

$page = new SitePage();
$zip = new ZipFolder($_GET['folder']);
ShutdownHandler::instance()->registerFunction([$zip, 'cleanUp'], 10000);
$zip->sendZip();

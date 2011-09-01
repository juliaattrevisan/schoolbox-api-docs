<?php
require_once($_SERVER['DOCUMENT_ROOT'].'/conf/conf.php');
require_once($_SERVER['DOCUMENT_ROOT'].'/include/stdfuncs.php');
require_once($_SERVER['DOCUMENT_ROOT'].'/include/Session.php');

session_start();

if (!isset($_SESSION['id'])) {
	goLoginPage();
}

//if ip tracking enabled ensure user has same ip as when they logged in
if (TRACKIP && $_SESSION['ip'] != Session::getIP()) {
	goLoginPage();
}

//if session remember is disabled and check session has not expired
if (!$_SESSION['remember'] && (($_SESSION['lasttime'] + SESSIONLENGTH) < mktime())) {
	goLoginPage();
}

//reset session timer
$_SESSION['lasttime'] = mktime();

<?php
require_once($_SERVER['DOCUMENT_ROOT'].'/../include/Bootstrap.php');

session_start();

$gotoLoginPage = false;

if (!isset($_SESSION['id'])) {
	$gotoLoginPage = true;
}

//if ip tracking enabled ensure user has same ip as when they logged in
if (TRACKIP && $_SESSION['ip'] != Session::getIP()) {
	$gotoLoginPage = true;
}

//if session remember is disabled and check session has not expired
if (!$_SESSION['remember'] && (($_SESSION['lasttime'] + SESSIONLENGTH) < time())) {
	$gotoLoginPage = true;
}

/* Go to login page if any of the above conditions are defeated
 */
if ($gotoLoginPage === true) {
	$page = new SitePage;
	$page->goLoginPage();
}

//reset session timer
$_SESSION['lasttime'] = time();

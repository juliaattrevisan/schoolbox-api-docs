<?php

require_once '../include/SitePage.php';
session_start();

if ((isset($_POST['username'])) && (isset($_POST['password']) && $_POST['password'] && $_POST['username'])) {
	$sessionHandler = new Session;

	//first check for brute force attempt
	if ($sessionHandler->checkBruteForce()) {
		$page = new SitePage(FALSE);
		$page->goLoginPage('You have attempted more than ' . Config::get('login_allowed') . ' logins in ' . Config::get('login_grace') . ' seconds.<br>
			Please try again in a short while.');
	}

	if ($sessionHandler->authenticate($_POST['username'], $_POST['password']) && $sessionHandler->create($_POST['username'])) {
        if (isset($_POST['page']) && $_POST['page'] && $_POST['page'] != '/') {
            $page = rawurldecode($_POST['page']);
            SitePage::localRedir($page);
        }
        else {
            SitePage::localRedir('/');
        }
	}
	else {
		//get rid of session started earlier to stop it conflicting with next login
		$sessionHandler->destroy();
		$page = new SitePage(FALSE);
		$page->goLoginPage('The username or password you typed is incorrect, please try again', $_POST['page']);
	}
}
else {
	SitePage::localRedir('/');
}

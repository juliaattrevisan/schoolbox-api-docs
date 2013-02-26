<?php
require_once(dirname(__FILE__) . '/../../include/Bootstrap.php');

$page = new SitePage();
$userHandler = new User();
$groupHandler = new Group();

if (!in_array($_SESSION['gid'], $groupHandler->canChangePassword()) && !$_SESSION['su']) {
	error(19);
}

if (isset($_POST['current_password'])) {
	if (empty($_POST['current_password'])) {
		error(105, "You must enter your current password.");
	}

	if ($_POST['new_password'] != $_POST['confirm_password']) {
		error(105, "The new and confirmation passwords you entered do not match.");
	}

	if (strlen($_POST['new_password']) < 6) {
		error(105, "Your new password must be at least 6 characters long.");
	}
	else {
		if (!preg_match('/[a-z]/i', $_POST['new_password'])) {
			error(105, "Your new password must have at least one letter.");
		}
		if (!preg_match('/\\d/', $_POST['new_password'])) {
			error(105, "Your new password must have at least one number.");
		}
		if (!preg_match('/[^a-z\\d]/i', $_POST['new_password'])) {
			error(105, "Your new password must have at least one non-letter or number character.");
		}
	}

	if (empty($error) && !$userHandler->setPassword($_SESSION['id'], $_POST['current_password'], $_POST['new_password'])) {
		error(105, "Your current password did not match what is stored in the database.");
	}

	SitePage::localRedir('/account/modify/success');
}


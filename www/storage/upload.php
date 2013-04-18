<?php
/**
 * 
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2013, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

require_once '../../include/Bootstrap.php';
$sitePage = new SitePage;

if (is_readable($_FILES['file']['tmp_name'])) {
	$imageResource = Storage_ResourceMapper::getInstance()->create(
		$_FILES['file']['name'], $_FILES['file']['tmp_name'], $_SESSION['id']
	);
	$hash = $imageResource->hash;

	if(isset($_POST['return'])) {
		SitePage::localRedir($_POST['return'] . '?hash=' . $hash);
	}
	else {
		$page->backToTheFuture(2);
	}

}
else {
	error(2);
}


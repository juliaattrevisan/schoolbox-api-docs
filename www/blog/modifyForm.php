<?php
require_once dirname(__FILE__) . '/../../include/Bootstrap.php';

$page = new SitePage();
$groupHandle = new Group();
$blogHandle  = new Blog();

if (isset($_POST['delete'])) {
	$blogHandle->delete($_POST['blogID']);
	$page->backToTheFuture(2);
}
else if (!empty($_POST['heading']) ) {
	if (($permission = $groupHandle->getPermission($_POST['cid'])) < WRITE_ACCESS) {
		error(19);
	}

	$blog = array(
		'heading' => $_POST['heading'],
		'body' => $_POST['body'],
	);

	/* Update existing, check ownership
	 */
	if ($_POST['blogID']) {
		if (!$blogHandle->permission($_POST['blogID'])) {
			error(23);
		}

		$blog['id'] = $_POST['blogID'];
		$blogHandle->update($blog);
	}
	/* Create new blog
	 */
	else {
		$blog['cid'] = $_POST['cid'];
		$blogHandle->create($blog);
	}

	SitePage::localRedir('/homepage/'.$_POST['cid']);
}
else {
	error(1);
}
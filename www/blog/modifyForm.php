<?php
require_once $_SERVER['DOCUMENT_ROOT'] . '/../include/sitePage.php';
require_once '../../library/group.php';
require_once '../../library/blog.php';

$page = new SitePage;
$groupHandle = new Group();
$blogHandle  = new Blog();


if(isset($_POST['delete'])) {
		$blogHandle->delete($_POST['blogID']);
		$page->backToTheFuture(2);
}
else if(!empty($_POST['heading']) ) {

	if (($permission = $groupHandle->getPermission($_POST['cid'])) < WRITE_ACCESS)
		error(19);
		
	$blog = array(	'heading'	=> $_POST['heading'],
					'body'		=> $_POST['body'],
				);

	if($_POST['blogID']) {
		//Update existing, check ownership
		if(!$blogHandle->permission($_POST['blogID']))
			error(23);

		$blog['id'] = $_POST['blogID'];
		$blogHandle->update($blog);
	}
	else {
		//Create new blog
		$blog['cid'] = $_POST['cid'];
		$blogHandle->create($blog);
	}

	localRedir('/homepage/'.$_POST['cid']);
}

else
	error(1);
?>

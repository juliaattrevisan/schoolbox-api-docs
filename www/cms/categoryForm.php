<?php
require_once $_SERVER['DOCUMENT_ROOT'] . '/../include/SitePage.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/../library/category.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/../library/group.php';

$page = new SitePage;
$categoryHandler = new Category;
$groupHandler = new Group;

if ($_POST['name']) {
	/* scrubTEXT($_POST['group']): This keeps throwing a warning, is this used anywhere?
	 */

	$category = array(
		'name' => scrubTEXT($_POST['name']),
		'parent' => scrubTEXT($_POST['parent']),
		'template' => scrubTEXT($_POST['template']),
		'code' => scrubTEXT($_POST['code']),
		'group' => scrubTEXT($_POST['group']),
	);

	if (is_numeric($_POST['categoryID'])) {
		if ($groupHandler->getPermission($_POST['categoryID']) < WRITE_ACCESS) {
			error(6);
		}

		$category['id'] = $_POST['categoryID'];
		$categoryHandler->update($category);
	}
	else {
		if ($groupHandler->getPermission($_POST['parent']) < WRITE_ACCESS) {
			error(6);
		}

		$category['id'] = $categoryHandler->create($category);
	}

	if ($_POST['groupcheck']) {
		/* Setup group and forward to group management page */
		$groupHandler->createGroup($category['id']);
		$groupHandler->addUser($category['id'], $_SESSION['id'], 3);

		localRedir('/group/modify/'.$category['id']);
	}
	else {
		/* Redirect to / if the homepage was removed from a category
		 * Otherwise go back to the category
		 */
		if ($_POST['template'] == Homepage::REMOVE_HOMEPAGE_TEMPLATE_ID) {
			localRedir('/resources/' . $category['id']);
		}
		else {
			$page->backToTheFuture(2);
		}
	}
}
else if(is_numeric($_POST['move']) && is_numeric($_POST['categoryID'])) {

	/* Move link from one category to another, after checking permission on both from and to categories */
	if ($_POST['move'] == $_POST['categoryID'] || !$category = $categoryHandler->getByID($_POST['move']) ) {
		echo "Failed";
		exit;
	}

	if ($groupHandler->getPermission($_POST['move']) < WRITE_ACCESS  || $groupHandler->getPermission($_POST['categoryID']) < WRITE_ACCESS) {
		echo "Insufficient permssions";
		exit;
	}

	$categoryHandler->update(array('id'=>$_POST['move'], 'parent'=> $_POST['categoryID']));
}
else if (is_numeric($_GET['delete'])) {
	if (!$category = $categoryHandler->getByID($_GET['delete'])) {
		error(2);
	}

	if ($groupHandler->getPermission($category['id']) < WRITE_ACCESS) {
		error(8);
	}

	$categoryHandler->delete($_GET['delete']);

	$_SESSION['resourcesID'] = $category['parent'];
	$page->backToTheFuture(1);
}
else if (is_numeric($_GET['deleteAll'])) {
	if (!$category = $categoryHandler->getByID($_GET['deleteAll'])) {
		error(2);
	}

	$categoryHandler->recursiveDelete($_GET['deleteAll']);
	$_SESSION['resourcesID'] = $category['parent'];
	$page->backToTheFuture(1);
}
else {
	error(2);
}
?>

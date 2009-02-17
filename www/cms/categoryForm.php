<?php
require_once $_SERVER['DOCUMENT_ROOT'] . '/../library/category.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/../library/group.php';

$categoryHandler = new Category;
$groupHandler = new Group;

if ($_POST['title']) {

	if ($groupHandler->getPermission($_POST['parent']) < WRITE_ACCESS)
		error(6);

	$category = array('name'		=> $_POST['name'],
					  'parent'		=> $_POST['parent'],
					  'template'	=> $_POST['template'],
					  'code'		=> $_POST['code'],
					  );

	if (isset($_POST['id'])) {
		$category['id'] = $_POST['id'];
		$categoryHandler->update($category);
	}
	else
		$categoryHandler->create($category);
	
	$page->backToTheFuture(2);
			
	
}
else if(is_numeric($_POST['move']) && is_numeric($_POST['categoryID'])) {
	
	/* Move link from one category to another, after checking permission on both from and to categories */
	if(!$category = $categoryHandler->getByID($_POST['move']))
		echo "Failed";

	if ($groupHandler->getPermission($_POST['move']) < WRITE_ACCESS)
		echo "Insufficient permssions";

	if ($groupHandler->getPermission($_POST['categoryID']) < WRITE_ACCESS)
		echo "Insufficient permssions";
	
	$categoryHandler->update(array('id'=>$_POST['move'], 'parent'=> $_POST['categoryID']));

}
elseif ($_GET['delete']) {

	if($category = $categoryHandler->getByID($_GET['delete']))
		error(2);
		
	if ($groupHandler->getPermission($file['cid']) < WRITE_ACCESS)
		error(6);
	
	$categoryHandler->delete($_GET['delete']);
	$page->backToTheFuture(1);
}

?>

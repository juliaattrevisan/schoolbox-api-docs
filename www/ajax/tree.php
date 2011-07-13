<?php

require_once dirname(__FILE__) . '/../../include/Bootstrap.php';

$page = new SitePage;
$output = array();

if (is_numeric($_POST['relationID'])) {
	$groupHandler = new Group;
	$categoryHandler = new Category;
	$categoryList = $categoryHandler->getCategoryList($_POST['relationID']);
	if (!is_array($categoryList)) {
		$output = array();
	} else {
		foreach ($categoryList as $category) {
		
			$permission = $groupHandler->getPermission($category['id']);
			if ($permission >= READ_ACCESS) {
				$category['permission'] = '';
				/* mark write access */
				if ($permission == WRITE_ACCESS)
					$category['permission'] = "write";
				
				/* mark full access*/
				if ($permission == FULL_ACCESS)
					$category['permission'] = "full";

				/* append code to name */
				$name = scrubTEXT($category['name']);
				if($category['code'] != '' && $category['code'] != $category['name'])
					$name .= ' ('.scrubTEXT($category['code']).')';
				
				$category['name'] = $name;
					
				$output[] = $category;
			}
		}
	}

	if (empty($output))
		$output = array('error' => 'This folder does not contain any visible subfolders.');

	
	$_SESSION['resourcesID'] = $_POST['relationID'];
	
}
elseif (is_numeric($_POST['resourcesID']))
	$_SESSION['resourcesID'] = $_POST['resourcesID'];

	
	
header("Content-type: application/json");
print json_encode($output);

?>
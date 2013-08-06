<?php


$this->setFile(TEMPLATE_content, 'group/bulk.html');

$this->setTitle('Add Bulk Members');
$this->setTab('resources');

$categoryHandler = new Category;

if (is_numeric($this->arg[0])) {

	$category = $categoryHandler->getByID($this->arg[0]);

	// check access
	if(!$_SESSION['su'] && $category['owner'] != $_SESSION['id'])
		error(19);

	$output = array('CATEGORY_NAME'	=> Scrub::text($category['name']),
					'CATEGORY_ID'	=> $category['id'],
					);
	$this->setVar($output);

} else
	error(2);

?>
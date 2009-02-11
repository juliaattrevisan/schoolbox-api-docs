<?php

require_once '../library/group.php';
$groupHandler = new Group;

if(is_numeric($this->arg[0])) {
	
	require_once '../library/link.php';
	$linkHandler = new Link;
	
	/* Modify existing link */
	$link = $linkHandler->getByID($this->arg[0]);
	if(!is_array($link))
		error(41);

	/* Check permissions to submit */
	if($groupHandler->getPermission($link['category']) < WRITE_ACCESS )
		error(6);

	$this->setFile(TEMPLATE_content, 'cms/modify-link.html');

	$this->setTitle('Modify Link');	
	
	$output = array('ACTION'			=> 'Modify',
					'LINK_ID'			=> $link['id'],
					'URL'				=> $link['url'],
					'DESCRIPTION'		=> scrubText($link['description']),
					'COMMENT'			=> scrubText($link['comment']),	
					'CATEGORY_NAME'		=> scrubText($link['categoryName']),
					'CATEGORY_ID'		=> $link['cid'],
					'HIDDEN_SELECTED'	=> 	$link['hidden'] > 0 ? 'checked="checked"' : '',
					);
	$this->setVar($output);
	
	
} else if($this->arg[0] == 'new' && is_numeric($this->arg[1])) {
	
	require_once '../library/category.php';
	$categoryHandler = new Category;
	
	/* Add new link to system */
	$this->setFile(TEMPLATE_content, 'cms/modify-link.html');
	$this->setTitle('Add Link');	
	
	/* Check folder exists */
	$category = $categoryHandler->getByID($this->arg[1]);
	if(is_array($category))
		error(13);
	
	/* Check permissions to submit */
	if($groupHandler->getPermission($folder['id']) < WRITE_ACCESS )
		error(6);
		
	$output = array('ACTION'			=> 'Add',
					'LINK_ID'			=> '',
					'URL'				=> '',
					'DESCRIPTION'		=> '',
					'COMMENT'			=> '',	
					'HIDDEN_SELECTED'	=> '',
					'CATEGORY_NAME'		=> scrubText($category['categoryName']),
					'CATEGORY_ID'		=> $category['id'],
					);
	$this->setVar($output);
	
}
else {
	error(2);
}

?>

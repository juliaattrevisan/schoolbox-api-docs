<?php

require_once $_SERVER['DOCUMENT_ROOT'] . '/../library/group.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/../library/categoryNews.php';

$groupHandler = new Group;

if(is_numeric($this->arg[0])) {

	/* Modify existing news item */
	$catNewsHandler = new CategoryNews;
	
	if(!$newsItem = $catNewsHandler->getByID($this->arg[0]))
		error(2);
	
	if (($permission = $groupHandler->getPermission($newsItem['code'])) < WRITE_ACCESS)
		error(23);
	
	$this->setFile(TEMPLATE_content, 'news/homepage.html');
	$this->setBlock(TEMPLATE_content, 'delete_button', 'delete_button_block');
	
	$this->setTitle('Modify Homepage News');
	$this->setTab('resources');
	
	$output = array(	'ACTION'				=> 'Modify',
						'NEWS_ID'				=> $newsItem['id'],
						'HEADING'				=> $newsItem['heading'],
						'BODY'					=> $newsItem['body'],
						'CATEGORY_ID'			=> $newsItem['code'],
						'LEAVE_OPTION'			=> '<option value="0" selected>Keep existing</option>',
						'DEFAULT_TIME_SELECTED'	=> '',
					);
						
	$this->setVar($output);
	
	$this->parse('delete_button_block', 'delete_button');
	
}
else if($this->arg[0] == 'new' && is_numeric($this->arg[1])) {
	
	/* Create a new news item */
	
	if (($permission = $groupHandler->getPermission($this->arg[1])) < WRITE_ACCESS)
		error(23);
	
	$this->setFile(TEMPLATE_content, 'news/homepage.html');
	$this->setBlock(TEMPLATE_content, 'delete_button', 'delete_button_block');
	
	$this->setTitle('Create Homepage News');
	$this->setTab('resources');
	
	$output = array(	'ACTION'				=> 'Create',
						'HEADING'				=> '',
						'BODY'					=> '',
						'NEWS_ID'				=> '',
						'CATEGORY_ID'			=> $this->arg[1],
						'delete_button_block'	=> '',
						'LEAVE_OPTION'			=> '',
						'DEFAULT_TIME_SELECTED'	=> 'selected="selected"',
					);
						
	$this->setVar($output);
	
	
}
else
	error(2);

?>
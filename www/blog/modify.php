<?php

require_once '../library/group.php';
require_once '../library/blog.php';
$groupHandle = new Group(); 
$blogHandle  = new Blog();

$this->setFile(TEMPLATE_content, 'blog/modify.html');

if($this->arg[0] == 'new' && is_numeric($this->arg[1])) {
	if ($groupHandle->getPermission($this->arg[1]) < WRITE_ACCESS)
		error(23);
	
	if(empty($_POST)) {
		$this->setBlock(TEMPLATE_content, 'delete', 'delete_block');
		$this->setVar(array(
			'ACTION'		=> 'Create',
			'ENTRY_HEADING' => '',
			'ENTRY_BODY'    => '',
			'delete_block'	=> ''
		));
	} else {
		if(!empty($_POST['heading']) && !empty($_POST['body'])) {
			$blogHandle->create(array(
				'cid'     => $this->arg[1],
				'heading' => $_POST['heading'],
				'body'    => $_POST['body']
			));
			
			$this->backToTheFuture(2);
		} else
			error(23);
	}
} elseif(is_numeric($this->arg[0])) {
	$entry = $blogHandle->getByID($this->arg[0]);

	if(!$blogHandle->permission($entry['id']))
		error(23);
	
	if(empty($_POST)) {
		$this->setVar(array(
			'ACTION'		=> 'Modify',
			'ENTRY_HEADING' => scrubTEXT($entry['heading']),
			'ENTRY_BODY'    => scrubHTML($entry['body'])
		));
	} elseif(isset($_POST['submit'])) {
		if(!empty($_POST['heading']) && !empty($_POST['body'])) {
			$blogHandle->update(array(
				'id'      => $entry['id'],
				'cid'     => $entry['cid'],
				'heading' => $_POST['heading'],
				'body'    => $_POST['body']
			));
			
			$this->backToTheFuture(2);
		} else
			error(23);
	} elseif(isset($_POST['delete'])) {
		$blogHandle->delete($this->arg[0]);
		$this->backToTheFuture(2);
	}
}



?>
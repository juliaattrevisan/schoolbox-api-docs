<?php

require_once '../library/group.php';
require_once '../library/blog.php';
$groupHandle = new Group();
$blogHandle  = new Blog();

$this->setFile(TEMPLATE_content, 'blog/modify.html');

if($this->arg[0] == 'new' && is_numeric($this->arg[1])) {
	if ($groupHandle->getPermission($this->arg[1]) < WRITE_ACCESS)
		error(23);

	$this->setTitle('Create Blog Entry');

	$this->setBlock(TEMPLATE_content, 'delete', 'delete_block');
	$this->setVar(array(
		'ACTION'		=> 'Create',
		'ENTRY_HEADING' => '',
		'ENTRY_BODY'    => '',
		'CID'			=> $this->arg[1],
		'ID'			=> '',
		'delete_block'	=> ''
	));

} elseif(is_numeric($this->arg[0])) {
	$entry = $blogHandle->getByID($this->arg[0]);

	$this->setTitle('Modify Blog Entry');
	if(!$blogHandle->permission($entry['id']))
		error(23);

	$this->setVar(array(
		'ACTION'		=> 'Modify',
		'ENTRY_HEADING' => Scrub::text($entry['heading']),
		'ENTRY_BODY'    => Scrub::html($entry['body']),
		'CID'			=> $entry['cid'],
		'ID'			=> $this->arg[0],
	));
}

?>
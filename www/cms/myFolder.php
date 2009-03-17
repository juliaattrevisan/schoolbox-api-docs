<?php
require_once '../library/my-folder.php';
$folderHandler = new MyFolder;

if(is_numeric($this->arg[0])) {
	
	if(!($folder = $folderHandler->getByID($this->arg[0])))
		error(2);
	
	$this->setFile(TEMPLATE_content, "cms/myFolder.html");
	$this->setBlock(TEMPLATE_content, 'perm_table', 'perm_table_block');
	$this->setBlock(TEMPLATE_content, 'indiv_perms', 'indiv_perms_block');
	$this->setBlock(TEMPLATE_content, 'indiv_modify', 'indiv_modify_block');

	$this->setTitle('Modify Folder');	
	$this->setTab('resources');
	
	$output = array('ACTION'		=> 'Modify',
					'FOLDER_ID'		=> $folder['id'],
					'NAME'			=> scrubText($folder['name']),
					);
	$this->setVar($output);

	$folderTree = $folderHandler->getFolderTree(0, '&nbsp;&nbsp;', $folder['id']);
	array_unshift($folderTree, array('id' => 0, 'name' => 'Top Level'));
	$this->dropDown('parent_list', $folderTree, $folder['parent'], TEMPLATE_content, array('id' => 'PARENT_ID', 'name' => 'PARENT_NAME'));


	
} else if($this->arg[0] == 'new' && is_numeric($this->arg[1])) {

	$this->setFile(TEMPLATE_content, 'cms/myFolder.html');
	$this->setBlock(TEMPLATE_content, 'perm_table', 'perm_table_block');
	$this->setBlock(TEMPLATE_content, 'indiv_perms', 'indiv_perms_block');
	$this->setBlock(TEMPLATE_content, 'indiv_modify', 'indiv_modify_block');

	$this->setTitle('Add Folder');
	$this->setTab('resources');	

	$output = array('ACTION'		=> 'Add',
					'FOLDER_ID'		=> '',
					'NAME'			=> '',
					);
	$this->setVar($output);
	

	$folderTree = $folderHandler->getFolderTree(0, '&nbsp;&nbsp;');
	array_unshift($folderTree, array('id' => 0, 'name' => 'Top Level'));
	$this->dropDown('parent_list', $folderTree, $this->arg[1], TEMPLATE_content, array('id' => 'PARENT_ID', 'name' => 'PARENT_NAME'));

	
} else
	$this->error(2);

?>

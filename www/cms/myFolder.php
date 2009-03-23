<?php
require_once '../library/my-folder.php';
$folderHandler = new MyFolder;

if(is_numeric($this->arg[0])) {
	
	if(!($folder = $folderHandler->getByID($this->arg[0])))
		error(2);
	
	$this->setFile(TEMPLATE_content, "cms/myFolder.html");
	$this->setBlock(TEMPLATE_content, 'parent_list', 'parent_list_block');


	$this->setTitle('Modify Folder');	
	$this->setTab('resources');
	
	$output = array('ACTION'		=> 'Modify',
					'FOLDER_ID'		=> $folder['id'],
					'NAME'			=> scrubText($folder['name']),
					);
	$this->setVar($output);

	$parentTree = $folderHandler->getFolderTree(0, 0, $folder['id']);
	if (is_array($parentTree)) {
		array_unshift($parentTree, array('id' => 0, 'name' => 'Top Level', 'depth' => -1));
		foreach ($parentTree as $parent) {
			$output = array('PARENT_ID'			=> $parent['id'],
							'PARENT_NAME'		=> $parent['name'],
							'PARENT_LEFT'		=> number_format(($parent['depth'] + 1) * 14, 0),
							'PARENT_SELECTED'	=> $parent['id'] == $folder['parent'] ? 'selected="selected"' : '',
							);
			$this->setVar($output);
			$this->parse('parent_list_block', 'parent_list', true);
		}
	}


	
} else if($this->arg[0] == 'new' && is_numeric($this->arg[1])) {

	$this->setFile(TEMPLATE_content, 'cms/myFolder.html');
	$this->setBlock(TEMPLATE_content, 'parent_list', 'parent_list_block');


	$this->setTitle('Add Folder');
	$this->setTab('resources');	

	$output = array('ACTION'		=> 'Add',
					'FOLDER_ID'		=> '',
					'NAME'			=> '',
					);
	$this->setVar($output);
	


	$parentTree = $folderHandler->getFolderTree();
	if (is_array($parentTree)) {
		array_unshift($parentTree, array('id' => 0, 'name' => 'Top Level', 'depth' => -1));
		foreach ($parentTree as $parent) {
			$output = array('PARENT_ID'			=> $parent['id'],
							'PARENT_NAME'		=> $parent['name'],
							'PARENT_LEFT'		=> number_format(($parent['depth'] + 1) * 14, 0),
							'PARENT_SELECTED'	=> $parent['id'] == $this->arg[1] ? 'selected="selected"' : '',
							);
			$this->setVar($output);
			$this->parse('parent_list_block', 'parent_list', true);
		}
	}

	
} else
	$this->error(2);

?>

<?php
require_once(dirname(__FILE__) . '/../../include/Bootstrap.php');

if (Cfg::get('webdav_enable')) {
	require_once $_SERVER['DOCUMENT_ROOT'] . '/../library/portfolioWebDAV.php';
	$fileHandler = PortfolioWebDAV::factory();
	$folderHandler = $fileHandler;
} else {
	require_once '../library/my-file.php';
	require_once '../library/my-folder.php';

	$fileHandler = new MyFile;
	$folderHandler = new MyFolder;
}

if(is_numeric($this->arg[0])) {

	/* Modify existing file */

	$file = $fileHandler->getByID($this->arg[0]);
	if(!is_array($file))
		error(41);

	$this->setFile(TEMPLATE_content, 'cms/myFile.html');
	$this->setBlock(TEMPLATE_content, 'browse_row', 'browse_row_block');
	$this->setBlock(TEMPLATE_content, 'folder_list', 'folder_list_block');
	$this->setTitle('Modify File');
	$this->setTab('myfiles');

	$this->setVar('browse_row_block', '');

	$output = array('ACTION'			=> 'Modify',
					'FILE_ID'			=> $file['id'],
					'DESCRIPTION'		=> Scrub::text($file['description']),
					'MAX_FILE_SIZE'		=> Cfg::get('max_file_size'),
					'PARENT_ID'			=> $file['parent'],
					);
	$this->setVar($output);

	if (Cfg::get('webdav_enable')) {
		$this->setBlock(TEMPLATE_content, 'location', 'location_block');
		$this->setVar('location_block', '');

	} else {
		$folderTree = $folderHandler->getFolderTree();
		if (is_array($folderTree)) {
			array_unshift($folderTree, array('id' => 0, 'name' => 'Top Level', 'depth' => -1));
			foreach ($folderTree as $folder) {
				$output = array('FOLDER_ID'			=> $folder['id'],
								'FOLDER_NAME'		=> $folder['name'],
								'FOLDER_INDENT'		=> str_repeat('&nbsp;', ($folder['depth'] + 1) * 4),
								'FOLDER_SELECTED'	=> $folder['id'] == $file['parent'] ? 'selected="selected"' : '',
								);
				$this->setVar($output);
				$this->parse('folder_list_block', 'folder_list', true);
			}
		}
	}



}
elseif($this->arg[0] == 'new' && is_numeric($this->arg[1])) {

	/* Add new file to system */
	if ($this->arg[1] == 0)
		$parent = array('id' => 0);
	elseif (!$parent = $folderHandler->getByID($this->arg[1]))
		error(13);

	$this->setFile(TEMPLATE_content, 'cms/myFile.html');
	$this->setBlock(TEMPLATE_content, 'folder_list', 'folder_list_block');
	$this->setTitle('Add File');
	$this->setTab('resources');

	$output = array('ACTION'			=> 'Add',
					'FILE_ID'			=> '',
					'DESCRIPTION'		=> '',
					'MAX_FILE_SIZE'		=> Cfg::get('max_file_size'),
					'PARENT_ID'			=> $parent['id'],
				);

	$this->setVar($output);

	if (Cfg::get('webdav_enable')) {
		$this->setBlock(TEMPLATE_content, 'desc', 'desc_block');
		$this->setBlock(TEMPLATE_content, 'location', 'location_block');
		$this->setVar('desc_block', '');
		$this->setVar('location_block', '');

	} else {
		$folderTree = $folderHandler->getFolderTree();
		if (is_array($folderTree)) {
			array_unshift($folderTree, array('id' => 0, 'name' => 'Top Level', 'depth' => -1));
			foreach ($folderTree as $folder) {
				$output = array('FOLDER_ID'			=> $folder['id'],
								'FOLDER_NAME'		=> $folder['name'],
								'FOLDER_INDENT'		=> str_repeat('&nbsp;', ($folder['depth'] +1) * 4),
								'FOLDER_SELECTED'	=> $folder['id'] == $parent['id'] ? 'selected="selected"' : '',
								);
				$this->setVar($output);
				$this->parse('folder_list_block', 'folder_list', true);
			}
		}
	}


}
else
	error(2);

?>
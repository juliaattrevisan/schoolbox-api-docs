<?php
require_once '../library/my-file.php';
require_once '../library/my-folder.php';

$fileHandler = new MyFile;
$folderHandler = new MyFolder;

if(is_numeric($this->arg[0])) {
	
	/* Modify existing file */
	
	$file = $fileHandler->getByID($this->arg[0]);
	if(!is_array($file))
		error(41);

	$this->setFile(TEMPLATE_content, 'cms/myFile.html');
	$this->setBlock(TEMPLATE_content, 'browse_row', 'browse_row_block');
	$this->setTitle('Modify File');	
	$this->setTab('myfiles');
	
	$this->setVar('browse_row_block', '');
	
	$output = array('ACTION'			=> 'Modify',
					'FILE_ID'			=> $file['id'],
					'DESCRIPTION'		=> scrubText($file['description']),
					'MAX_FILE_SIZE'		=> MAX_FILE_SIZE,
					);
	$this->setVar($output);
	
	$this->dropDown('folder_list', $folderHandler->getFolderTree(), $file['parent'], TEMPLATE_content, array('id' => 'FOLDER_ID', 'name' => 'FOLDER_NAME'));
	
} 
elseif($this->arg[0] == 'new' && is_numeric($this->arg[1])) {
	
	/* Add new file to system */
	
	$this->setFile(TEMPLATE_content, 'cms/myFile.html');
	$this->setTitle('Add File');	
	$this->setTab('resources');
	
	$output = array('ACTION'			=> 'Add',
					'FILE_ID'			=> '',
					'DESCRIPTION'		=> '',
					'MAX_FILE_SIZE'		=> MAX_FILE_SIZE,
				);

	$this->setVar($output);

	$this->dropDown('folder_list', $folderHandler->getFolderTree(), $this->arg[1], TEMPLATE_content, array('id' => 'FOLDER_ID', 'name' => 'FOLDER_NAME'));

}
else
	error(2);

?>
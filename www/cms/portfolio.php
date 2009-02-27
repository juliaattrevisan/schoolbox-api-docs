<?php

require_once $_SERVER['DOCUMENT_ROOT'] . '/../library/my-folder.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/../library/my-file.php';

require_once $_SERVER['DOCUMENT_ROOT'] . '/../library/group.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/../library/allowed-file.php';

$this->setFile(TEMPLATE_content, 'cms/portfolio.html');
$this->setBlock(TEMPLATE_content, 'folder_list', 'folder_list_block');
$this->setBlock(TEMPLATE_content, 'file_list', 'file_list_block');
$this->setBlock('custom_head', 'allowed_files', 'allowed_files_block');

$this->setJavascript('/javascript/jquery.js');
$this->setJavascript('/javascript/jquery.ui.js');
$this->setJavascript('/javascript/jquery.contextmenu.js');
$this->setJavascript('/javascript/jquery.simplemodal.js');
$this->setJavascript('/javascript/jquery.resources.js');

$this->setCss('/css/jquery.contextmenu.css');
$this->setCss('/css/jquery.resources.css');

$this->setTitle('My Files');
$this->setTab('myfiles');

$folderHandler = new MyFolder;
$fileHandler = new MyFile;

$groupHandler = new Group;
$allowedFiles = new AllowedFile;

if (is_numeric($this->arg[0])) {
	$_SESSION['portfolioID'] = $this->arg[0];
	localRedir('/cms/portfolio/');
} else
	$openFolder = (int)$_SESSION['portfolioID'];



// get parentIDs from openFolder to 0
$parentList[] = $parentID = $openFolder;
while ($parentID > 0)
	$parentList[] = $parentID = $folderHandler->getParent($parentID);
	

// output folders, column 1
$folderList = $folderHandler->getFolderNode($openFolder);
if (is_array($folderList)) {

	foreach ($folderList as $folder) {
	
	
		$folder['children'] = $folderHandler->hasChildren($folder['id']);
		
		$class = array();
		
		// set parent folder
		$class[] = "child-of-node-{$folder['parent']}";
		
		// has children
		$folder['children'] > 0 ? $class[] = "parent" : null;
		
		// is currently expanded
		in_array($folder['id'], $parentList) ? $class[] = "expanded populated" : null;
		
		// has files showing
		$folder["id"] == $openFolder ? $class[] = "opened selected" : null;
		
		// write access
		$class[] = "write";
		
		$output = array('FOLDER_ID'		=> $folder['id'],
						'FOLDER_NAME'	=> scrubText($folder['name']),
						'CLASS'			=> implode(" ", $class),
						);	
		$this->setVar($output);
		$this->parse('folder_list_block', 'folder_list', true);
	}
}

// output files, column 2
$fileList = $fileHandler->getAllInFolder($openFolder);
if (is_array($fileList) && count($fileList) > 0) {
	
	foreach ($fileList as $file) {
		
		$class = array();
		
		// icon
		$class[] = !empty($file['iconFile']) ? substr($file["iconFile"], 0, strpos($file["iconFile"], ".")) : "textfile";
	
		$output = array('FILE_ID' 	 	=> $file['id'],
						'TYPE'			=> 'file',
						'FILE_NAME' 	=> scrubText($file['description']),
						'FILE_MODIFIED'	=> outputDate(DATE_short_format, $file['submitted']),
						'FILE_SIZE'		=> $file['file_size'] > 0 ? bytesToString($file['file_size']) : "--",
						'FOLDER_ID'		=> $file['parent'],
						'CLASS'			=> implode(" ", $class),
						);
						
		$this->setVar($output);
		$this->parse('file_list_block', 'file_list', true);

	}
} else
	$this->setVar('file_list_block', '');	

// output mime classes
$allowedList = $allowedFiles->getAll();
if (is_array($allowedList)) {
	
	$last = '';
	foreach ($allowedList as $allowed) {
		if ($last != $allowed["iconFile"]) {
			$output = array("NAME"	=> substr($allowed["iconFile"], 0, strpos($allowed["iconFile"], ".")),
							"ICON"	=> $allowed["iconFile"],
							);
			
			$this->setVar($output);
			$this->parse("allowed_files_block", "allowed_files", true);
			
			$last = $allowed["iconFile"];
		}
	}
} else
	$this->setVar('allowed_files_block', '');

?>
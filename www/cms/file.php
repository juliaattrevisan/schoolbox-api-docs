<?php

use \Schoolbox\Resource\FileMapper;
use \Schoolbox\Resource\CategoryMapper;


$groupHandler = new Group;


if(is_numeric($this->arg[0])) {

	/* Modify existing file */
    try{
        $file = FileMapper::getInstance()->findById($this->arg[0]);
        $folder = CategoryMapper::getInstance()->findById($file->getFolder()->getId());
    }
    catch (Exception $e) {
		error(41);
	}

	if ($groupHandler->getPermission($file->getFolder()->getId()) < WRITE_ACCESS) {
		error(6);
	}

	$this->setFile(TEMPLATE_content, 'cms/modify-file.html');
	//$this->setBlock(TEMPLATE_content, 'browse', 'browse_block');
	$this->setBlock(TEMPLATE_content, 'show_my_files', 'show_my_files_block');
	$this->setBlock('show_my_files', 'optgroup', 'optgroup_block');
	$this->setBlock('optgroup', 'my_files', 'my_files_block');

	$this->setTitle('Modify File');
	$this->setTab('resources');

	//$this->setVar('browse_block', '');
	$output = array(
		'ACTION' => 'Modify',
		'FILE_ID' => $file->getId(),
		'DESCRIPTION' => Scrub::text($file->getDescription()),
		'COMMENT' => Scrub::text($file->getComment()),
		'HIDDEN_SELECTED' => $file->getHidden() > 0 ? 'checked="checked"' : '',
		'MAX_FILE_SIZE' => Cfg::get('max_file_size'),
		'CATEGORY_ID' => $file->getFolder()->getId(),
		'CATEGORY_NAME' => Scrub::text($folder->getName()),
		'DUE_DAY' => '',
		'DUE_MONTH' => '',
		'DUE_YEAR' => '',
	);

	$this->setVar($output);

    outputMyFileSelector($this);
}
else if($this->arg[0] == 'new' && is_numeric($this->arg[1])) {

	/* Add new file to system */

	$this->setFile(TEMPLATE_content, 'cms/modify-file.html');
	$this->setBlock(TEMPLATE_content, 'show_my_files', 'show_my_files_block');
	$this->setBlock('show_my_files', 'optgroup', 'optgroup_block');
	$this->setBlock('optgroup', 'my_files', 'my_files_block');
    $this->setBlock(TEMPLATE_content, 'delete_button','delete_button_block');
    $this->setVar('delete_button_block','');

	$this->setTitle('Add File');
	$this->setTab('resources');

    try{
        $folder = CategoryMapper::getInstance()->findById($this->arg[1]);
    }
    catch (Exception $e) {
		error(13);
	}

	if ($groupHandler->getPermission($folder->getId()) < WRITE_ACCESS) {
		error(6);
	}

	$output = array(
		'ACTION' => 'Post',
		'FILE_ID' => '',
		'DESCRIPTION' => '',
		'COMMENT' => '',
		'HIDDEN_SELECTED' => (Cfg::get('file_default_to_hidden') ? 'checked="checked"' : ''),
		'MAX_FILE_SIZE' => Cfg::get('max_file_size'),
		'CATEGORY_NAME' => Scrub::text($folder->getName()),
		'CATEGORY_ID' => $folder->getId(),
		'DUE_DAY' => is_numeric($this->arg[2]) ? $this->arg[2] : '',
		'DUE_MONTH' => is_numeric($this->arg[3]) ? $this->arg[3] : '',
		'DUE_YEAR' => is_numeric($this->arg[4]) ? $this->arg[4] : '',
    );

	$this->setVar($output);

    outputMyFileSelector($this);
}
else if($this->arg[0] == 'multi' && is_numeric($this->arg[1])) {
	/* Upload multiple files at once */

	$this->setFile(TEMPLATE_content, 'cms/add-multi-file.html');

	$this->setTitle('Upload Multiple Files');
	$this->setTab('resources');

    try{
        $folder = CategoryMapper::getInstance()->findById($this->arg[1]);
    }
    catch (Exception $e) {
		error(13);
	}

	if ($groupHandler->getPermission($folder->getId()) < WRITE_ACCESS) {
		error(6);
	}

	$output = array(
        'CATEGORY_NAME'		=> Scrub::text($folder->getName()),
        'CATEGORY_ID'		=> $folder->getId(),
        'FILE_max_upload'	=> Cfg::get('file_upload_count'),
        'HIDDEN_SELECTED'	=> (Cfg::get('file_default_to_hidden') ? 'checked="checked"' : ''),
        'SESSION_NAME'		=> session_name(),
        'SESSION_ID'		=> session_id(),
    );
	$this->setVar($output);

	/* Allowed filetypes
	 */
	$extensionMapper = new \Schoolbox\File\ExtensionMapper();
	$allExtensions = $extensionMapper->findAllowedExtensions();

	$allowedFileTypes = array();

	foreach($allExtensions as $currentExtension) {
		$allowedFileTypes[] = '*.' . $currentExtension->getExtension();
	}

	$this->setVar(array(
		'UPLOADIFY_ALL_ALLOWED_FILETYPES' => implode('; ', $allowedFileTypes)
	));
}
else if($this->arg[0] == 'multicat') {
	/* Upload a file to multiple categories */

	$this->setFile(TEMPLATE_content, 'cms/add-file-multi-cat.html');
    $this->setBlock(TEMPLATE_content, 'show_my_files', 'show_my_files_block');
    $this->setBlock('show_my_files', 'optgroup', 'optgroup_block');
    $this->setBlock('optgroup', 'my_files', 'my_files_block');
    $this->setBlock(TEMPLATE_content, 'category_list', 'category_list_block');
	$this->setBlock(TEMPLATE_content, 'no_categories', 'no_categories_block');

	$this->setTitle('Upload a File to Multiple Locations');
	$this->setTab('resources');

	$output = array(
		'MAX_FILE_SIZE'   => Cfg::get('max_file_size'),
		'HIDDEN_SELECTED' => (Cfg::get('file_default_to_hidden') ? 'checked="checked"' : ''),
	);

	$this->setVar($output);

	$groupHandler = new Group;
	$categoryList = $groupHandler->involvedInCategoryList($_SESSION['id'], TRUE, WRITE_ACCESS);

	if (is_array($categoryList) && count($categoryList)) {
		foreach($categoryList as $category) {
			$output = array(
				'CATEGORY_NAME' =>Scrub::text($category['name']) . ($category['code']?' ('.$category['code'].')':''),
				'CATEGORY_ID' => $category['id'],
			);

			$this->setVar($output);

			$this->parse('category_list_block', 'category_list', true);
		}

		$this->setVar('no_categories_block', '');
	}
	else {
		error(154);
	}

    outputMyFileSelector($this);
}
else {
	error(2);
}

/**
 * Stupid output method for stupid file selector.
 *
 * Factored out of duplicate code for add/edit file.
 *
 * @param Sitepage $sitepage The Sitepage to spam blocks into
 */
function outputMyFileSelector($sitepage) {
    $myFolderHandler = new MyFolder;
    $myFileHandler   = new MyFile;

    /* Check for files in the root
    */
    $fileList = $myFileHandler->getAllInFolder(0);

    if (is_array($fileList)) {
        $sitepage->setVar(
                 array(
                     'MYFOLDER_NAME' => 'Top'
                 )
        );

        foreach ($fileList as $file) {
            $output = array(
                'MYFILE_ID'   => $file['id'],
                'MYFILE_NAME' => $file['description'],
            );

            $sitepage->setVar($output);
            $sitepage->parse('my_files_block', 'my_files', true);
        }
    }
    $sitepage->parse('optgroup_block', 'optgroup', true);
    $sitepage->setVar('my_files_block', '');

    $folderTree = $myFolderHandler->getFolderTree();
    if (is_array($folderTree)) {
        foreach ($folderTree as $folder) {
            $output = array(
                'MYFOLDER_NAME' => trim($folder['name']),
                'MYFOLDER_LEFT' => number_format($folder['depth'] * 14, 0),
            );
            $sitepage->setVar($output);
            $fileList = $myFileHandler->getAllInFolder($folder['id']);
            if (is_array($fileList)) {
                foreach ($fileList as $fileOut) {
                    $output = array(
                        'MYFILE_ID'   => $fileOut['id'],
                        'MYFILE_NAME' => $fileOut['description'],
                    );
                    $sitepage->setVar($output);
                    $sitepage->parse('my_files_block', 'my_files', true);
                }
            }
            $sitepage->parse('optgroup_block', 'optgroup', true);
            $sitepage->setVar('my_files_block', '');
        }
    }

    $sitepage->parse('show_my_files_block', 'show_my_files');
}

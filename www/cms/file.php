<?php

require_once $_SERVER['DOCUMENT_ROOT'] . '/../library/group.php';
require_once '../library/my-folder.php';
require_once '../library/my-file.php';

$groupHandler = new Group;

if(is_numeric($this->arg[0])) {

	/* Modify existing file */

	require_once '../library/file.php';
	require_once '../library/submission.php';
	$fileHandler = new File;

	$file = $fileHandler->getByID($this->arg[0]);

	if (!is_array($file)) {
		error(41);
	}

	if ($groupHandler->getPermission($file['cid']) < WRITE_ACCESS) {
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
		'FILE_ID' => $file['id'],
		'DESCRIPTION' => Scrub::text($file['description']),
		'COMMENT' => Scrub::text($file['comment']),
		'HIDDEN_SELECTED' => $file['hidden'] > 0 ? 'checked="checked"' : '',
		'MAX_FILE_SIZE' => Cfg::get('max_file_size'),
		'CATEGORY_ID' => $file['cid'],
		'CATEGORY_NAME' => Scrub::text($file['categoryName']),
		'DUE_DAY' => '',
		'DUE_MONTH' => '',
		'DUE_YEAR' => '',
	);

	$this->setVar($output);

	$myFolderHandler = new MyFolder;
	$myFileHandler = new MyFile;

	$folderTree = $myFolderHandler->getFolderTree();
	if (is_array($folderTree)) {
		foreach ($folderTree as $folder) {
			$output = array('MYFOLDER_NAME'	=> trim($folder['name']),
					'MYFOLDER_LEFT'	=> number_format($folder['depth'] * 14, 0),
					);
			$this->setVar($output);
			$fileList = $myFileHandler->getAllInFolder($folder['id']);
			if (is_array($fileList)) {
				foreach ($fileList as $fileOut) {
					$output = array('MYFILE_ID'		=> $fileOut['id'],
									'MYFILE_NAME'	=> $fileOut['description'],
									);
					$this->setVar($output);
					$this->parse('my_files_block', 'my_files', true);
				}
			}
			$this->parse('optgroup_block', 'optgroup', true);
			$this->setVar('my_files_block', '');
		}
	}

	/* Parse show_my_files only if WebDAV is disabled
	 */
	if (!Cfg::get('webdav_enable')) {
		$this->parse('show_my_files_block', 'show_my_files');
	}
	else {
		$this->setVar('show_my_files_block', '');
	}
}
else if($this->arg[0] == 'new' && is_numeric($this->arg[1])) {

	/* Add new file to system */
	require_once '../library/category.php';

	$this->setFile(TEMPLATE_content, 'cms/modify-file.html');
	$this->setBlock(TEMPLATE_content, 'show_my_files', 'show_my_files_block');
	$this->setBlock('show_my_files', 'optgroup', 'optgroup_block');
	$this->setBlock('optgroup', 'my_files', 'my_files_block');
    $this->setBlock(TEMPLATE_content, 'delete_button','delete_button_block');
    $this->setVar('delete_button_block','');

	$this->setTitle('Add File');
	$this->setTab('resources');

	$categoryHandler = new Category;
	$category = $categoryHandler->getByID($this->arg[1]);

	if (!is_array($category)) {
		error(13);
	}

	if ($groupHandler->getPermission($category['id']) < WRITE_ACCESS) {
		error(6);
	}

	$output = array(
		'ACTION' => 'Post',
		'FILE_ID' => '',
		'DESCRIPTION' => '',
		'COMMENT' => '',
		'HIDDEN_SELECTED' => '',
		'MAX_FILE_SIZE' => Cfg::get('max_file_size'),
		'CATEGORY_NAME' => Scrub::text($category['name']),
		'CATEGORY_ID' => $category['id'],
		'DUE_DAY' => is_numeric($this->arg[2]) ? $this->arg[2] : '',
		'DUE_MONTH' => is_numeric($this->arg[3]) ? $this->arg[3] : '',
		'DUE_YEAR' => is_numeric($this->arg[4]) ? $this->arg[4] : '',
    );

	$this->setVar($output);


	$myFolderHandler = new MyFolder;
	$myFileHandler = new MyFile;

	$folderTree = $myFolderHandler->getFolderTree();

	if (is_array($folderTree)) {
		foreach ($folderTree as $folder) {
			$output = array(
                'MYFOLDER_NAME'	=> trim($folder['name']),
                'MYFOLDER_LEFT'	=> number_format($folder['depth'] * 14, 0),
            );
			$this->setVar($output);
			$fileList = $myFileHandler->getAllInFolder($folder['id']);
			if (is_array($fileList)) {
				foreach ($fileList as $file) {
					$output = array(
                        'MYFILE_ID'		=> $file['id'],
                        'MYFILE_NAME'	=> $file['description'],
                    );
					$this->setVar($output);
					$this->parse('my_files_block', 'my_files', true);
				}
			}
		}
	}

	$this->parse('optgroup_block', 'optgroup', true);
	$this->setVar('my_files_block', '');

	/* Check for files in the root
	 */
	$fileList = $myFileHandler->getAllInFolder(0);

	if (is_array($fileList)) {
		$this->setVar(array(
			'MYFOLDER_NAME' => 'Root'
		));

		foreach ($fileList as $file) {
			$output = array(
				'MYFILE_ID' => $file['id'],
				'MYFILE_NAME' => $file['description'],
			);

			$this->setVar($output);
			$this->parse('my_files_block', 'my_files', true);
		}
	}

	$this->parse('optgroup_block', 'optgroup', true);

	/* Parse show_my_files only if WebDAV is disabled
	 */
	if (!Cfg::get('webdav_enable')) {
		$this->parse('show_my_files_block', 'show_my_files');
	}
	else {
		$this->setVar('show_my_files_block', '');
	}
}
else if($this->arg[0] == 'multi' && is_numeric($this->arg[1])) {
	/* Upload multiple files at once */
	require_once '../library/category.php';

	$this->setFile(TEMPLATE_content, 'cms/add-multi-file.html');

	$this->setTitle('Upload Multiple Files');
	$this->setTab('resources');

	$categoryHandler = new Category;
	$category = $categoryHandler->getByID($this->arg[1]);

	if (!is_array($category)) {
		error(13);
	}

	if ($groupHandler->getPermission($category['id']) < WRITE_ACCESS) {
		error(6);
	}

	$output = array(
        'CATEGORY_NAME'		=> Scrub::text($category['name']),
        'CATEGORY_ID'		=> $category['id'],
        'FILE_max_upload'	=> Cfg::get('file_upload_count'),
        'SESSION_NAME'		=> session_name(),
        'SESSION_ID'		=> session_id(),
    );
	$this->setVar($output);
}
else if($this->arg[0] == 'multicat') {
	/* Upload a file to multiple categories */
	require_once '../library/group.php';

	$this->setFile(TEMPLATE_content, 'cms/add-file-multi-cat.html');
	$this->setBlock(TEMPLATE_content, 'optgroup', 'optgroup_block');
	$this->setBlock('optgroup', 'my_files', 'my_files_block');
	$this->setBlock(TEMPLATE_content, 'category_list', 'category_list_block');
	$this->setBlock(TEMPLATE_content, 'no_categories', 'no_categories_block');

	$this->setTitle('Upload a File to Multiple Locations');
	$this->setTab('resources');

	$output = array(
		'MAX_FILE_SIZE'	=> Cfg::get('max_file_size'),
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

	$myFolderHandler = new MyFolder;
	$myFileHandler = new MyFile;

	$folderTree = $myFolderHandler->getFolderTree();

	if (is_array($folderTree)) {
		foreach ($folderTree as $folder) {
			$output = array(
				'MYFOLDER_NAME'	=> trim($folder['name']),
				'MYFOLDER_LEFT'	=> number_format($folder['depth'] * 14, 0),
			);

			$this->setVar($output);

			$fileList = $myFileHandler->getAllInFolder($folder['id']);

			if (is_array($fileList)) {
				foreach ($fileList as $file) {
					$output = array(
						'MYFILE_ID' => $file['id'],
						'MYFILE_NAME' => $file['description'],
					);

					$this->setVar($output);

					$this->parse('my_files_block', 'my_files', true);
				}
			}

			$this->parse('optgroup_block', 'optgroup', true);
			$this->setVar('my_files_block', '');
		}
	}
}
else {
	error(2);
}

?>

<?php

if(is_numeric($this->arg[0])) {
	
	/* Modify existing file */
	
	require_once '../library/file.php';
	require_once '../library/submission-box.php';
	$fileHandler = new File;
	
	$file = $fileHandler->getByID($this->arg[0]);
	if(!is_array($file))
		error(41);

	$this->setFile(TEMPLATE_content, 'cms/modify-file.html');
	$this->setBlock(TEMPLATE_content, 'browse', 'browse_block');
	$this->setTitle('Modify File');	
	$this->setTab('resources');
	
	$this->setVar('browse_block', '');
	
	$output = array('ACTION'			=> 'Modify',
					'FILE_ID'			=> $file['id'],
					'DESCRIPTION'		=> scrubText($file['description']),
					'COMMENT'			=> scrubText($file['comment']),	
					'HIDDEN_SELECTED'	=> 	$file['hidden'] > 0 ? 'checked="checked"' : '',
					'MAX_FILE_SIZE'		=> MAX_FILE_SIZE,
					'CATEGORY_ID'		=> $file['cid'],
					'CATEGORY_NAME'		=> scrubText($file['categoryName']),
					'DUE_DAY'			=> '',
					'DUE_MONTH'			=> '',
					'DUE_YEAR'			=> '',
					);
	$this->setVar($output);
	
	/* Output different controls for submission boxes */
	$subBoxHandler = new SubmissionBox;
	$subBox = $subBoxHandler->getByField('fid', $file['id']);
	
	if(is_array($subBox)) {
		if($subBox['deleted'])
			$this->setVar('SUBBOX', "<a href=\"/submission/listSubmission.php?undelete=".$subBox['id']."\">undelete box</a>");
		else
			$this->setVar("SUBBOX", "<a href=\"/submission/listSubmission.php?delete=".$subBox['id']."\">delete 
				</a> // <a href=\"/submission/createSubmission.php?sid=".$subBox['id']."\">modify</a>");
	}
	else {
		$this->setVar("SUBBOX", "<a href=\"/submission/createSubmission.php?cid=".$file['category']."&fileid=".$file['id']."\">create</a>");
	}
	
	
} else if($this->arg[0] == 'new' && is_numeric($this->arg[1])) {
	
	/* Add new file to system */
	require_once '../library/category.php';
	
	$this->setFile(TEMPLATE_content, 'cms/modify-file.html');

	$this->setTitle('Add File');	
	$this->setTab('resources');
	
	$categoryHandler = new Category;
	$category = $categoryHandler->getByID($this->arg[1]);
	if(!is_array($category))
		error(13);

	$output = array('ACTION'			=> 'Add',
					'FILE_ID'			=> '',
					'DESCRIPTION'		=> '',
					'COMMENT'			=> '',	
					'HIDDEN_SELECTED'	=> '',
					'MAX_FILE_SIZE'		=> MAX_FILE_SIZE,
					'SUBBOX'			=> '<input type="checkbox" name="submission" value="1" >',
					'CATEGORY_NAME'		=> scrubTEXT($category['name']),
					'CATEGORY_ID'		=> $category['id'],
					'DUE_DAY'			=> is_numeric($this->arg[2])?$this->arg[2]:'',
					'DUE_MONTH'			=> is_numeric($this->arg[3])?$this->arg[3]:'',
					'DUE_YEAR'			=> is_numeric($this->arg[4])?$this->arg[4]:'',
				);

	$this->setVar($output);
	


}
else if($this->arg[0] == 'multi' && is_numeric($this->arg[1])) {
	/* Upload multiple files at once */
	require_once '../library/category.php';
	
	$this->setFile(TEMPLATE_content, 'cms/add-multi-file.html');

	$this->setTitle('Upload Multiple Files');	
	$this->setTab('resources');
	
	$categoryHandler = new Category;
	$category = $categoryHandler->getByID($this->arg[1]);
	if(!is_array($category))
		error(13);
		
	$output = array('CATEGORY_NAME'		=> scrubTEXT($category['name']),
					'CATEGORY_ID'		=> $category['id'],
					'FILE_max_upload'	=> FILE_max_upload,
					);
	$this->setVar($output);
}
else if($this->arg[0] == 'multicat') {
	/* Upload a file to multiple categories */
	require_once '../library/group.php';
	
	$this->setFile(TEMPLATE_content, 'cms/add-file-multi-cat.html');
	$this->setBlock(TEMPLATE_content, 'category_list', 'category_list_block');

	$this->setTitle('Upload Multiple Files');	
	$this->setTab('resources');
	
	$groupHandler = new Group;
	$categoryList = $groupHandler->involvedCategoryList($_SESSION['id'], $_SESSION['subjects'], 2);

	if(is_array($categoryList)) {
		foreach($categoryList as $category) {
			$output = array('CATEGORY_NAME'		=> scrubTEXT($category['name']),
							'CATEGORY_ID'		=> $category['id'],
					);
			$this->setVar($output);
			$this->parse('category_list_block', 'category_list', true);
		}
	}
}
else {
	error(2);
}

?>
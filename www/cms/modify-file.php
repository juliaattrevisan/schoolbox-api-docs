<?php
// replacement of cms/modResource.php

require_once '../library/file.php';
$fileHandler = new File;

$category = $fileHandler->getByID($this->arg[0]);

if ($_POST['title']) {

	$category = array('name'		=> $_POST['name'],
					  'parent'		=> $_POST['parent'],
					  'template'	=> $_POST['template'],
					  'code'		=> $_POST['code'],
					  );

	if (isset($_POST['id'])) {
		$category['id'] = $_POST['id'];
		$fileHandler->update($category);
	}
	else
		$fileHandler->create($category);
		
	if (is_array())
	
	localRedir('/browse.php?category=' . $_POST['parent']);
} 
elseif ($_POST['delete']) {

	$category = $fileHandler->getByID($_POST['delete']);
	$fileHandler->delete($_POST['delete']);
	localRedir('/browse.php?category=' . $category['parent']);
}
elseif ($category['id']) {
	$this->setFile(TEMPLATE_content, "cms/modify-file.html");
	$this->setBlock(TEMPLATE_content, 'perm_table', 'perm_table_block');
	$this->setBlock(TEMPLATE_content, 'indiv_perms', 'indiv_perms_block');
	$this->setBlock(TEMPLATE_content, 'indiv_modify', 'indiv_modify_block');

	$this->setVar('TITLE', HEADING . " - Modify Category");	
	
	$output = array('ACTION'	=> 'Modify',
					'CATEGORY_ID'	=> $category['id'],
					'NAME'		=> scrubText($category['name']),
					'CODE'		=> scrubText($category['code']),				
					);
	$this->setVar($output);
	
	$categoryTree = $fileHandler->getCategoryTree();
	array_unshift($categoryTree, array('CAT_ID' => 0, 'CAT_NAME' => 'Top Level'));
	$this->dropDown('parent_list', $categoryTree, $category['parent']);

	$templateList = $fileHandler->getTemplateList();
	array_unshift($templateList, array('TEMPLATE_ID' => 0, 'TEMPLATE_NAME' => 'Keep Existing'));
	$this->dropDown('template_list', $templateList);	
	
	$groupPermList = $fileHandler->getGroupPerms($category['id']);

	if (is_array($groupPermList)) {
		foreach ($groupPermList as $group) {
			$output = array('GROUP_NAME'		=> scrubText($group['name']),
							'GROUP_ID'			=> $group['id'],
							'PERM_0_SELECTED'	=> '',
							'PERM_1_SELECTED'	=> '',
							'PERM_2_SELECTED'	=> '',
							'PERM_' . (int)$group['type'] . '_SELECTED'	=> 'checked="checked"',
							);
			$this->setVar($output);
			$this->parse('perm_table_block', 'perm_table', true);
		}
	} else
		$this->setVar('perm_table_block', '');
	
	if($category['owner'] == $_SESSION['id'] || ($category['owner'] && $_SESSION['su']) ) {
		$this->setVar('indiv_perms_block', '');
		$this->parse('indiv_modify_block', 'indiv_modify');
	} 
	else if($category['owner']) {
		$this->setVar('indiv_perms_block', '');
		$this->setVar('indiv_modify_block', '');
	} else {
		$this->parse('indiv_perms_block', 'indiv_perms');
		$this->setVar('indiv_modify_block', '');
	}
	
} else {

	$this->setFile(TEMPLATE_content, "cms/modify-category.html");
	$this->setBlock(TEMPLATE_content, 'perm_table', 'perm_table_block');
	$this->setBlock(TEMPLATE_content, 'indiv_perms', 'indiv_perms_block');
	$this->setBlock(TEMPLATE_content, 'indiv_modify', 'indiv_modify_block');

	$this->setVar('TITLE', HEADING . " - Add Category");	

	$output = array('ACTION'		=> 'Add',
					'CATEGORY_ID'	=> '',
					'NAME'			=> '',
					'CODE'			=> '',				
					);
	$this->setVar($output);
	

	$categoryTree = $fileHandler->getCategoryTree();
	array_unshift($categoryTree, array('CAT_ID' => 0, 'CAT_NAME' => 'Top Level'));
	$this->dropDown('parent_list', $categoryTree);

	$templateList = $fileHandler->getTemplateList();
	array_unshift($templateList, array('TEMPLATE_ID' => 0, 'TEMPLATE_NAME' => 'No Homepage'));
	$this->dropDown('template_list', $templateList);	

	$groupPermList = $fileHandler->getGroupPerms(0);
	if (is_array($groupPermList)) {
		foreach ($groupPermList as $group) {
			$output = array('GROUP_NAME'		=> scrubText($group['name']),
							'GROUP_ID'			=> $group['id'],
							'PERM_0_SELECTED'	=> '',
							'PERM_1_SELECTED'	=> '',
							'PERM_2_SELECTED'	=> '',
							);
			$this->setVar($output);
			$this->parse('perm_table_block', 'perm_table', true);
		}
	} else
		$this->setVar('perm_table_block', '');

	$this->parse('indiv_perms_block', 'indiv_perms');
	$this->setVar('indiv_modify_block', '');

}

?>
<?php
require_once '../library/category.php';
require_once '../library/homepage.php';
require_once '../library/homepage-template.php';
require_once '../library/group.php';
$categoryHandler = new Category;
$homeTemplateHandler = new HomepageTemplate;
$groupHandler = new Group;

if(is_numeric($this->arg[0])) {
	if (!($category = $categoryHandler->getByID($this->arg[0]))) {
		error(2);
	}

	if ($category['parent'] == 0) {
		$categoryParent = array('id' => 0, 'name' => 'Top');
	}
	else if(!($categoryParent = $categoryHandler->getByID($category['parent']))) {
		error(2);
	}

	if ($groupHandler->getPermission($category['id']) < WRITE_ACCESS) {
		error(6);
	}

	$this->setFile(TEMPLATE_content, "cms/modify-category.html");
	$this->setBlock(TEMPLATE_content, 'perm_table', 'perm_table_block');
	$this->setBlock(TEMPLATE_content, 'indiv_perms', 'indiv_perms_block');
	$this->setBlock(TEMPLATE_content, 'indiv_modify', 'indiv_modify_block');
	$this->setBlock(TEMPLATE_content, 'reset_content', 'reset_content_block');
    $this->setBlock(TEMPLATE_content, 'synergy_input', 'synergy_input_block');

	$this->setTitle('Modify Category');
	$this->setTab('resources');

	$output = array(
		'ACTION'		=> 'Modify',
		'CATEGORY_ID'	=> $category['id'],
		'NAME'			=> Scrub::text($category['name']),
		'CODE'			=> Scrub::text($category['code']),
		'PARENT_ID'		=> $category['parent'],
		'PARENT_NAME'	=> Scrub::text($categoryParent['name']),
		'USER_GROUP' => $_SESSION['gid'],
		'DATABASE_TYPE' => ucfirst(Cfg::get('external_type')),
		'REQUIRED_PERMS'=> $_SESSION['gid'],
	);

    $this->setVar($output);

	$templateList = $homeTemplateHandler->getTemplateListDropDown();

	/* Prepend
	 */
	array_unshift($templateList, array(
		'TEMPLATE_ID' => 0,
		'TEMPLATE_NAME' =>
		'Keep Existing'
	));

	/* Used to remove homepages
	 */
	$templateList[] = array(
		'TEMPLATE_ID' => -1,
		'TEMPLATE_NAME' => 'No homepage'
	);

	$this->dropDown('template_list', $templateList);

	$groupPermList = $categoryHandler->getGroupPerms($category['id']);

	if (is_array($groupPermList)) {
		foreach ($groupPermList as $group) {
			$output = array(
				'GROUP_NAME'		=> Scrub::text($group['name']),
				'GROUP_ID'			=> $group['id'],
				'PERM_0_SELECTED'	=> '',
				'PERM_1_SELECTED'	=> '',
				'PERM_2_SELECTED'	=> '',
				'PERM_' . (int)$group['type'] . '_SELECTED'	=> 'checked="checked"',
			);

			$this->setVar($output);
			$this->parse('perm_table_block', 'perm_table', true);
		}
	}
	else {
		$this->setVar('perm_table_block', '');
	}

	if ($category['owner'] == $_SESSION['id'] || ($category['owner'] && $_SESSION['su']) ) {
		$this->setVar('indiv_perms_block', '');
		$this->parse('indiv_modify_block', 'indiv_modify');
	}
	else if($category['owner']) {
		$this->setVar('indiv_perms_block', '');
		$this->setVar('indiv_modify_block', '');
	}
	else {
		$this->parse('indiv_perms_block', 'indiv_perms');
		$this->setVar('indiv_modify_block', '');
	}

	$this->parse('reset_content_block', 'reset_content');

    if ($groupHandler->isSuperuser()) {
        $this->parse('synergy_input_block','synergy_input');
    }
    else {
        $this->setVar('synergy_input_block','');
    }
}
else if ($this->arg[0] == 'new' && is_numeric($this->arg[1])) {
	if ($this->arg[1] == 0) {
		$categoryParent = array('id' => 0, 'name' => 'Top');
	}
	else if(!($categoryParent = $categoryHandler->getByID($this->arg[1]))) {
		error(2);
	}

	if ($groupHandler->getPermission($categoryParent['id']) < WRITE_ACCESS) {
		error(6);
	}

	$this->setFile(TEMPLATE_content, 'cms/modify-category.html');
	$this->setBlock(TEMPLATE_content, 'perm_table', 'perm_table_block');
	$this->setBlock(TEMPLATE_content, 'indiv_perms', 'indiv_perms_block');
	$this->setBlock(TEMPLATE_content, 'indiv_modify', 'indiv_modify_block');
	$this->setBlock(TEMPLATE_content, 'reset_content', 'reset_content_block');
    $this->setBlock(TEMPLATE_content, 'synergy_input', 'synergy_input_block');

	$this->setTitle('Add Category');
	$this->setTab('resources');

	$output = array(
		'ACTION' => 'Add',
		'CATEGORY_ID' => '',
		'NAME' => '',
		'CODE' => '',
		'PARENT_ID'	=> $categoryParent['id'],
		'PARENT_NAME' => Scrub::text($categoryParent['name']),
		'REQUIRED_PERMS' => $_SESSION['gid'],
		'GROUP_NAME' => '',
		'DATABASE_TYPE' => ucfirst(Cfg::get('database_type')),
	);

	$this->setVar($output);

	$templateList = $homeTemplateHandler->getTemplateListDropDown();
	array_unshift($templateList, array('TEMPLATE_ID' => 0, 'TEMPLATE_NAME' => 'No Homepage'));
	$this->dropDown('template_list', $templateList);

	$groupPermList = $categoryHandler->getGroupPerms(0);

	if (is_array($groupPermList)) {
		foreach ($groupPermList as $group) {
			$output = array(
				'GROUP_NAME'		=> Scrub::text($group['name']),
				'GROUP_ID'			=> $group['id'],
				'PERM_0_SELECTED'	=> 'checked="checked"',
				'PERM_1_SELECTED'	=> '',
				'PERM_2_SELECTED'	=> '',
			);

			$this->setVar($output);
			$this->parse('perm_table_block', 'perm_table', true);
		}
	}
	else {
		$this->setVar('perm_table_block', '');
	}

	$this->parse('indiv_perms_block', 'indiv_perms');
	$this->setVar('indiv_modify_block', '');
	$this->setVar('reset_content_block','');

    if($groupHandler->isSuperuser()) {
        $this->parse('synergy_input_block','synergy_input');
    }
    else {
        $this->setVar('synergy_input_block','');
    }
}
else if($this->arg[0] == 'reset' && is_numeric($this->arg[1])) {
	if (!($category = $categoryHandler->getByID($this->arg[1]))) {
		error(2);
	}

	if ($groupHandler->getPermission($category['id']) < WRITE_ACCESS) {
		error(6);
	}

	// remove component content
	$homepage = new Homepage($category['id']);
	$homepage->emptyHomepage();

	// remove files and links
	$categoryHandler->recursiveDelete($category['id'], false);

	localRedir("/homepage/{$category['id']}");
}

?>

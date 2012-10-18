<?php
require_once SITE_root . '/library/user.php';
require_once SITE_root . '/library/group.php';
require_once SITE_root . '/library/homepage-template.php';

$groupHandler = new Group();

if (!$groupHandler->isSuperuser()) {
    error(19);
}

$this->setTitle('Admin - Category');
$this->setFile(TEMPLATE_content, 'adminv2/category.html');
$this->setBlock(TEMPLATE_content, 'group', 'group_block');
$this->setBlock(TEMPLATE_content, 'homepage_template', 'homepage_template_block');

// include the admin navigation
$nav = new AdminV2_Nav();
$nav->parse($this, 'category');

$userHandler = new User();

/* Show all the groups for permission selection
 */
$allGroups = $userHandler->getGroupList();

foreach($allGroups as $currentGroup) {
	$this->setVar(array(
		'GROUP_ID' => $currentGroup['id'],
		'GROUP_NAME' => $currentGroup['name'],
	));

	$this->parse('group_block', 'group', true);
}

/* Show all the possible templates a homepage can have
 */
$homepageTemplateHandler = new HomepageTemplate();

$allTemplates = $homepageTemplateHandler->getTemplateListDropDown();

if (is_array($allTemplates) && count($allTemplates)) {
	foreach($allTemplates as $currentTemplate) {
		$this->setVar(array(
			'HOMEPAGE_TEMPLATE_ID' => $currentTemplate['TEMPLATE_ID'],
			'HOMEPAGE_TEMPLATE_NAME' => $currentTemplate['TEMPLATE_NAME']
		));

		$this->parse('homepage_template_block', 'homepage_template', true);
	}
}
else {
	$this->setVar('homepage_template_block', '');
}

?>

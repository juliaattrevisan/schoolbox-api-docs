<?php
require_once '../library/category.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/../library/group.php';

$groupHandler = new Group();
$categoryHandler = new Category();

if ($groupHandler->isSuperuser()) {
	$this->setFile(TEMPLATE_content, 'cms/zipFile.html');
	$this->setBlock(TEMPLATE_content, 'perm_table', 'perm_table_block');

	/* Set up permissions options
	 */
	$groupPermList = $categoryHandler->getGroupPerms(0);

	if (is_array($groupPermList)) {
		foreach ($groupPermList as $group) {
			$this->setVar(array(
				'GROUP_NAME'		=> scrubText($group['name']),
				'GROUP_ID'			=> $group['id'],
				'PERM_0_SELECTED'	=> 'checked="checked"',
				'PERM_1_SELECTED'	=> '',
				'PERM_2_SELECTED'	=> '',
			));

			$this->parse('perm_table_block', 'perm_table', true);
		}
	}
	else {
		$this->setVar('perm_table_block', '');
	}
}
else {
	error(19);
}

?>

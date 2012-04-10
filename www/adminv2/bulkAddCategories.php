<?php
require_once SITE_root . '/library/group.php';

$groupHandler = new Group();

if (!$groupHandler->isSuperuser()) {
    error(19);
}

$this->setTitle('Admin - Bulk Add Categories');
$this->setFile(TEMPLATE_content, 'adminv2/bulkAddCategories.html');
$this->setBlock(TEMPLATE_content, 'category', 'category_block');

/* Two types of upload.. either single user.. or via csv file..
 */
if (isset($_FILES['bulkadd']['tmp_name']) && $_FILES['bulkadd']['tmp_name']) {
	$bulkHandler = new Bulk_Category($_FILES['bulkadd']['tmp_name'], Scrub::text($_POST['template']));
	$allCategories = $bulkHandler->addCategories();

	if ($allCategories && count($allCategories)) {
		foreach ($allCategories as $currentCategory) {
			$this->setVar(array(
				'CATEGORY_CATEGORY' => $currentCategory['category'],
				'CATEGORY_STATUS' => $currentCategory['status'],
				'CATEGORY_ERROR' => $currentCategory['error'],
			));

			$this->parse('category_block', 'category', true);
		}
	}
	else {
		$this->setVar('category_block', '');
	}
}
else {
	error(2);
}

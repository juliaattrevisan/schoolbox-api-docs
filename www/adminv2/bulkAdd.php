<?php
require_once SITE_root . '/library/bulk.php';

$this->setTitle('Admin - Bulk Add Users');
$this->setFile(TEMPLATE_content, 'adminv2/bulkAdd.html');
$this->setBlock(TEMPLATE_content, 'user', 'user_block');

$bulkHandler = new Bulk();

/* Two types of upload.. either single user.. or via csv file..
 */
if (isset($_FILES['bulkadd']['tmp_name']) && $_FILES['bulkadd']['tmp_name']) {
	$allUsers = $bulkHandler->addUsers($_FILES['bulkadd']['tmp_name']);

	if ($allUsers && count($allUsers)) {
		foreach ($allUsers as $currentUser) {
			$this->setVar(array(
				'USER_USERNAME' => $currentUser['username'],
				'USER_STATUS' => $currentUser['status'],
				'USER_ERROR' => $currentUser['error'],
			));

			$this->parse('user_block', 'user', true);
		}
	}
	else {
		$this->setVar('user_block', '');
	}
}
else {
	error(2);
}
?>
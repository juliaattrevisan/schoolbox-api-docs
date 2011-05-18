<?php
require_once SITE_root . '/library/bulk-user.php';

$this->setTitle('Admin - Bulk Remove Users');
$this->setFile(TEMPLATE_content, 'adminv2/bulkRemove.html');
$this->setBlock(TEMPLATE_content, 'user', 'user_block');

$bulkUserHandler = new BulkUser();

/* Two types of upload.. either single user.. or via csv file..
 */
if (isset($_FILES['bulkremove']['tmp_name']) && $_FILES['bulkremove']['tmp_name']) {
	$allUsers = $bulkUserHandler->remove($_FILES['bulkremove']['tmp_name']);

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